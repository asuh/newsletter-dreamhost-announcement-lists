# Newsletter Sender for DreamHost Lists

**Send newsletters and new blog posts to your DreamHost Announcement Lists using the official API. Manage campaigns directly from WordPress.**

> [!NOTE]
> **Disclaimer:** This plugin is developed independently and is **not** affiliated with, endorsed, or supported by DreamHost. It utilizes the official DreamHost API.

---

## Description

This WordPress plugin provides a convenient way to interact with DreamHost's "Announcement Lists" feature (often used as mailing lists or newsletters) directly from your WordPress dashboard.

It allows you to:

1.  Manually compose and send custom newsletters/announcements to one or more of your DreamHost lists.
2.  Automatically send the **full content** of a new blog post (formatted similarly to how it appears on your site, including processed shortcodes and paragraphs) as a notification to selected DreamHost lists when the post is published for the first time.

The plugin requires a DreamHost API key with permissions for the `announcement_list` functions.

## Features

*   Connects securely to the DreamHost API using your private API key.
*   Provides an admin page (Settings > DH Newsletter) to manually send custom newsletters.
*   Fetches and displays your available DreamHost Announcement Lists on the settings page and in the post editor.
*   Includes a meta box on the Post edit screen to enable/disable sending a notification for that specific post upon initial publishing.
*   Allows selection of specific list(s) for each post notification.
*   Automatically formats the post notification message (Subject: Post Title, Body: **Full formatted post content**).
*   Includes basic rate limiting on the manual sending form to prevent accidental multiple sends.
*   Caches the list of DreamHost Announcement Lists for performance (refreshes hourly or when API key changes).

## Requirements

*   WordPress 6.7.2 or higher (Recommended: Latest version)
*   PHP 8.2 or higher (Recommended: Latest stable version supported by WordPress)
*   A DreamHost account with at least one Announcement List configured.
*   A DreamHost API key with permissions for `announcement_list-list_lists` and `announcement_list-post_announcement`.

## Installation

1.  Download the plugin as a `.zip` file.
2.  Log in to your WordPress admin dashboard.
3.  Navigate to **Plugins > Add New**.
4.  Click the **Upload Plugin** button at the top.
5.  Click **Choose File** and select the `.zip` file you downloaded.
6.  Click **Install Now**.
7.  Once installed, click **Activate Plugin**.

## Usage

### 1. Configuration (API Key)

1.  After activating the plugin, navigate to **Settings > DH Newsletter** in your WordPress admin menu.
2.  You will see the **API Key Settings** section.
3.  Obtain your API key from the DreamHost Panel:
    *   Log in to your DreamHost account.
    *   Navigate to **API Keys** (often found under Billing & Account or similar). You can usually find it directly via: [https://panel.dreamhost.com/index.cgi?tree=api.keys](https://panel.dreamhost.com/index.cgi?tree=api.keys)
    *   Create a new key if you don't have one. Ensure it has permissions for `announcement_list-list_lists` and `announcement_list-post_announcement`. Granting all `announcement_list-*` permissions is usually easiest.
    *   Copy the 16-character API key.
4.  Paste the 16-character key into the **DreamHost API Key** field in the plugin settings.
5.  Click **Save API Key**.
6.  If the key is valid and saved successfully, the page will reload, and the "Send Custom Newsletter" form should appear below the API key settings.

### 2. Sending a Custom Newsletter

1.  Navigate to **Settings > DH Newsletter**.
2.  Ensure your API key is saved and the form is visible.
3.  Under **Send Custom Newsletter**:
    *   Select one or more lists from the **Select List(s)** checkboxes.
    *   Enter a **Subject** for your newsletter.
    *   Compose your message in the **Message** editor (HTML is allowed).
    *   Click the **Send Newsletter** button.
4.  A success or error message will appear at the top of the page. Note the simple rate limiting prevents sending again immediately. Check your PHP error log (`/wp-content/debug.log` if `WP_DEBUG_LOG` is enabled) for detailed API error messages if sending fails.

### 3. Sending New Post Notifications

1.  When creating or editing a Post (`Posts > Add New` or `Posts > Edit`):
2.  Look for the **Send Post as Newsletter (DH)** meta box (usually in the right-hand sidebar).
3.  To enable sending for this post, check the **Send newsletter when published?** box.
4.  Select the specific **list(s)** you want this post notification sent to.
5.  When you click the **Publish** button for the *first time*, the plugin will automatically:
    *   Use the Post Title as the email subject.
    *   Use the **full, formatted post content** (including processed shortcodes, paragraphs, etc.) as the email message.
    *   Send this message to the selected DreamHost Announcement List(s).
6.  **Important:** This only happens on the *initial* publish event (when the post status changes from something else *to* 'publish'). Simply updating an already published post will *not* trigger the announcement again.
7.  Check your PHP error log (`/wp-content/debug.log` if `WP_DEBUG_LOG` is enabled) for confirmation or error messages related to the send attempt (look for `[DH_AL Save/Send]` messages).

## Frequently Asked Questions (FAQ)

*   **Where do I get my DreamHost API key?**
    Log in to your DreamHost Panel and navigate to the API Keys section. You can usually find it directly via [https://panel.dreamhost.com/index.cgi?tree=api.keys](https://panel.dreamhost.com/index.cgi?tree=api.keys). Ensure the key has `announcement_list-*` permissions.

*   **Why don't I see the "Send Custom Newsletter" form?**
    You need to enter and save a valid 16-character DreamHost API key in the settings first (**Settings > DH Newsletter**).

*   **My new post notification didn't send. Why?**
    *   Did you check the "Send newsletter when published?" box in the post editor meta box?
    *   Did you select at least one list in the meta box?
    *   Was this the very *first* time you clicked "Publish" for that post? Updating an already published post won't resend the notification.
    *   Is your API key still valid and saved correctly in the plugin settings?
    *   Check your PHP error log (`/wp-content/debug.log` if `WP_DEBUG_LOG` is enabled) for specific error messages from the plugin (`[DH_AL Save/Send]...`) or the DreamHost API.

*   **Is this an official DreamHost plugin?**
    No. This plugin is developed independently and is not affiliated with, endorsed, or supported by DreamHost.

## Changelog

*   **1.0**
    *   Modified post notification feature to send full formatted post content instead of excerpt.
    *   Updated meta box description text.
*   **0.9**
    *   Updated plugin name, description, and UI text to focus on "Newsletter Sender" functionality.
    *   Added Author URI / Plugin URI placeholders.
*   **0.75**
    *   Implemented sending notifications for newly published posts via meta box (using excerpt).
    *   Refined `save_post` logic to reliably detect initial publish event using date comparison.
    *   Added cache clearing for options page to better handle object caching environments.
    *   Removed debugging code.
*   **0.5**
    *   Refactored API key saving to use standard WordPress Settings API.
    *   Improved API error handling and logging.
    *   Improved sending logic and feedback messages.
    *   Addressed issues with notice display and option fetching after save.
*   **0.1** (Initial version shared)
    *   Basic API key saving.
    *   Manual announcement sending form.
    *   API list fetching with basic caching.

## License

GPLv2 or later
(Standard WordPress plugin license)
