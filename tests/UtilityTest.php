<?php

namespace WackCloudfrontInvalidationTest;

use WP_Mock;
use WackCloudfrontInvalidation\Utility;

/**
 * Tests for the Utility class
 */
final class UtilityTest extends WP_Mock\Tools\TestCase
{
    //==========================================================================
    // getPostTypes
    //==========================================================================
    // phpcs:ignore
    public function test_getPostTypes_filters_default_post_types(): void
    {
        // Default post types such as page / attachment / revision / nav_menu_item are excluded
        WP_Mock::userFunction('get_post_types')
            ->once()
            ->with(['public' => true], 'objects')
            ->andReturn([
                'page'         => (object) ['name' => 'page', 'label' => 'Pages'],
                'attachment'   => (object) ['name' => 'attachment', 'label' => 'Media'],
                'revision'     => (object) ['name' => 'revision', 'label' => 'Revisions'],
                'nav_menu_item' => (object) ['name' => 'nav_menu_item', 'label' => 'Navigation Menu Items'],
                'wp_template'  => (object) ['name' => 'wp_template', 'label' => 'Templates'],
                'wp_template_part' => (object) ['name' => 'wp_template_part', 'label' => 'Template Parts'],
            ]);

        $result = Utility::getPostTypes();

        $this->assertEmpty($result, 'All default post types are excluded');
    }

    // phpcs:ignore
    public function test_getPostTypes_keeps_custom_post_types(): void
    {
        // Custom post types remain without being filtered out
        WP_Mock::userFunction('get_post_types')
            ->once()
            ->with(['public' => true], 'objects')
            ->andReturn([
                'page' => (object) ['name' => 'page', 'label' => 'Pages'],
                'news' => (object) ['name' => 'news', 'label' => 'News'],
                'book' => (object) ['name' => 'book', 'label' => 'Books'],
            ]);

        $result = Utility::getPostTypes();
        $names = array_map(fn($pt) => $pt->name, $result);

        $this->assertNotContains('page', $names, 'page is excluded because it is a default post type');
        $this->assertContains('news', $names, 'Custom post type news remains');
        $this->assertContains('book', $names, 'Custom post type book remains');
    }

    // phpcs:ignore
    public function test_getPostTypes_keeps_post_type(): void
    {
        // The 'post' post type is not in the default exclusion list and remains
        WP_Mock::userFunction('get_post_types')
            ->once()
            ->with(['public' => true], 'objects')
            ->andReturn([
                'post' => (object) ['name' => 'post', 'label' => 'Posts'],
                'page' => (object) ['name' => 'page', 'label' => 'Pages'],
            ]);

        $result = Utility::getPostTypes();
        $names = array_map(fn($pt) => $pt->name, $result);

        $this->assertContains('post', $names, "'post' post type remains because it is not filtered out");
        $this->assertNotContains('page', $names, "'page' is excluded");
    }

    // phpcs:ignore
    public function test_getPostTypes_returns_empty_when_no_post_types(): void
    {
        // Returns an empty array when no post types are retrieved
        WP_Mock::userFunction('get_post_types')
            ->once()
            ->with(['public' => true], 'objects')
            ->andReturn([]);

        $result = Utility::getPostTypes();
        $this->assertEmpty($result);
    }

    //==========================================================================
    // infoLog
    //==========================================================================
    // phpcs:ignore
    public function test_infoLog_does_not_throw(): void
    {
        // Falls back to error_log() when the logger() function does not exist
        // Ensures it runs without throwing an exception
        $this->expectNotToPerformAssertions();
        Utility::infoLog('Test info message');
    }

    //==========================================================================
    // errorLog
    //==========================================================================
    // phpcs:ignore
    public function test_errorLog_does_not_throw(): void
    {
        // Falls back to error_log() when the logger() function does not exist
        // Ensures it runs without throwing an exception
        $this->expectNotToPerformAssertions();
        Utility::errorLog('Test error message');
    }
}
