<?php

namespace WackCloudfrontInvalidationTest;

use WP_Mock;
use WackCloudfrontInvalidation\PluginSettings;

/**
 * Tests for the PluginSettings class
 *
 * Verifies behavior when the constants (WACK_CF_INV_DISTRIBUTION_ID / WACK_CF_INV_DRY_RUN)
 * are not defined. Cases with the constants set are separated into PluginSettingsConstantsTest.
 * When the constants are not defined, Constants::distributionIdConstant() / dryRunConstant()
 * naturally return null, so no mocking is needed.
 */
final class PluginSettingsTest extends WP_Mock\Tools\TestCase
{
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
    // getDistributionIdFromConstant (constant not defined)
    //==========================================================================
    // phpcs:ignore
    public function test_getDistributionIdFromConstant_not_defined(): void
    {
        // Returns null when WACK_CF_INV_DISTRIBUTION_ID is not defined
        $result = PluginSettings::getDistributionIdFromConstant();
        $this->assertNull($result);
    }

    //==========================================================================
    // getDistributionId (constant not set = database fallback)
    //==========================================================================
    // phpcs:ignore
    public function test_getDistributionId_from_database_when_no_constant(): void
    {
        // Returns the database value when the constant is not set
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn([
                'distribution_id' => 'EDATABASE456',
            ]);

        $result = PluginSettings::getDistributionId();
        $this->assertSame('EDATABASE456', $result);
    }

    // phpcs:ignore
    public function test_getDistributionId_null_when_not_configured(): void
    {
        // Returns null when neither the constant nor the database value is set
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn(false);

        $result = PluginSettings::getDistributionId();
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
    // getDryRunFromConstant (constant not defined)
    //==========================================================================
    // phpcs:ignore
    public function test_getDryRunFromConstant_not_defined(): void
    {
        // Returns null when WACK_CF_INV_DRY_RUN is not defined
        $result = PluginSettings::getDryRunFromConstant();
        $this->assertNull($result);
    }

    //==========================================================================
    // getDryRun (constant not set = database fallback)
    //==========================================================================
    // phpcs:ignore
    public function test_getDryRun_from_database_when_no_constant(): void
    {
        // Returns the database value when the constant is not set
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn([
                'dry_run' => true,
            ]);

        $result = PluginSettings::getDryRun();
        $this->assertTrue($result);
    }

    // phpcs:ignore
    public function test_getDryRun_false_by_default(): void
    {
        // Returns false when neither the constant nor the database value is set
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn(false);

        $result = PluginSettings::getDryRun();
        $this->assertFalse($result);
    }

    //==========================================================================
    // distributionId / dryRun instance methods (constants not set)
    //==========================================================================
    // phpcs:ignore
    public function test_distributionId_returns_value_from_database(): void
    {
        // The constructor calls get_option multiple times (database fallback runs because the constants are not set)
        WP_Mock::userFunction('get_option')
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn([
                'distribution_id' => 'EINSTANCE789',
            ]);

        $instance = PluginSettings::get();
        $this->assertSame('EINSTANCE789', $instance->distributionId());
    }

    // phpcs:ignore
    public function test_distributionId_returns_null_when_not_set(): void
    {
        WP_Mock::userFunction('get_option')
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn(false);

        $instance = PluginSettings::get();
        $this->assertNull($instance->distributionId());
    }

    // phpcs:ignore
    public function test_dryRun_returns_true_from_database(): void
    {
        WP_Mock::userFunction('get_option')
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn([
                'dry_run' => true,
            ]);

        $instance = PluginSettings::get();
        $this->assertTrue($instance->dryRun());
    }

    // phpcs:ignore
    public function test_dryRun_returns_false_by_default(): void
    {
        WP_Mock::userFunction('get_option')
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn(false);

        $instance = PluginSettings::get();
        $this->assertFalse($instance->dryRun());
    }
}
