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
     * Initialize the hooks
     */
    public function init(): void
    {
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

        // Prevent multiple executions by adding a timestamp with minute precision to the caller reference
        $callerReferenceID = $post->ID . '-' . date('YmdHi');

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

        // Check if dry-run mode is enabled
        if (defined('WACK_CF_INV_DRY_RUN') && constant('WACK_CF_INV_DRY_RUN') === true) {
            $distributionId = defined('WACK_CF_INV_DISTRIBUTION_ID') ? constant('WACK_CF_INV_DISTRIBUTION_ID') : 'not defined';
            Utility::infoLog('[CloudFront Invalidation Dry Run] Distribution ID: ' . $distributionId . ', Paths: ' . json_encode($paths));
        } else {
            $this->executeInvalidation($paths, $callerReferenceID);
        }
    }

    /**
     * Determine whether invalidation should be skipped for this post.
     */
    private function shouldSkip(WP_Post $post, bool $isRestHook): bool
    {
        // Prevent excessive invalidations by not clearing cache for unpublished posts
        if (
            $post->post_status === 'auto-draft'
            || $post->post_status === 'draft'
            || $post->post_status === 'inherit'
            || $post->post_status === 'pending') {
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
        // Check if the CloudFront distribution ID constant is defined and not empty.
        if (!defined('WACK_CF_INV_DISTRIBUTION_ID') || empty(constant('WACK_CF_INV_DISTRIBUTION_ID'))) {
            Utility::errorLog('CloudFront Invalidation Error: Distribution ID not defined.');
            return;
        }

        $client = new CloudFrontClient([
            'version' => 'latest',
            'region' => 'us-east-1',
        ]);

        try {
            $client->createInvalidation([
                'DistributionId' => constant('WACK_CF_INV_DISTRIBUTION_ID'),
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
     *
     * @param string $path_with_placeholder
     * @param WP_Post $post
     *
     * @return string
     */
    private function replacePlaceholder(string $path_with_placeholder, WP_Post $post): string
    {
        $temp_string = str_replace('%id%', $post->ID, $path_with_placeholder);

        if (empty($post->post_name)) {
            return str_replace('%slug%', $post->ID, $temp_string);
        } else {
            return str_replace('%slug%', $post->post_name, $temp_string);
        }
    }
}
