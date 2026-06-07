=== Comment Management ===
Contributors: fifoqueue
Tags: comments, moderation, wpdiscuz, frontend, wp-cli
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Securely edit and moderate WordPress comments from the front end.

== Description ==

Comment Management adds administrator-only controls to comments rendered on the front end. It uses WordPress core comment APIs and the standard `comment_text` filter, making it compatible with the default comment system and integrations such as wpDiscuz that retain the core filter.

Supported actions:

* Edit comment content.
* Move a comment to the Trash.
* Mark a comment as spam.
* Unapprove a comment.
* Permanently delete a comment after confirmation.

Every browser request requires an authenticated session, a valid nonce, and the `edit_comment` capability for the specific comment. Edited content is restricted to WordPress's allowed post HTML.

WP-CLI examples:

`wp comment-management spam 42`

`wp comment-management edit 42 --content="Updated comment"`

`wp comment-management delete 42 --yes`

Updates are delivered from GitHub Releases through Plugin Update Checker.

== Installation ==

1. Install the release ZIP in the WordPress Plugins screen.
2. Activate Comment Management.
3. Sign in as an administrator or another user who can moderate comments.
4. Open a front-end page that displays comments.

== Frequently Asked Questions ==

= Does this replace WordPress comments? =

No. It only adds management controls to comments rendered by WordPress.

= Does it work with wpDiscuz? =

The plugin is compatible with wpDiscuz rendering paths that apply WordPress's standard `comment_text` filter. Controls use delegated browser events, so dynamically rendered comment lists remain functional.

= Who can see the controls? =

Only logged-in users who can edit the specific comment. Assets are limited to users with the `moderate_comments` capability.

== Changelog ==

= 1.0.0 =

* Initial release.
* Added secure front-end edit, Trash, spam, unapprove, and permanent delete actions.
* Added WP-CLI support.
* Added GitHub release updates through Plugin Update Checker 5.7.
