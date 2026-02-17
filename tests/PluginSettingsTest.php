<?php

namespace WackCloudfrontInvalidationTest;

use WP_Mock;
use Mockery;
use WackCloudfrontInvalidation\PluginSettings;

final class PluginSettingsTest extends WP_Mock\Tools\TestCase
{
    /**
    * Mock static methods on the Constants class.
     */
    private function mockConstants(?string $distribution_id = null, ?bool $dry_run = null): void
    {
        $mock = Mockery::mock('overload:' . \WackCloudfrontInvalidation\Constants::class)->makePartial();
        $mock->shouldReceive('distributionIdConstant')->andReturn($distribution_id);
        $mock->shouldReceive('dryRunConstant')->andReturn($dry_run);
    }

    //==========================================================================
    // invalidationPathsFor
    //==========================================================================
    // phpcs:ignore
    public function test_invalidationPathsFor_found(): void
    {
        $this->mockConstants();

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
        $this->mockConstants();

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
        $this->mockConstants();

        WP_Mock::userFunction('get_option')
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn(false);

        $instance = PluginSettings::get();
        $this->assertSame([], $instance->invalidationPathsFor('post'));
    }

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
    // getDistributionIdFromConstant
    //==========================================================================
    // phpcs:ignore
    public function test_getDistributionIdFromConstant_settings_found(): void
    {
        $this->mockConstants(distribution_id: 'E1234567890ABC');

        $result = PluginSettings::getDistributionIdFromConstant();
        $this->assertSame('E1234567890ABC', $result);
    }

    // phpcs:ignore
    public function test_getDistributionIdFromConstant_settings_not_found(): void
    {
        $this->mockConstants(distribution_id: null);

        $result = PluginSettings::getDistributionIdFromConstant();
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

    //==========================================================================
    // getDryRunFromConstant
    //==========================================================================
    // phpcs:ignore
    public function test_getDryRunFromConstant_settings_found_true(): void
    {
        $this->mockConstants(dry_run: true);

        $result = PluginSettings::getDryRunFromConstant();
        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_getDryRunFromConstant_settings_found_false(): void
    {
        $this->mockConstants(dry_run: false);

        $result = PluginSettings::getDryRunFromConstant();
        $this->assertFalse($result);
    }

    // phpcs:ignore
    public function test_getDryRunFromConstant_settings_not_found(): void
    {
        $this->mockConstants(dry_run: null);

        $result = PluginSettings::getDryRunFromConstant();
        $this->assertNull($result);
    }
}
