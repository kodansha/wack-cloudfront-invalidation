<?php

namespace WackCloudfrontInvalidationTest;

use WP_Mock;
use WP_Post;
use WackCloudfrontInvalidation\CloudFrontInvalidationHook;

/**
 * Tests for the CloudFrontInvalidationHook class
 *
 * Private methods such as replacePlaceholder and shouldSkip are tested
 * directly via ReflectionMethod.
 *
 * Tests that depend on the DOING_AUTOSAVE / REST_REQUEST constants are omitted
 * because combining WP_Mock\Tools\TestCase with @runInSeparateProcess
 * has compatibility issues with PHP 8.5.
 */
final class CloudFrontInvalidationHookTest extends WP_Mock\Tools\TestCase
{
    /**
     * Create a WP_Post stub
     */
    private function makePost(int $id, string $post_type, string $post_status, string $post_name = ''): WP_Post
    {
        $post = new WP_Post();
        $post->ID = $id;
        $post->post_type = $post_type;
        $post->post_status = $post_status;
        $post->post_name = $post_name;

        return $post;
    }

    /**
     * Helper to call a private method
     *
     * setAccessible() is not called because it is unnecessary (no-op) since PHP 8.1
     */
    private function callPrivate(object $object, string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);

        return $reflection->invoke($object, ...$args);
    }

    //==========================================================================
    // replacePlaceholder
    //==========================================================================
    // phpcs:ignore
    public function test_replacePlaceholder_id_placeholder(): void
    {
        // %id% is replaced with the post ID
        $post = $this->makePost(42, 'post', 'publish', 'my-slug');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'replacePlaceholder', '/articles/%id%', $post);

        $this->assertSame('/articles/42', $result);
    }

    // phpcs:ignore
    public function test_replacePlaceholder_slug_placeholder(): void
    {
        // %slug% is replaced with the post slug (post_name)
        $post = $this->makePost(42, 'post', 'publish', 'my-awesome-post');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'replacePlaceholder', '/articles/%slug%', $post);

        $this->assertSame('/articles/my-awesome-post', $result);
    }

    // phpcs:ignore
    public function test_replacePlaceholder_slug_fallback_to_id_when_post_name_empty(): void
    {
        // %slug% falls back to the post ID when post_name is empty
        $post = $this->makePost(42, 'post', 'publish', '');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'replacePlaceholder', '/articles/%slug%', $post);

        $this->assertSame('/articles/42', $result);
    }

    // phpcs:ignore
    public function test_replacePlaceholder_no_placeholder(): void
    {
        // Paths without placeholders are returned unchanged
        $post = $this->makePost(42, 'post', 'publish', 'my-slug');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'replacePlaceholder', '/static/path/*', $post);

        $this->assertSame('/static/path/*', $result);
    }

    // phpcs:ignore
    public function test_replacePlaceholder_both_placeholders(): void
    {
        // Both %id% and %slug% are replaced when included together
        $post = $this->makePost(42, 'post', 'publish', 'my-slug');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'replacePlaceholder', '/articles/%id%/%slug%', $post);

        $this->assertSame('/articles/42/my-slug', $result);
    }

    // phpcs:ignore
    public function test_replacePlaceholder_trashed_uses_desired_slug(): void
    {
        // Trashed posts use the original slug stored in _wp_desired_post_slug
        // (long slugs are truncated before __trashed is appended, so stripping the suffix cannot restore them)
        $post = $this->makePost(42, 'post', 'trash', 'long-slug-truncat__trashed');
        $hook = new CloudFrontInvalidationHook();

        WP_Mock::userFunction('get_post_meta')
            ->with(42, '_wp_desired_post_slug', true)
            ->andReturn('long-slug-truncated-by-wordpress');

        $result = $this->callPrivate($hook, 'replacePlaceholder', '/articles/%slug%', $post);

        $this->assertSame('/articles/long-slug-truncated-by-wordpress', $result);
    }

    // phpcs:ignore
    public function test_replacePlaceholder_trashed_without_desired_slug_keeps_post_name(): void
    {
        // Without _wp_desired_post_slug (e.g. the original slug already ended with __trashed), post_name is used as is
        $post = $this->makePost(42, 'post', 'trash', 'report__trashed');
        $hook = new CloudFrontInvalidationHook();

        WP_Mock::userFunction('get_post_meta')
            ->with(42, '_wp_desired_post_slug', true)
            ->andReturn('');

        $result = $this->callPrivate($hook, 'replacePlaceholder', '/articles/%slug%', $post);

        $this->assertSame('/articles/report__trashed', $result);
    }

    // phpcs:ignore
    public function test_replacePlaceholder_trashed_suffix_kept_when_not_trashed(): void
    {
        // Non-trashed posts use the slug as is
        $post = $this->makePost(42, 'post', 'publish', 'my-slug__trashed');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'replacePlaceholder', '/articles/%slug%', $post);

        $this->assertSame('/articles/my-slug__trashed', $result);
    }

    //==========================================================================
    // buildCallerReference
    //==========================================================================
    // phpcs:ignore
    public function test_buildCallerReference_same_within_10_seconds(): void
    {
        // Saves with the same status within the same 10 seconds share the reference (prevents duplicate execution)
        $post = $this->makePost(42, 'post', 'publish');
        $hook = new CloudFrontInvalidationHook();
        $base = mktime(12, 0, 30, 10, 26, 2026);

        $first = $this->callPrivate($hook, 'buildCallerReference', $post, $base);
        $second = $this->callPrivate($hook, 'buildCallerReference', $post, $base + 9);

        $this->assertSame('42-publish-20261026120030', $first);
        $this->assertSame($first, $second);
    }

    // phpcs:ignore
    public function test_buildCallerReference_differs_across_10_second_windows(): void
    {
        // Crossing a 10-second boundary produces a different reference
        $post = $this->makePost(42, 'post', 'publish');
        $hook = new CloudFrontInvalidationHook();
        $base = mktime(12, 0, 39, 10, 26, 2026);

        $first = $this->callPrivate($hook, 'buildCallerReference', $post, $base);
        $second = $this->callPrivate($hook, 'buildCallerReference', $post, $base + 1);

        $this->assertSame('42-publish-20261026120030', $first);
        $this->assertSame('42-publish-20261026120040', $second);
    }

    // phpcs:ignore
    public function test_buildCallerReference_differs_by_status(): void
    {
        // Publish and unpublish within the same 10 seconds produce different references
        $published = $this->makePost(42, 'post', 'publish');
        $unpublished = $this->makePost(42, 'post', 'draft');
        $hook = new CloudFrontInvalidationHook();
        $base = mktime(12, 0, 30, 10, 26, 2026);

        $first = $this->callPrivate($hook, 'buildCallerReference', $published, $base);
        $second = $this->callPrivate($hook, 'buildCallerReference', $unpublished, $base);

        $this->assertNotSame($first, $second);
    }

    //==========================================================================
    // shouldSkip - skipping by post status
    //==========================================================================
    // phpcs:ignore
    public function test_shouldSkip_auto_draft_status(): void
    {
        // auto-draft status is skipped
        $post = $this->makePost(1, 'post', 'auto-draft');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_draft_status(): void
    {
        // draft status is skipped
        $post = $this->makePost(1, 'post', 'draft');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_inherit_status(): void
    {
        // inherit status is skipped
        $post = $this->makePost(1, 'post', 'inherit');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_pending_status(): void
    {
        // pending status is skipped
        $post = $this->makePost(1, 'post', 'pending');
        $hook = new CloudFrontInvalidationHook();

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }

    //==========================================================================
    // shouldSkip - conditions for publish status
    //==========================================================================
    // phpcs:ignore
    public function test_shouldSkip_false_for_normal_published_post(): void
    {
        // A normal published post is not skipped
        $post = $this->makePost(1, 'post', 'publish', 'my-post');
        $hook = new CloudFrontInvalidationHook();

        WP_Mock::userFunction('wp_is_post_autosave')->with(1)->andReturn(false);
        WP_Mock::userFunction('wp_is_post_revision')->with(1)->andReturn(false);
        WP_Mock::userFunction('did_action')->with('rest_after_insert_post')->andReturn(0);

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertFalse($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_autosave_post(): void
    {
        // Skipped when wp_is_post_autosave returns a truthy value
        $post = $this->makePost(1, 'post', 'publish', 'my-post');
        $hook = new CloudFrontInvalidationHook();

        // Returns the parent post ID (truthy) for an autosave
        WP_Mock::userFunction('wp_is_post_autosave')->with(1)->andReturn(100);
        WP_Mock::userFunction('wp_is_post_revision')->with(1)->andReturn(false);
        WP_Mock::userFunction('did_action')->with('rest_after_insert_post')->andReturn(0);

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_revision_post(): void
    {
        // Skipped when wp_is_post_revision returns a truthy value
        $post = $this->makePost(1, 'post', 'publish', 'my-post');
        $hook = new CloudFrontInvalidationHook();

        WP_Mock::userFunction('wp_is_post_autosave')->with(1)->andReturn(false);
        // Returns the parent post ID (truthy) for a revision
        WP_Mock::userFunction('wp_is_post_revision')->with(1)->andReturn(100);
        WP_Mock::userFunction('did_action')->with('rest_after_insert_post')->andReturn(0);

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }

    //==========================================================================
    // shouldSkip - unpublishing (publish -> draft/pending)
    //==========================================================================
    // phpcs:ignore
    public function test_shouldSkip_false_when_unpublished_to_draft(): void
    {
        // Not skipped when changed from publish to draft, so that the cache is cleared
        $post = $this->makePost(1, 'post', 'draft', 'my-post');
        $hook = new CloudFrontInvalidationHook();
        $hook->rememberPreviousStatus('draft', 'publish', $post);

        WP_Mock::userFunction('wp_is_post_autosave')->with(1)->andReturn(false);
        WP_Mock::userFunction('wp_is_post_revision')->with(1)->andReturn(false);
        WP_Mock::userFunction('did_action')->with('rest_after_insert_post')->andReturn(0);

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertFalse($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_false_when_unpublished_to_pending(): void
    {
        // Not skipped when changed from publish to pending, so that the cache is cleared
        $post = $this->makePost(1, 'post', 'pending', 'my-post');
        $hook = new CloudFrontInvalidationHook();
        $hook->rememberPreviousStatus('pending', 'publish', $post);

        WP_Mock::userFunction('wp_is_post_autosave')->with(1)->andReturn(false);
        WP_Mock::userFunction('wp_is_post_revision')->with(1)->andReturn(false);
        WP_Mock::userFunction('did_action')->with('rest_after_insert_post')->andReturn(0);

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertFalse($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_draft_to_draft(): void
    {
        // Saving a draft as a draft is skipped as before
        $post = $this->makePost(1, 'post', 'draft', 'my-post');
        $hook = new CloudFrontInvalidationHook();
        $hook->rememberPreviousStatus('draft', 'draft', $post);

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_previous_status_is_per_post(): void
    {
        // Unpublishing another post has no effect
        $unpublished = $this->makePost(1, 'post', 'draft', 'unpublished');
        $other = $this->makePost(2, 'post', 'draft', 'other');
        $hook = new CloudFrontInvalidationHook();
        $hook->rememberPreviousStatus('draft', 'publish', $unpublished);

        $result = $this->callPrivate($hook, 'shouldSkip', $other, false);

        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_autosave_after_unpublish(): void
    {
        // Autosaves are skipped even when unpublishing
        $post = $this->makePost(1, 'post', 'draft', 'my-post');
        $hook = new CloudFrontInvalidationHook();
        $hook->rememberPreviousStatus('draft', 'publish', $post);

        WP_Mock::userFunction('wp_is_post_autosave')->with(1)->andReturn(100);
        WP_Mock::userFunction('wp_is_post_revision')->with(1)->andReturn(false);
        WP_Mock::userFunction('did_action')->with('rest_after_insert_post')->andReturn(0);

        $result = $this->callPrivate($hook, 'shouldSkip', $post, false);

        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_shouldSkip_false_when_nested_save_during_unpublish(): void
    {
        // Treated as unpublishing even if the same post is saved again in save_post during unpublishing,
        // causing a draft -> draft transition (with REST, invalidation is deferred until rest_after_insert)
        $post = $this->makePost(1, 'post', 'draft', 'my-post');
        $hook = new CloudFrontInvalidationHook();
        $hook->rememberPreviousStatus('draft', 'publish', $post);
        $hook->rememberPreviousStatus('draft', 'draft', $post);

        WP_Mock::userFunction('wp_is_post_autosave')->with(1)->andReturn(false);
        WP_Mock::userFunction('wp_is_post_revision')->with(1)->andReturn(false);
        WP_Mock::userFunction('did_action')->with('rest_after_insert_post')->andReturn(0);

        $result = $this->callPrivate($hook, 'shouldSkip', $post, true);

        $this->assertFalse($result);
    }

    // phpcs:ignore
    public function test_previous_status_cleared_after_invalidation(): void
    {
        // After the invalidation is processed, the recorded previous status is discarded and later draft saves are skipped
        $post = $this->makePost(1, 'post', 'draft', 'my-post');
        $hook = new CloudFrontInvalidationHook();
        $hook->rememberPreviousStatus('draft', 'publish', $post);

        WP_Mock::userFunction('wp_is_post_autosave')->with(1)->andReturn(false);
        WP_Mock::userFunction('wp_is_post_revision')->with(1)->andReturn(false);
        WP_Mock::userFunction('did_action')->with('rest_after_insert_post')->andReturn(0);
        // Leave the plugin settings unset so paths are empty and no actual AWS request is made
        WP_Mock::userFunction('get_option')->andReturn(false);

        $hook->cloudFrontInvalidationAfterRest($post, null, false);

        $result = $this->callPrivate($hook, 'shouldSkip', $post, true);

        $this->assertTrue($result);
    }
}
