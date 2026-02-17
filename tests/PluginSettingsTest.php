<?php

namespace WackCloudfrontInvalidationTest;

use WP_Mock;
use Mockery;
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
    // getDistributionIdFromDatabase
    //==========================================================================
    // phpcs:ignore
    public function test_getDistributionIdFromDatabase_settings_found(): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn([
                'distribution_id' => 'E1234567890ABC',
            ]);
        $result = PluginSettings::getDistributionIdFromDatabase();
        $this->assertSame('E1234567890ABC', $result);
    }

    // phpcs:ignore
    public function test_getDistributionIdFromDatabase_settings_found_but_invalid(): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn([]);
        $result = PluginSettings::getDistributionIdFromDatabase();
        $this->assertNull($result);
    }

    // phpcs:ignore
    public function test_getDistributionIdFromDatabase_settings_not_found(): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn(false);
        $result = PluginSettings::getDistributionIdFromDatabase();
        $this->assertNull($result);
    }

    //==========================================================================
    // getDryRunFromDatabase
    //==========================================================================
    // phpcs:ignore
    public function test_getDryRunFromDatabase_settings_found_true(): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn([
                'dry_run' => true,
            ]);
        $result = PluginSettings::getDryRunFromDatabase();
        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_getDryRunFromDatabase_settings_found_false(): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn([
                'dry_run' => false,
            ]);
        $result = PluginSettings::getDryRunFromDatabase();
        $this->assertFalse($result);
    }

    // phpcs:ignore
    public function test_getDryRunFromDatabase_settings_not_found(): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn(false);
        $result = PluginSettings::getDryRunFromDatabase();
        $this->assertFalse($result);
    }
}
