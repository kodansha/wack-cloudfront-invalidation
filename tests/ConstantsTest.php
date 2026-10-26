<?php

namespace WackCloudfrontInvalidationTest;

use WackCloudfrontInvalidation\Constants;

/**
 * Tests for the Constants class
 *
 * PHP constants cannot be changed or removed once defined, so all tests are
 * run in separate processes to avoid affecting the main process's class registry.
 * This also ensures that the Mockery overload used in the subsequent
 * PluginSettingsTest works correctly.
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
        // Returns null when WACK_CF_INV_DISTRIBUTION_ID is not defined
        $result = Constants::distributionIdConstant();
        $this->assertNull($result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_distributionIdConstant_defined(): void
    {
        // Returns the value when WACK_CF_INV_DISTRIBUTION_ID is defined
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
        // Returns null when WACK_CF_INV_DRY_RUN is not defined
        $result = Constants::dryRunConstant();
        $this->assertNull($result);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_dryRunConstant_defined_true(): void
    {
        // Returns true when WACK_CF_INV_DRY_RUN is true
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
        // Returns false when WACK_CF_INV_DRY_RUN is false
        define('WACK_CF_INV_DRY_RUN', false);

        $result = Constants::dryRunConstant();
        $this->assertFalse($result);
    }
}
