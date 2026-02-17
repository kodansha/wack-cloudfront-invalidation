<?php

namespace WackCloudfrontInvalidation;

/**
 * Class Constants
 *
 * @package WackCloudfrontInvalidation
 */
final class Constants
{
    /**
     * Get Distribution ID from the 'WACK_CF_INV_DISTRIBUTION_ID' constant
     *
     * @return string|null Distribution ID, or null if the constant is not defined
     */
    public static function distributionIdConstant(): string | null
    {
        if (defined('WACK_CF_INV_DISTRIBUTION_ID')) {
            return constant('WACK_CF_INV_DISTRIBUTION_ID');
        }

        return null;
    }

    /**
     * Get Dry Run flag from the 'WACK_CF_INV_DRY_RUN' constant
     *
     * @return bool|null Dry Run flag, or null if the constant is not defined
     */
    public static function dryRunConstant(): bool | null
    {
        if (defined('WACK_CF_INV_DRY_RUN')) {
            return constant('WACK_CF_INV_DRY_RUN');
        }

        return null;
    }
}
