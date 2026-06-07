=== Comment Management ===
Contributors: fifoqueue
Tags: comments, moderation, wpdiscuz, frontend
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.1.7
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
* Undo Trash, spam, and unapprove operations for a short period.
* Display the new moderation status immediately.
* Review and restore the latest 20 comment edits.

Every browser request requires an authenticated session, a valid nonce, and the `edit_comment` capability for the specific comment. Edited content is restricted to WordPress's allowed post HTML.

After moderation, the plugin reloads the comment area from the server so comment
counts, empty-state markup, separators, and forms stay in sync with the active
theme. wpDiscuz pages use a full refresh after the undo period to preserve its
internal JavaScript state.

Updates are delivered from GitHub Releases through Plugin Update Checker.

Update source settings are available under Settings > Comment Management. The
default repository is `fifoqueue/comment-management`, the default stable branch
is `master`, and an optional GitHub API token can be saved for private
repositories or higher API limits.

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

= 1.1.7 =

* Kept the comment action menu inside the viewport on mobile and narrow layouts.

= 1.1.6 =

* Simplified undo restoration to use the previous comment status protected by the existing authenticated AJAX request.

= 1.1.5 =

* Restored the previous comment status directly when undoing moderation actions.

= 1.1.4 =

* Replaced stored undo tokens with signed, self-validating tokens.

= 1.1.3 =

* Fixed undo tokens becoming unavailable before the undo period ended.

= 1.1.2 =

* Improved comment action menu contrast across themes.

= 1.1.1 =

* Positioned the comment action menu beside the comment content and removed the separator styling.

= 1.1.0 =

* Removed the redundant custom WP-CLI comment command.
* Added a time-limited undo action for Trash, spam, and unapprove operations.
* Added immediate comment status badges before the comment area is refreshed.
* Added a 20-entry comment edit history with revision restoration.
* Re-rendered the comment area after moderation to keep surrounding theme markup in sync.
* Moved comment management actions into an accessible overflow menu.

= 1.0.2 =

* Matched the inline comment editor textarea to the active theme's comment form styling.

= 1.0.1 =

* Added configurable GitHub repository, stable branch, and API token settings.
* Added automatic version tagging and GitHub Release creation on master pushes.

= 1.0.0 =

* Initial release.
* Added secure front-end edit, Trash, spam, unapprove, and permanent delete actions.
* Added GitHub release updates through Plugin Update Checker 5.7.
