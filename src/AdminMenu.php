<?php

namespace WackCloudfrontInvalidation;

/**
 * Class AdminMenu
 *
 * @package WackCloudfrontInvalidation
 */
final class AdminMenu
{
    /**
     * Initialize the settings page
     */
    public function init()
    {
        add_action('admin_menu', [$this, 'addAdminMenuPage']);
        add_action('admin_menu', [$this, 'addAdminSubMenuPage']);
    }

    /**
     * Add the settings page to the WordPress admin menu
     */
    public function addAdminMenuPage(): void
    {
        global $menu;

        // Check if the menu already exists
        $menu_slug = 'wack-stack-settings';
        $menu_exists = false;

        foreach ($menu as $item) {
            if ($item[2] == $menu_slug) {
                $menu_exists = true;
                break;
            }
        }

        // Add the menu if it doesn't exist
        if (!$menu_exists) {
            add_menu_page(
                'WACK Stack Settings',
                'WACK Stack',
                'manage_options',
                $menu_slug,
                function () {
                    ?>
                    <div class="wrap">
                        <h1>WACK Stack Settings</h1>
                        <p>The settings pages for plugins belonging to the WACK Stack ecosystem.</p>
                    </div>
                    <?php
                },
                'dashicons-superhero-alt',
            );
        }
    }

    public function addAdminSubMenuPage(): void
    {
        add_submenu_page(
            'wack-stack-settings',
            'WACK CloudFront Invalidation Settings',
            'WACK CF Inv',
            'manage_options',
            'wack-cloudfront-invalidation-settings',
            function () {
                ?>
                <div class="wrap">
                    <?php settings_errors(); ?>
                    <form action='options.php' method='post'>
                        <h1>WACK CloudFront Invalidation Settings</h1>
                        <?php
                        settings_fields('wack-cloudfront-invalidation-settings');
                do_settings_sections('wack-cloudfront-invalidation-settings-page');
                submit_button();
                ?>
                    </form>
                </div>
                <?php
            },
        );

        register_setting(
            'wack-cloudfront-invalidation-settings',
            'wack_cloudfront_invalidation_settings',
            ['sanitize_callback' => [$this, 'optionsSanitizeCallback']],
        );

        //----------------------------------------------------------------------
        // Invalidation Paths Section
        //----------------------------------------------------------------------
        add_settings_section(
            'wack-cloudfront-invalidation-settings-invalidation-paths-section',
            'Invalidation Paths',
            function () {
                echo '<p>Enter the paths you would like to be invalidated when each post type is updated.</p>';
                echo '<p>You can specify multiple paths by listing them on separate lines. Please enter one path per line.</p>';
                echo '<p>You can use two template strings in the paths:</p>';
                echo '<ul>';
                echo '<li><code>%id%</code> - Will be replaced with the actual post ID</li>';
                echo '<li><code>%slug%</code> - Will be replaced with the actual post slug</li>';
                echo '</ul>';
            },
            'wack-cloudfront-invalidation-settings-page',
        );

        // Generate Path Mappings Field
        $post_types = Utility::getPostTypes();

        foreach ($post_types as $post_type) {
            add_settings_field(
                'invalidation_paths_for_' . $post_type->name,
                $post_type->label,
                function () use ($post_type) {
                    $settings_option = get_option('wack_cloudfront_invalidation_settings');
                    $invalidation_paths = $settings_option['invalidation_paths'] ?? [];
                    $paths_for_post_type = $invalidation_paths[$post_type->name] ?? [];
                    $paths_for_post_type_string = implode("\n", $paths_for_post_type);
                    ?>
                    <textarea rows="5" style="width: 90%;" name="wack_cloudfront_invalidation_settings[invalidation_paths][<?php echo $post_type->name; ?>]"><?php echo esc_textarea($paths_for_post_type_string); ?></textarea>
                    <?php
                },
                'wack-cloudfront-invalidation-settings-page',
                'wack-cloudfront-invalidation-settings-invalidation-paths-section',
            );
        }

        // Remove the default WACK Stack settings page
        remove_submenu_page('wack-stack-settings', 'wack-stack-settings');
    }

    /**
     * Sanitize the options passed in
     */
    public function optionsSanitizeCallback($options): array | null
    {
        $sanitized_options = [];
        $errors = [];

        if (isset($options['invalidation_paths'])) {
            foreach ($options['invalidation_paths'] as $post_type => $paths_string) {
                // Skip if the input value is already converted to an array
                if (is_array($paths_string)) {
                    continue;
                }

                if (empty($paths_string)) {
                    continue;
                }

                $paths_array = preg_split('/\r\n|\r|\n/', trim($paths_string));

                foreach ($paths_array as $path) {
                    $path = trim($path);

                    if (empty($path)) {
                        continue;
                    }

                    // Add leading slash if not present
                    if ($path[0] !== '/') {
                        $path = '/' . $path;
                    }

                    // Add some validations if needed...

                    $sanitized_options['invalidation_paths'][$post_type][] = $path;
                }
            }
        }

        if (!empty($errors)) {
            $error_messages = '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
            add_settings_error(
                'wack_cloudfront_invalidation_settings',
                'wack_cloudfront_invalidation_settings_validation_errors',
                $error_messages,
            );

            // Restore the current option value
            $current_option_value = get_option('wack_cloudfront_invalidation_settings');
            if (empty($current_option_value)) {
                return null;
            }
            return $current_option_value;
        }

        return $sanitized_options;
    }
}
