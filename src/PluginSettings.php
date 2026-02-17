<?php

namespace WackCloudfrontInvalidation;

/**
 * Class PluginSettings
 *
 * @package WackCloudfrontInvalidation
 */
final class PluginSettings
{
    private static PluginSettings $instance;

    private array $invalidation_paths;
    private string | null $distribution_id;
    private bool $dry_run;

    final private function __construct()
    {
        $this->invalidation_paths = self::getInvalidationPaths();
        $this->distribution_id = self::getDistributionId();
        $this->dry_run = self::getDryRun();
    }

    /**
     * Retrieves the invalidation paths for a specific post type.
     *
     * @param string $post_type The post type to get invalidation paths for.
     * @return array An array of invalidation paths related to the given post type.
     */
    public function invalidationPathsFor(string $post_type): array
    {
        if (isset($this->invalidation_paths[$post_type])) {
            return $this->invalidation_paths[$post_type];
        } else {
            return [];
        }
    }

    /**
     * Get Distribution ID
     *
     * @return string|null Distribution ID
     */
    public function distributionId(): string | null
    {
        return $this->distribution_id;
    }

    /**
     * Get Dry Run flag
     *
     * @return bool Dry Run flag
     */
    public function dryRun(): bool
    {
        return $this->dry_run;
    }

    /**
     * Get singleton instance
     *
     * @return PluginSettings
     */
    public static function get(): PluginSettings
    {
        if (!isset(self::$instance) || (defined('PHPUNIT') && constant('PHPUNIT'))) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Retrieves the paths that should be invalidated in CloudFront.
     *
     * This method returns an array of paths that will be used for CloudFront invalidation.
     *
     * @return array An array of paths to be invalidated
     */
    public static function getInvalidationPaths(): array
    {
        $invalidation_paths = [];
        $settings_option = get_option('wack_cloudfront_invalidation_settings');

        if ($settings_option && isset($settings_option['invalidation_paths'])) {
            $invalidation_paths = $settings_option['invalidation_paths'];
        }

        return $invalidation_paths;
    }

    /**
     * Get Distribution ID from the constant or database plugin settings
     *
     * Always use the value from the constant if it exists.
     *
     * @return string|null Distribution ID
     */
    public static function getDistributionId(): string | null
    {
        $distribution_id = self::getDistributionIdFromConstant();

        if (is_null($distribution_id)) {
            $distribution_id = self::getDistributionIdFromDatabase();
        }

        return $distribution_id;
    }

    /**
     * Get Distribution ID from the WACK_CF_INV_DISTRIBUTION_ID constant
     *
     * @return string|null Distribution ID
     */
    public static function getDistributionIdFromConstant(): string | null
    {
        return Constants::distributionIdConstant();
    }

    /**
     * Get Distribution ID from database plugin settings
     *
     * @return string|null Distribution ID
     */
    public static function getDistributionIdFromDatabase(): string | null
    {
        $distribution_id = null;
        $settings_option = get_option('wack_cloudfront_invalidation_settings');

        if ($settings_option && isset($settings_option['distribution_id'])) {
            $distribution_id = $settings_option['distribution_id'];
        }

        return $distribution_id;
    }

    /**
     * Get Dry Run flag from the constant or database plugin settings
     *
     * Always use the value from the constant if it exists.
     *
     * @return bool Dry Run flag
     */
    public static function getDryRun(): bool
    {
        $dry_run = self::getDryRunFromConstant();

        if ($dry_run === true) {
            return true;
        } elseif ($dry_run === false) {
            return false;
        } else {
            return self::getDryRunFromDatabase();
        }
    }

    /**
     * Get Dry Run flag from the WACK_CF_INV_DRY_RUN constant
     *
     * @return bool|null Dry Run flag
     */
    public static function getDryRunFromConstant(): bool | null
    {
        return Constants::dryRunConstant();
    }

    /**
     * Get Dry Run flag from database plugin settings
     *
     * @return bool Dry Run flag
     */
    public static function getDryRunFromDatabase(): bool
    {
        $settings_option = get_option('wack_cloudfront_invalidation_settings');

        if ($settings_option && isset($settings_option['dry_run'])) {
            return $settings_option['dry_run'];
        }

        return false;
    }
}
