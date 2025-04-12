<?php

/**
 * Plugin Name: WACK CloudFront Invalidation
 * Plugin URI: https://github.com/kodansha/wack-cloudfront-invalidation
 * Description: Invalidate CloudFront caches when a post is published or updated.
 * Version: 0.0.1
 * Author: KODANSHAtech LLC.
 * Author URI: https://github.com/kodansha
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

// Don't do anything if called directly.
if (!defined('ABSPATH') || !defined('WPINC')) {
    die();
}

// Autoloader
if (is_readable(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Initialize plugin
 */
function wack_cloudfront_invalidation_init()
{
    (new WackCloudfrontInvalidation\AdminMenu())->init();
    (new WackCloudfrontInvalidation\CloudFrontInvalidationHook())->init();
}

add_action('plugins_loaded', 'wack_cloudfront_invalidation_init', PHP_INT_MAX - 1);
