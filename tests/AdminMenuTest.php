<?php

namespace WackCloudfrontInvalidationTest;

use WP_Mock;
use WackCloudfrontInvalidation\AdminMenu;

/**
 * Tests for the AdminMenu class
 *
 * Verifies the sanitization performed by optionsSanitizeCallback.
 *
 * optionsSanitizeCallback internally calls PluginSettings::getDryRunFromConstant(),
 * but WACK_CF_INV_DRY_RUN is not defined in the test environment, so it naturally
 * returns null and no constant override occurs. No mocking is needed.
 */
final class AdminMenuTest extends WP_Mock\Tools\TestCase
{
    //==========================================================================
    // Sanitizing distribution_id
    //==========================================================================
    // phpcs:ignore
    public function test_optionsSanitizeCallback_sanitizes_distribution_id(): void
    {
        // distribution_id is sanitized with sanitize_text_field()
        WP_Mock::userFunction('sanitize_text_field')
            ->once()
            ->with('E1234567890ABC')
            ->andReturn('E1234567890ABC');

        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'distribution_id' => 'E1234567890ABC',
        ]);

        $this->assertSame('E1234567890ABC', $result['distribution_id']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_distribution_id_not_in_result_when_missing(): void
    {
        // distribution_id is not included in the result when the key is missing
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([]);

        $this->assertArrayNotHasKey('distribution_id', $result);
    }

    //==========================================================================
    // Sanitizing dry_run
    //==========================================================================
    // phpcs:ignore
    public function test_optionsSanitizeCallback_dry_run_true_when_1_submitted(): void
    {
        // dry_run becomes true when submitted as '1'
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'dry_run' => '1',
        ]);

        $this->assertTrue($result['dry_run']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_dry_run_false_when_not_submitted(): void
    {
        // dry_run becomes false when the key is not submitted (checkbox unchecked)
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([]);

        $this->assertFalse($result['dry_run']);
    }

    //==========================================================================
    // Sanitizing invalidation_paths
    //==========================================================================
    // phpcs:ignore
    public function test_optionsSanitizeCallback_splits_paths_by_newlines(): void
    {
        // Newline-separated text is converted into an array
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => "/posts/*\n/articles/*",
            ],
        ]);

        $this->assertSame(['/posts/*', '/articles/*'], $result['invalidation_paths']['post']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_splits_paths_by_crlf(): void
    {
        // CRLF line endings are also supported
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => "/posts/*\r\n/articles/*",
            ],
        ]);

        $this->assertSame(['/posts/*', '/articles/*'], $result['invalidation_paths']['post']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_adds_leading_slash(): void
    {
        // A leading slash is added automatically to paths without one
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => 'posts/*',
            ],
        ]);

        $this->assertSame(['/posts/*'], $result['invalidation_paths']['post']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_trims_whitespace(): void
    {
        // Leading and trailing whitespace around paths is trimmed
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => "  /posts/*  \n  /articles/*  ",
            ],
        ]);

        $this->assertSame(['/posts/*', '/articles/*'], $result['invalidation_paths']['post']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_skips_empty_lines(): void
    {
        // Empty lines are skipped
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => "/posts/*\n\n/articles/*\n",
            ],
        ]);

        $this->assertSame(['/posts/*', '/articles/*'], $result['invalidation_paths']['post']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_skips_post_type_with_empty_paths(): void
    {
        // Post types with empty paths are not included in the result
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => '',
            ],
        ]);

        $this->assertArrayNotHasKey('post', $result['invalidation_paths'] ?? []);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_passes_through_already_array_paths(): void
    {
        // Paths that are already an array (when sanitize_callback is called multiple times) are used as is
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => ['/posts/*', '/articles/*'],
            ],
        ]);

        $this->assertSame(['/posts/*', '/articles/*'], $result['invalidation_paths']['post']);
    }

    // phpcs:ignore
    public function test_optionsSanitizeCallback_handles_multiple_post_types(): void
    {
        // Paths for multiple post types can be sanitized at once
        $admin_menu = new AdminMenu();
        $result = $admin_menu->optionsSanitizeCallback([
            'invalidation_paths' => [
                'post' => '/posts/*',
                'news' => "/news/*\n/news/%slug%",
            ],
        ]);

        $this->assertSame(['/posts/*'], $result['invalidation_paths']['post']);
        $this->assertSame(['/news/*', '/news/%slug%'], $result['invalidation_paths']['news']);
    }
}
