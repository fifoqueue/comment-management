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

The distributable ZIP is built by `.github/workflows/release.yml` when a commit
is pushed to `master`. The workflow
creates a version tag such as `vX.Y.Z`, so the plugin version must be bumped
before the next release.

Update settings are available under **Settings > Comment Management**:

- GitHub repository, defaulting to `fifoqueue/comment-management`
- Stable branch, defaulting to `master`
- Optional GitHub API token for private repositories or higher API limits

Saved tokens are not rendered back into the settings form. Leaving the token
field blank keeps the current value; the remove checkbox deletes it.
