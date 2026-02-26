<?php

namespace WackCloudfrontInvalidationTest;

use WackCloudfrontInvalidation\Constants;

/**
 * Constants クラスのテスト
 *
 * PHP 定数は一度定義すると変更・削除できないため、すべてのテストを
 * 独立プロセスで実行してメインプロセスのクラスレジストリへの影響を防ぐ。
 * これにより、後続の PluginSettingsTest で使用する Mockery の overload が
 * 正しく機能することも保証される。
 */
final class ConstantsTest extends \PHPUnit\Framework\TestCase
{
    //==========================================================================
    // distributionIdConstant
    //==========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_distributionIdConstant_not_defined(): void
    {
        // WACK_CF_INV_DISTRIBUTION_ID が未定義のとき null を返す
        $result = Constants::distributionIdConstant();
        $this->assertNull($result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_distributionIdConstant_defined(): void
    {
        // WACK_CF_INV_DISTRIBUTION_ID が定義されているとき、その値を返す
        define('WACK_CF_INV_DISTRIBUTION_ID', 'E1234567890ABC');

        $result = Constants::distributionIdConstant();
        $this->assertSame('E1234567890ABC', $result);
    }

    //==========================================================================
    // dryRunConstant
    //==========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_dryRunConstant_not_defined(): void
    {
        // WACK_CF_INV_DRY_RUN が未定義のとき null を返す
        $result = Constants::dryRunConstant();
        $this->assertNull($result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_dryRunConstant_defined_true(): void
    {
        // WACK_CF_INV_DRY_RUN が true のとき true を返す
        define('WACK_CF_INV_DRY_RUN', true);

        $result = Constants::dryRunConstant();
        $this->assertTrue($result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_dryRunConstant_defined_false(): void
    {
        // WACK_CF_INV_DRY_RUN が false のとき false を返す
        define('WACK_CF_INV_DRY_RUN', false);

        $result = Constants::dryRunConstant();
        $this->assertFalse($result);
    }
}
