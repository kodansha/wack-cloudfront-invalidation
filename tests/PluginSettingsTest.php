<?php

namespace WackCloudfrontInvalidationTest;

use WP_Mock;
use WackCloudfrontInvalidation\PluginSettings;

/**
 * PluginSettings クラスのテスト
 *
 * 定数 (WACK_CF_INV_DISTRIBUTION_ID / WACK_CF_INV_DRY_RUN) が未定義の場合の
 * 動作を検証する。定数が設定されているケースは PluginSettingsConstantsTest に分離している。
 * 定数が未定義のとき Constants::distributionIdConstant() / dryRunConstant() は
 * 自然に null を返すため、モックは不要。
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
    // getDistributionIdFromConstant (定数未定義ケース)
    //==========================================================================
    // phpcs:ignore
    public function test_getDistributionIdFromConstant_not_defined(): void
    {
        // WACK_CF_INV_DISTRIBUTION_ID が未定義のとき null を返す
        $result = PluginSettings::getDistributionIdFromConstant();
        $this->assertNull($result);
    }

    //==========================================================================
    // getDistributionId (定数未設定 = DB フォールバック)
    //==========================================================================
    // phpcs:ignore
    public function test_getDistributionId_from_database_when_no_constant(): void
    {
        // 定数が未設定のとき、データベースの値を返す
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
        // 定数もデータベースも未設定のとき null を返す
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
    // getDryRunFromConstant (定数未定義ケース)
    //==========================================================================
    // phpcs:ignore
    public function test_getDryRunFromConstant_not_defined(): void
    {
        // WACK_CF_INV_DRY_RUN が未定義のとき null を返す
        $result = PluginSettings::getDryRunFromConstant();
        $this->assertNull($result);
    }

    //==========================================================================
    // getDryRun (定数未設定 = DB フォールバック)
    //==========================================================================
    // phpcs:ignore
    public function test_getDryRun_from_database_when_no_constant(): void
    {
        // 定数が未設定のとき、データベースの値を返す
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
        // 定数もデータベースも未設定のとき false を返す
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('wack_cloudfront_invalidation_settings')
            ->andReturn(false);

        $result = PluginSettings::getDryRun();
        $this->assertFalse($result);
    }

    //==========================================================================
    // distributionId / dryRun インスタンスメソッド (定数未設定)
    //==========================================================================
    // phpcs:ignore
    public function test_distributionId_returns_value_from_database(): void
    {
        // コンストラクタは get_option を複数回呼ぶ (定数未設定のため DB フォールバックが走る)
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
