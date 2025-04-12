# WACK CloudFront Invalidation

**WACK CloudFront Invalidation** is a WordPress plugin that helps you to run
invalidation on CloudFront after a post is published or updated.

It is designed to work with the WACK Stack, but can be used with any WordPress
installation that uses CloudFront as a CDN.

## How it works

This plugin listens to the `save_post` action and sends an invalidation request
to CloudFront when a post is published or updated.

## Installation

- Requires PHP 8.1 or later
- Requires WordPress 6.7 or later
- Requires Composer

### Using Composer

```bash
composer require kodansha/wack-cloudfront-invalidation
```

> [!NOTE]
> This plugin is not available on the WordPress.org plugin repository.
> The only installation method currently available is through Composer.

## Configuration

### AWS credentials

WACK CloudFront Invalidation uses AWS SDK for PHP to send invalidation requests
to CloudFront, so you need to configure AWS credentials. Please refer to the
[AWS SDK for PHP documentation](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html)
for more information on how to set up credentials.

### CloudFront Distribution ID

Specify the CloudFront Distribution ID to be invalidated using the
`WACK_CF_INV_DISTRIBUTION_ID` constant.

Add the following to your `wp-config.php` file or somewhere similar:

```php
define('WACK_CF_INV_DISTRIBUTION_ID', 'YOUR_CLOUDFRONT_DISTRIBUTION_ID');
```

> [!NOTE]
> Currently, only the CloudFront Distribution ID specified by the
> `WACK_CF_INV_DISTRIBUTION_ID` constant will be targeted, and it's not possible
> to specify more than one CloudFront Distribution.

### Invalidation Path Settings

After installing the WACK CloudFront Invalidation plugin, a settings page is
added to the WordPress admin menu under **WACK Stack** > **WACK CF Inv**.

In this settings page, you can configure CloudFront invalidation paths for
each post type.

> [!IMPORTANT]
> To specify multiple paths, list them separated by line breaks.
> Empty lines will be ignored.

## Tips

### `WACK_CF_INV_DRY_RUN` constant

If the `WACK_CF_INV_DRY_RUN` constant is defined and set to `true`, the actual
CloudFront invalidation execution will be skipped, and only log output will
be performed.

You can use this for testing behavior in development environments.

### WACK Log integration

If you have the [WACK Log plugin](https://packagist.org/packages/kodansha/wack-log)
installed, various logs from WACK CloudFront Invalidation will be output to
standard output using WACK Log.
