<?php

namespace WackCloudfrontInvalidationTest;

use WP_Mock;
use WackCloudfrontInvalidation\PluginSettings;

final class PluginSettingsTest extends WP_Mock\Tools\TestCase
{
    //==========================================================================
    // getInvalidationPaths
    //==========================================================================
    // phpcs:ignore
    public function test_getInvalidationPaths_settings_found(): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn([
                'invalidation_paths' => [
                    'post' => [
                        '/posts/*',
                    ],
                ],
            ]);
        $result = PluginSettings::getInvalidationPaths();
        $this->assertSame(['post' => ['/posts/*']], $result);
    }

    // phpcs:ignore
    public function test_getInvalidationPaths_settings_not_found(): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn(false);
        $result = PluginSettings::getInvalidationPaths();
        $this->assertSame([], $result);
    }

    //==========================================================================
    // invalidationPathsFor
    //==========================================================================
    // phpcs:ignore
    public function test_invalidationPathsFor_found(): void
    {
        WP_Mock::userFunction('get_option')
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn([
                'invalidation_paths' => [
                    'post' => [
                        '/posts/*',
                        '/article/%id%',
                    ],
                ],
            ]);

        $instance = PluginSettings::get();
        $this->assertSame(['/posts/*', '/article/%id%'], $instance->invalidationPathsFor('post'));
    }

    // phpcs:ignore
    public function test_invalidationPathsFor_not_found_for_post_type(): void
    {
        WP_Mock::userFunction('get_option')
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn([
                'invalidation_paths' => [
                    'post' => [
                        '/posts/*',
                    ],
                ],
            ]);

        $instance = PluginSettings::get();
        $this->assertSame([], $instance->invalidationPathsFor('news'));
    }

    // phpcs:ignore
    public function test_invalidationPathsFor_completely_not_found(): void
    {
        WP_Mock::userFunction('get_option')
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn(false);

        $instance = PluginSettings::get();
        $this->assertSame([], $instance->invalidationPathsFor('post'));
    }
}
