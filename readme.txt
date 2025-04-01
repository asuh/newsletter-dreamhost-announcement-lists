=== Dreamhost Announcements ===
Contributors: asuh
Tags: announcements, newsletter, rundbrief, dreamhost, API, mail, mailinglist
Requires at least: 6.7.2
Tested up to: 6.7.2
Requires PHP: 8.3
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send newsletters and new post notifications (full content) to your DreamHost Announcement Lists using the official API.

== Description ==

This plugin provides a convenient way to interact with DreamHost's "Announcement Lists" feature (often used as mailing lists or newsletters) directly from your WordPress dashboard.

**Features:**

*   Manually compose and send custom newsletters/announcements to one or more of your DreamHost lists via **Settings > DH Newsletter**.
*   Automatically send the **full content** of a new blog post (formatted similarly to how it appears on your site) as a notification to selected DreamHost lists when the post is published for the first time.
*   Adds a meta box to the Post edit screen to control automatic sending for each post.
*   Connects securely to the DreamHost API using your private API key.
*   Fetches and displays your available DreamHost Announcement Lists.
*   Includes basic rate limiting on the manual sending form.
*   Caches the list of DreamHost Announcement Lists for performance.

**Disclaimer:** This plugin is developed independently by **asuh** and is **not** affiliated with, endorsed, or supported by DreamHost. It utilizes the official DreamHost API.

== Installation ==

**1. Get Your DreamHost API Key:**

1.  Log in to your [DreamHost Panel](https://panel.dreamhost.com/).
2.  Navigate to the **API Keys** section (often under 'Billing & Account' or accessible via [https://panel.dreamhost.com/index.cgi?tree=api.keys](https://panel.dreamhost.com/index.cgi?tree=api.keys)).
3.  Generate a new API Key if you don't have one suitable.
4.  **Crucially, ensure the key has permissions for `announcement_list-list_lists` and `announcement_list-post_announcement`.** Granting all `announcement_list-*` permissions is usually the simplest way.
5.  Copy the 16-character API key.
6.  Make sure you have at least one Announcement List created under **Mail > Announce Lists** in the DreamHost Panel.

**2. Install the Plugin in WordPress:**

*   **Method A: Uploading ZIP**
    1.  Download the plugin `.zip` file.
    2.  In your WordPress admin, go to **Plugins > Add New**.
    3.  Click **Upload Plugin**.
    4.  Choose the downloaded `.zip` file and click **Install Now**.
    5.  Activate the plugin.
*   **Method B: FTP/SFTP**
    1.  Download and unzip the plugin.
    2.  Upload the entire plugin folder (e.g., `newsletter-sender-for-dreamhost-lists`) to your `/wp-content/plugins/` directory via FTP/SFTP.
    3.  In your WordPress admin, go to **Plugins > Installed Plugins**.
    4.  Find "Newsletter Sender for DreamHost Lists" and click **Activate**.

**3. Configure the Plugin:**

1.  Go to **Settings > DH Newsletter** in your WordPress admin menu.
2.  Paste the 16-character DreamHost API key you obtained into the **DreamHost API Key** field.
3.  Click **Save API Key**.

== Usage ==

**Sending a Custom Newsletter:**

1.  Go to **Settings > DH Newsletter**.
2.  If your API key is valid, the "Send Custom Newsletter" form will appear.
3.  Select the target list(s).
4.  Enter a Subject.
5.  Compose your message using the editor (HTML is allowed).
6.  Click **Send Newsletter**.
7.  Look for success/error messages at the top of the page. Check your PHP error log for details if sending fails.

**Sending New Post Notifications:**

1.  When creating or editing a Post.
2.  Find the **Send Post as Newsletter (DH)** box (usually in the sidebar).
3.  Check the **Send newsletter when published?** box to enable sending for this post.
4.  Select the specific **list(s)** to send the notification to.
5.  When you click **Publish** for the *first time*, the plugin will send the full post content to the selected list(s).
6.  *Note:* Updating an already published post will **not** resend the notification.
7.  Check your PHP error log (`/wp-content/debug.log` if enabled) for confirmation or errors (`[DH_AL Save/Send]...`).

== Frequently Asked Questions ==

= Where do I get my DreamHost API key? =

Log in to your DreamHost Panel and navigate to the API Keys section ([https://panel.dreamhost.com/index.cgi?tree=api.keys](https://panel.dreamhost.com/index.cgi?tree=api.keys)). Ensure the key has `announcement_list-*` permissions.

= Why don't I see the "Send Custom Newsletter" form? =

You must enter and save a valid 16-character DreamHost API key first under **Settings > DH Newsletter**. Also ensure you have created at least one Announcement List in your DreamHost panel.

= My new post notification didn't send. Why? =

Check these:
1.  Did you check the "Send newsletter when published?" box in the post editor?
2.  Did you select at least one list in the post editor?
3.  Was this the very *first* time you published that specific post? (Updates don't resend).
4.  Is your API key still valid and saved correctly in **Settings > DH Newsletter**?
5.  Check your PHP error log (`/wp-content/debug.log` if `WP_DEBUG_LOG` is enabled) for `[DH_AL Save/Send]` messages related to that post ID.

= Is this an official DreamHost plugin? =

No. This plugin is developed independently by **asuh** and is not affiliated with, endorsed, or supported by DreamHost.

== Screenshots ==

1.  Settings page showing API key input and manual newsletter form.
2.  Post editor showing the "Send Post as Newsletter (DH)" meta box.

== Changelog ==

= 1.0 =
*   Modify post notification feature to send full formatted post content instead of excerpt.
*   Update meta box description text.

= 0.9 =
*   Update plugin name, description, and UI text to focus on "Newsletter Sender" functionality.
*   Add Author URI / Plugin URI placeholders.

= 0.75 =
*   Implement sending notifications for newly published posts via meta box (using excerpt initially).
*   Refine `save_post` logic to reliably detect initial publish event using date comparison.
*   Add cache clearing for options page to better handle object caching environments.
*   Remove debugging code.

= 0.5 =
*   Refactor API key saving to use standard WordPress Settings API.
*   Improve API error handling and logging.
*   Improve sending logic and feedback messages.
*   Address issues with notice display and option fetching after save.

= 0.1 =
*   Initial functional version with basic API key saving and manual sending form.