<?php

namespace WackCloudfrontInvalidation;

use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;
use WP_Post;

/**
 * Class CloudFrontInvalidationHook
 *
 * @package WackCloudfrontInvalidation
 */
class CloudFrontInvalidationHook
{
    /**
     * Post statuses that are not publicly visible and are normally skipped
     */
    private const UNPUBLISHED_STATUSES = ['auto-draft', 'draft', 'inherit', 'pending'];

    /**
     * Post meta key where WordPress stores the original slug of a trashed post
     */
    private const DESIRED_SLUG_META_KEY = '_wp_desired_post_slug';

    /**
     * Interval in seconds used to deduplicate invalidations for the same save
     */
    private const CALLER_REFERENCE_INTERVAL = 10;

    /**
     * Status of each post before the current save, keyed by post ID
     *
     * Once 'publish' is recorded, it is kept until the invalidation is processed
     * so that nested saves (e.g. wp_update_post() in a save_post callback) do not
     * hide the unpublish transition.
     *
     * @var array<int, string>
     */
    private array $previousStatuses = [];

    /**
     * Slug of each post while it was published, keyed by post ID
     *
     * The slug may change on the same save that unpublishes the post (e.g. WordPress
     * clears it when a user without the publish capability changes the post to pending),
     * so the cached URL has to be resolved with the slug before the save.
     * Like $previousStatuses, the first recorded slug is kept until the invalidation is processed.
     *
     * @var array<int, string>
     */
    private array $previousSlugs = [];

    /**
     * Initialize the hooks
     */
    public function init(): void
    {
        // Remember the previous status so that unpublishing (publish -> draft/pending) can be detected.
        // This fires inside wp_insert_post before save_post and rest_after_insert_{$post_type}.
        add_action('transition_post_status', [$this, 'rememberPreviousStatus'], 10, 3);

        // Remember the slug while published so that the cached URL is invalidated even if the slug changes.
        // This also fires inside wp_insert_post before save_post and rest_after_insert_{$post_type}.
        add_action('post_updated', [$this, 'rememberPreviousSlug'], 10, 3);

        // Invalidate on standard post save (classic editor, admin edits, etc.).
        add_action('save_post', [$this, 'cloudFrontInvalidation'], 10, 3);

        // Build a unique list of post types that should receive REST hooks.
        $postTypes = array_merge(['post'], array_map(fn($postType) => $postType->name, Utility::getPostTypes()));
        $postTypes = array_values(array_unique($postTypes));

        foreach ($postTypes as $postType) {
            // Invalidate when a REST insert/update completes for each post type.
            add_action('rest_after_insert_' . $postType, [$this, 'cloudFrontInvalidationAfterRest'], 10, 3);
        }
    }

    /**
     * Record the status of the post before the transition.
     */
    public function rememberPreviousStatus(string $newStatus, string $oldStatus, WP_Post $post): void
    {
        if (($this->previousStatuses[$post->ID] ?? null) === 'publish') {
            return;
        }

        $this->previousStatuses[$post->ID] = $oldStatus;
    }

    /**
     * Record the slug of the post before the update if it was published.
     */
    public function rememberPreviousSlug(int $postId, WP_Post $postAfter, WP_Post $postBefore): void
    {
        if ($postBefore->post_status !== 'publish' || isset($this->previousSlugs[$postId])) {
            return;
        }

        $this->previousSlugs[$postId] = $postBefore->post_name;
    }

    /**
     * Hook into save_post and automatically issue appropriate CloudFront
     * Invalidation based on post type
     *
     * When the constant WACK_CF_INV_DRY_RUN is set to true, it will only log
     * the output without actually performing invalidation.
     */
    public function cloudFrontInvalidation(int $post_ID, WP_Post $post, bool $update): void
    {
        $this->invalidateForPost($post, false);
    }

    /**
     * Handle REST API updates after the post is inserted.
     */
    public function cloudFrontInvalidationAfterRest(WP_Post $post, $request, bool $creating): void
    {
        $this->invalidateForPost($post, true);
    }

    /**
     * Execute invalidation with shared guard logic.
     */
    private function invalidateForPost(WP_Post $post, bool $isRestHook): void
    {
        if ($this->shouldSkip($post, $isRestHook)) {
            return;
        }

        // The previous status and slug have been consumed by this invalidation
        $previousSlug = $this->previousSlugs[$post->ID] ?? null;
        unset($this->previousStatuses[$post->ID], $this->previousSlugs[$post->ID]);

        // Execute invalidation according to settings for each post type
        $paths = PluginSettings::get()->invalidationPathsFor($post->post_type);

        /**
         * Filters the array of CloudFront paths for the specified post type.
         *
         * This filter is for paths determined by the plugin settings.
         * The $paths parameter receives values set by the plugin configuration.
         *
         * @param array   $paths Array of paths to be invalidated in CloudFront.
         * @param WP_Post $post  The post object being processed.
         *
         * @return array Filtered array of paths.
         */
        $paths = apply_filters('wack_cf_inv_' . $post->post_type . '_paths', $paths, $post);

        if (empty($paths)) {
            return;
        }

        $paths = $this->resolvePaths($paths, $post, $previousSlug);

        // Check if Dry Run mode is enabled
        if (PluginSettings::get()->dryRun()) {
            $distributionId = PluginSettings::get()->distributionId() ?? 'not defined';
            Utility::infoLog('[CloudFront Invalidation Dry Run] Distribution ID: ' . $distributionId . ', Paths: ' . json_encode($paths));
        } else {
            $this->executeInvalidation($paths, $this->buildCallerReference($post, $paths, time()));
        }
    }

    /**
     * Replace placeholders in the paths for the post.
     *
     * If the slug has changed from the one while published, paths resolved with
     * the previous slug are also included so that the cached URL is invalidated.
     *
     * @param array       $paths        Paths with placeholders
     * @param WP_Post     $post         The post being processed
     * @param string|null $previousSlug Slug of the post while it was published
     *
     * @return array Paths with placeholders replaced, without duplicates
     */
    private function resolvePaths(array $paths, WP_Post $post, ?string $previousSlug): array
    {
        $resolvedPaths = array_map(fn($path) => $this->replacePlaceholder($path, $post), $paths);

        if (!empty($previousSlug) && $previousSlug !== $post->post_name) {
            $previousPost = clone $post;
            $previousPost->post_name = $previousSlug;
            $previousPost->post_status = 'publish';

            $resolvedPaths = array_merge(
                $resolvedPaths,
                array_map(fn($path) => $this->replacePlaceholder($path, $previousPost), $paths),
            );
        }

        return array_values(array_unique($resolvedPaths));
    }

    /**
     * Build the CloudFront caller reference for the post.
     *
     * Duplicate hooks for the same save (e.g. the REST request and the meta box
     * request of the block editor) share the same reference within a 10-second
     * window, so CloudFront does not issue the invalidation twice. The post status
     * is included so that a status change (e.g. publish -> draft) within the same
     * window is issued as a separate invalidation. A fingerprint of the paths is
     * included because CloudFront rejects a different batch with the same reference
     * (InvalidationBatchAlreadyExists), e.g. when the slug changes within the window.
     *
     * @param WP_Post $post      The post being processed
     * @param array   $paths     The resolved paths to be invalidated
     * @param int     $timestamp The current Unix timestamp
     */
    private function buildCallerReference(WP_Post $post, array $paths, int $timestamp): string
    {
        $windowStart = intdiv($timestamp, self::CALLER_REFERENCE_INTERVAL) * self::CALLER_REFERENCE_INTERVAL;
        $pathsFingerprint = substr(md5(implode("\n", $paths)), 0, 8);

        return $post->ID . '-' . $post->post_status . '-' . date('YmdHis', $windowStart) . '-' . $pathsFingerprint;
    }

    /**
     * Determine whether invalidation should be skipped for this post.
     */
    private function shouldSkip(WP_Post $post, bool $isRestHook): bool
    {
        // Prevent excessive invalidations by not clearing cache for unpublished posts,
        // unless the post has just been unpublished and its cache must be cleared.
        $wasPublished = ($this->previousStatuses[$post->ID] ?? null) === 'publish';
        if (in_array($post->post_status, self::UNPUBLISHED_STATUSES, true) && !$wasPublished) {
            return true;
        }

        $doingAutosave = defined('DOING_AUTOSAVE') && DOING_AUTOSAVE;
        $isAutosave = wp_is_post_autosave($post->ID);
        $isRevision = wp_is_post_revision($post->ID);
        $isRestRequest = defined('REST_REQUEST') && REST_REQUEST;
        $restAfterInsertPost = did_action('rest_after_insert_post');

        // Skip processing in the following cases:
        // 1. During WordPress autosave operations
        // 2. When the post is an autosave
        // 3. When the post is a revision
        // 4. During REST API requests before the post is completely inserted
        if (
            $doingAutosave
            || $isAutosave
            || $isRevision
            || (!$isRestHook && $isRestRequest && !$restAfterInsertPost)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Executes the CloudFront invalidation process for specified paths.
     *
     * This method triggers the actual invalidation request to AWS CloudFront
     * for the provided paths using the given caller reference ID for tracking.
     *
     * @param array  $paths             The paths to be invalidated in CloudFront
     * @param string $callerReferenceID A unique identifier for this invalidation request
     */
    private function executeInvalidation(array $paths, string $callerReferenceID): void
    {
        // Check if CloudFront Distribution ID is defined
        $distributionId = PluginSettings::get()->distributionId();
        if (empty($distributionId)) {
            Utility::errorLog('CloudFront Invalidation Error: Distribution ID not defined.');
            return;
        }

        $client = new CloudFrontClient([
            'version' => 'latest',
            'region' => 'us-east-1',
        ]);

        try {
            $client->createInvalidation([
                'DistributionId' => $distributionId,
                'InvalidationBatch' => [
                    'CallerReference' => $callerReferenceID,
                    'Paths' => [
                        'Items' => $paths,
                        'Quantity' => count($paths),
                    ],
                ],
            ]);
        } catch (AwsException $e) {
            Utility::errorLog('CloudFront Invalidation Error (AwsException): ' . $e->getMessage());
        } catch (\Exception $e) {
            Utility::errorLog('CloudFront Invalidation Error (Exception): ' . $e->getMessage());
        }
    }

    /**
     * Replace placeholder with an actual post id or slug
     *
     * - %id% will be replaced with the post ID
     * - %slug% will be replaced with the post slug
     * - If the placeholder is not found, the original path will be returned
     * - If the post does not have the slug, it will fallback to the post ID
     * - If the post is trashed, the original slug stored by WordPress is used
     *
     * @param string $path_with_placeholder
     * @param WP_Post $post
     *
     * @return string
     */
    private function replacePlaceholder(string $path_with_placeholder, WP_Post $post): string
    {
        $temp_string = str_replace('%id%', $post->ID, $path_with_placeholder);

        $slug = $post->post_name;
        // WordPress renames the slug to "{slug}__trashed" (truncated if too long) when trashing
        // and stores the original one in post meta, which is the slug of the cached path
        if ($post->post_status === 'trash') {
            $desiredSlug = get_post_meta($post->ID, self::DESIRED_SLUG_META_KEY, true);
            if (is_string($desiredSlug) && $desiredSlug !== '') {
                $slug = $desiredSlug;
            }
        }

        if (empty($slug)) {
            return str_replace('%slug%', $post->ID, $temp_string);
        } else {
            return str_replace('%slug%', $slug, $temp_string);
        }
    }
}
