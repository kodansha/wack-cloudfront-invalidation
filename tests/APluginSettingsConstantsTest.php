<?php

namespace WackCloudfrontInvalidationTest;

use WP_Mock;
use Mockery;
use WackCloudfrontInvalidation\PluginSettings;

final class PluginSettingsConstantsTest extends WP_Mock\Tools\TestCase
{
    //==========================================================================
    // invalidationPathsFor
    //==========================================================================
    // phpcs:ignore
    public function test_invalidationPathsFor_found(): void
    {
        $mock = Mockery::mock('overload:' . \WackCloudfrontInvalidation\Constants::class)->makePartial();
        $mock->shouldReceive('distributionIdConstant')->andReturn(null);
        $mock->shouldReceive('dryRunConstant')->andReturn(null);

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
        $mock = Mockery::mock('overload:' . \WackCloudfrontInvalidation\Constants::class)->makePartial();
        $mock->shouldReceive('distributionIdConstant')->andReturn(null);
        $mock->shouldReceive('dryRunConstant')->andReturn(null);

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
        $mock = Mockery::mock('overload:' . \WackCloudfrontInvalidation\Constants::class)->makePartial();
        $mock->shouldReceive('distributionIdConstant')->andReturn(null);
        $mock->shouldReceive('dryRunConstant')->andReturn(null);

        WP_Mock::userFunction('get_option')
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn(false);

        $instance = PluginSettings::get();
        $this->assertSame([], $instance->invalidationPathsFor('post'));
    }

    //==========================================================================
    // getDistributionIdFromConstant
    //==========================================================================
    // phpcs:ignore
    public function test_getDistributionIdFromConstant_settings_found(): void
    {
        $mock = Mockery::mock('overload:' . \WackCloudfrontInvalidation\Constants::class)->makePartial();
        $mock->shouldReceive('distributionIdConstant')
            ->andReturn('E1234567890ABC');
        $result = PluginSettings::getDistributionIdFromConstant();
        $this->assertSame('E1234567890ABC', $result);
    }

    // phpcs:ignore
    public function test_getDistributionIdFromConstant_settings_not_found(): void
    {
        $mock = Mockery::mock('overload:' . \WackCloudfrontInvalidation\Constants::class)->makePartial();
        $mock->shouldReceive('distributionIdConstant')
            ->andReturn(null);
        $result = PluginSettings::getDistributionIdFromConstant();
        $this->assertNull($result);
    }

    //==========================================================================
    // getDryRunFromConstant
    //==========================================================================
    // phpcs:ignore
    public function test_getDryRunFromConstant_settings_found_true(): void
    {
        $mock = Mockery::mock('overload:' . \WackCloudfrontInvalidation\Constants::class)->makePartial();
        $mock->shouldReceive('dryRunConstant')
            ->andReturn(true);
        $result = PluginSettings::getDryRunFromConstant();
        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_getDryRunFromConstant_settings_found_false(): void
    {
        $mock = Mockery::mock('overload:' . \WackCloudfrontInvalidation\Constants::class)->makePartial();
        $mock->shouldReceive('dryRunConstant')
            ->andReturn(false);
        $result = PluginSettings::getDryRunFromConstant();
        $this->assertFalse($result);
    }

    // phpcs:ignore
    public function test_getDryRunFromConstant_settings_not_found(): void
    {
        $mock = Mockery::mock('overload:' . \WackCloudfrontInvalidation\Constants::class)->makePartial();
        $mock->shouldReceive('dryRunConstant')
            ->andReturn(null);
        $result = PluginSettings::getDryRunFromConstant();
        $this->assertNull($result);
    }
}
