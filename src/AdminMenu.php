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
        // General Settings Section
        //----------------------------------------------------------------------
        add_settings_section(
            'wack-cloudfront-invalidation-settings-general-section',
            'General Settings',
            function () {
                echo '<p>Configure the basic settings for CloudFront invalidation.</p>';
            },
            'wack-cloudfront-invalidation-settings-page',
        );

        // Distribution ID
        add_settings_field(
            'distribution_id',
            'Distribution ID',
            function () {
                $settings_option = get_option('wack_cloudfront_invalidation_settings');
                $distribution_id = $settings_option['distribution_id'] ?? '';
                $is_disabled_by_constant = !is_null(\WackCloudfrontInvalidation\PluginSettings::getDistributionIdFromConstant());
                $disabled_attr = $is_disabled_by_constant ? 'disabled' : '';
                ?>
                <input type="text" style="width: 90%;" name="wack_cloudfront_invalidation_settings[distribution_id]" value="<?php echo esc_attr($distribution_id); ?>" <?php echo $disabled_attr; ?> />
                <?php if ($is_disabled_by_constant): ?>
                    <p class="description">This setting is configured by a constant and cannot be changed.</p>
                <?php else: ?>
                    <p class="description">Enter the CloudFront Distribution ID.</p>
                <?php endif; ?>
                <?php
            },
            'wack-cloudfront-invalidation-settings-page',
            'wack-cloudfront-invalidation-settings-general-section',
        );

        // Dry Run
        add_settings_field(
            'dry_run',
            'Dry Run',
            function () {
                $settings_option = get_option('wack_cloudfront_invalidation_settings');
                $dry_run = $settings_option['dry_run'] ?? false;
                $is_disabled_by_constant = !is_null(\WackCloudfrontInvalidation\PluginSettings::getDryRunFromConstant());
                $disabled_attr = $is_disabled_by_constant ? 'disabled' : '';
                $checked_attr = $dry_run ? 'checked' : '';
                ?>
                <label>
                    <input type="checkbox" name="wack_cloudfront_invalidation_settings[dry_run]" value="1" <?php echo $checked_attr; ?> <?php echo $disabled_attr; ?> />
                    Enable Dry Run mode
                </label>
                <?php if ($is_disabled_by_constant): ?>
                    <p class="description">This setting is configured by a constant and cannot be changed.</p>
                <?php else: ?>
                    <p class="description">When enabled, CloudFront Invalidation will not be executed and only logs will be output.</p>
                <?php endif; ?>
                <?php
            },
            'wack-cloudfront-invalidation-settings-page',
            'wack-cloudfront-invalidation-settings-general-section',
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
    public function optionsSanitizeCallback($options): ?array
    {
        $sanitized_options = [];
        $errors = [];

        // Distribution ID
        if (isset($options['distribution_id'])) {
            $sanitized_options['distribution_id'] = sanitize_text_field($options['distribution_id']);
        }

        // Dry Run
        // If configured by constant, retain the database value
        if (is_null(\WackCloudfrontInvalidation\PluginSettings::getDryRunFromConstant())) {
            $sanitized_options['dry_run'] = isset($options['dry_run']) && $options['dry_run'] === '1';
        } else {
            // If configured by constant, retain the existing value
            $current_option_value = get_option('wack_cloudfront_invalidation_settings');
            $sanitized_options['dry_run'] = $current_option_value['dry_run'] ?? false;
        }

        // Invalidation Paths
        if (isset($options['invalidation_paths'])) {
            foreach ($options['invalidation_paths'] as $post_type => $paths_string) {
                // During first-time registration (when there's no record in wp_options yet), sanitize_callback gets called multiple times.
                // However, if the values have already been converted to a $paths_string array in the first sanitize_callback call, use them as they are.
                if (is_array($paths_string)) {
                    $sanitized_options['invalidation_paths'][$post_type] = $paths_string;
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
