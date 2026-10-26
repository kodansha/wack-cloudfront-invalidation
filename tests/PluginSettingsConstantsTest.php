<?php

namespace WackCloudfrontInvalidationTest;

use WackCloudfrontInvalidation\PluginSettings;

/**
 * Tests for the constant override feature of PluginSettings
 *
 * PHP constants cannot be changed once defined, so all tests run in separate processes.
 * Also, because WP_Mock\Tools\TestCase has compatibility issues with PHP 8.5,
 * this class extends PHPUnit\Framework\TestCase and only covers code paths
 * that do not call WordPress functions.
 */
final class PluginSettingsConstantsTest extends \PHPUnit\Framework\TestCase
{
    //==========================================================================
    // getDistributionIdFromConstant (constant defined)
    //==========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_getDistributionIdFromConstant_returns_value_when_defined(): void
    {
        // Returns the value when WACK_CF_INV_DISTRIBUTION_ID is defined
        define('WACK_CF_INV_DISTRIBUTION_ID', 'E1234567890ABC');

        $result = PluginSettings::getDistributionIdFromConstant();
        $this->assertSame('E1234567890ABC', $result);
    }

    //==========================================================================
    // getDryRunFromConstant (constant defined)
    //==========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_getDryRunFromConstant_returns_true_when_defined(): void
    {
        // Returns true when WACK_CF_INV_DRY_RUN = true
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
        // Returns false when WACK_CF_INV_DRY_RUN = false
        define('WACK_CF_INV_DRY_RUN', false);

        $result = PluginSettings::getDryRunFromConstant();
        $this->assertFalse($result);
    }

    //==========================================================================
    // getDistributionId (constant takes precedence)
    //==========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_getDistributionId_constant_takes_priority_over_database(): void
    {
        // When the constant is set, its value is returned without reading the database
        define('WACK_CF_INV_DISTRIBUTION_ID', 'ECONSTANT123');

        $result = PluginSettings::getDistributionId();
        $this->assertSame('ECONSTANT123', $result);
    }

    //==========================================================================
    // getDryRun (constant takes precedence)
    //==========================================================================

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_getDryRun_true_constant_takes_priority(): void
    {
        // When WACK_CF_INV_DRY_RUN = true, true is returned without reading the database
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
        // When WACK_CF_INV_DRY_RUN = false, false is returned without reading the database
        define('WACK_CF_INV_DRY_RUN', false);

        $result = PluginSettings::getDryRun();
        $this->assertFalse($result);
    }
}
