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
     * Initialize the hooks
     */
    public function init(): void
    {
        // Remember the previous status so that unpublishing (publish -> draft/pending) can be detected.
        // This fires inside wp_insert_post before save_post and rest_after_insert_{$post_type}.
        add_action('transition_post_status', [$this, 'rememberPreviousStatus'], 10, 3);

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

        // The previous status has been consumed by this invalidation
        unset($this->previousStatuses[$post->ID]);

        $callerReferenceID = $this->buildCallerReference($post, time());

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

        $paths = array_map(fn($path) => $this->replacePlaceholder($path, $post), $paths);

        // Check if Dry Run mode is enabled
        if (PluginSettings::get()->dryRun()) {
            $distributionId = PluginSettings::get()->distributionId() ?? 'not defined';
            Utility::infoLog('[CloudFront Invalidation Dry Run] Distribution ID: ' . $distributionId . ', Paths: ' . json_encode($paths));
        } else {
            $this->executeInvalidation($paths, $callerReferenceID);
        }
    }

    /**
     * Build the CloudFront caller reference for the post.
     *
     * Duplicate hooks for the same save (e.g. the REST request and the meta box
     * request of the block editor) share the same reference within a 10-second
     * window, so CloudFront does not issue the invalidation twice. The post status
     * is included so that a status change (e.g. publish -> draft) within the same
     * window is issued as a separate invalidation.
     */
    private function buildCallerReference(WP_Post $post, int $timestamp): string
    {
        $windowStart = intdiv($timestamp, self::CALLER_REFERENCE_INTERVAL) * self::CALLER_REFERENCE_INTERVAL;

        return $post->ID . '-' . $post->post_status . '-' . date('YmdHis', $windowStart);
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
