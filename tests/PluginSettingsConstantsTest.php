<?php

namespace WackCloudfrontInvalidationTest;

use WackCloudfrontInvalidation\PluginSettings;

/**
 * PluginSettings の定数オーバーライド機能のテスト
 *
 * PHP 定数は一度定義すると変更できないため、すべてのテストを独立プロセスで実行する。
 * また、WP_Mock\Tools\TestCase は PHP 8.5 との互換性問題があるため、
 * このクラスは PHPUnit\Framework\TestCase を継承し、WP 関数呼び出しが
 * 発生しないコードパスのみを対象とする。
 */
final class PluginSettingsConstantsTest extends \PHPUnit\Framework\TestCase
{
    //==========================================================================
    // getDistributionIdFromConstant (定数定義済みケース)
    //==========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_getDistributionIdFromConstant_returns_value_when_defined(): void
    {
        // WACK_CF_INV_DISTRIBUTION_ID が定義されていれば、その値を返す
        define('WACK_CF_INV_DISTRIBUTION_ID', 'E1234567890ABC');

        $result = PluginSettings::getDistributionIdFromConstant();
        $this->assertSame('E1234567890ABC', $result);
    }

    //==========================================================================
    // getDryRunFromConstant (定数定義済みケース)
    //==========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_getDryRunFromConstant_returns_true_when_defined(): void
    {
        // WACK_CF_INV_DRY_RUN = true のとき true を返す
        define('WACK_CF_INV_DRY_RUN', true);

        $result = PluginSettings::getDryRunFromConstant();
        $this->assertTrue($result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_getDryRunFromConstant_returns_false_when_defined(): void
    {
        // WACK_CF_INV_DRY_RUN = false のとき false を返す
        define('WACK_CF_INV_DRY_RUN', false);

        $result = PluginSettings::getDryRunFromConstant();
        $this->assertFalse($result);
    }

    //==========================================================================
    // getDistributionId (定数優先ケース)
    //==========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_getDistributionId_constant_takes_priority_over_database(): void
    {
        // 定数が設定されていれば、データベースを参照せずにその値を返す
        define('WACK_CF_INV_DISTRIBUTION_ID', 'ECONSTANT123');

        $result = PluginSettings::getDistributionId();
        $this->assertSame('ECONSTANT123', $result);
    }

    //==========================================================================
    // getDryRun (定数優先ケース)
    //==========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_getDryRun_true_constant_takes_priority(): void
    {
        // WACK_CF_INV_DRY_RUN = true のとき、データベースを参照せずに true を返す
        define('WACK_CF_INV_DRY_RUN', true);

        $result = PluginSettings::getDryRun();
        $this->assertTrue($result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_getDryRun_false_constant_takes_priority(): void
    {
        // WACK_CF_INV_DRY_RUN = false のとき、データベースを参照せずに false を返す
        define('WACK_CF_INV_DRY_RUN', false);

        $result = PluginSettings::getDryRun();
        $this->assertFalse($result);
    }
}
