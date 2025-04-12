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

    final private function __construct()
    {
        $this->invalidation_paths = self::getInvalidationPaths();
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
}
