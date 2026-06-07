# Comment Management

A WordPress plugin for securely managing comments from the front end.

## Requirements

- WordPress 6.5 or newer
- PHP 8.3 or newer
- Composer 2

## Development

```bash
composer install
composer test
composer lint
```

The distributable ZIP is built by `.github/workflows/release.yml`. Create a tag
that matches the plugin version, such as `v1.0.0`, to publish a GitHub Release.

## WP-CLI

```bash
wp comment-management trash 42
wp comment-management spam 42
wp comment-management unapprove 42
wp comment-management edit 42 --content="Updated comment"
wp comment-management delete 42 --yes
```
