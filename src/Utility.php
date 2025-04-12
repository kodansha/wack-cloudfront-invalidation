<?php

namespace WackCloudfrontInvalidation;

/**
 * Class Utility
 *
 * @package WackCloudfrontInvalidation
 */
final class Utility
{
    /**
     * Retrieves all registered post types (except default post types).
     *
     * @return array An array of registered post type objects.
     */
    public static function getPostTypes(): array
    {
        $post_types = get_post_types(['public' => true], 'objects');

        $default_post_types = [
            // 'post',
            'page',
            'attachment',
            'revision',
            'nav_menu_item',
            'wp_template',
            'wp_template_part',
        ];

        return array_filter(
            $post_types,
            fn($post_type) => !in_array($post_type->name, $default_post_types),
        );
    }

    /**
     * Logs informational messages.
     *
     * @param string $message The message to be logged
     * @return void
     */
    public static function infoLog(string $message): void
    {
        if (function_exists('logger')) {
            logger()->info($message);
        } else {
            error_log($message);
        }
    }

    /**
     * Logs an error message.
     *
     * @param string $message The error message to be logged.
     * @return void
     */
    public static function errorLog(string $message): void
    {
        if (function_exists('logger')) {
            logger()->error($message);
        } else {
            error_log($message);
        }
    }

}
