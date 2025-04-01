<?php
/**
 * Plugin Name: Newsletter Sender for DreamHost Lists
 * Description: Send newsletters and new post notifications to your DreamHost Announcement Lists using the official API. Manage campaigns directly from WordPress. This plugin is not affiliated with or endorsed by DreamHost.
 * Author: asuh
 * Version: 1.0
 * Text Domain: dh_al
 * Requires at least: 6.7.2
 * Requires PHP: 8.3
 */

if (!defined('WPINC')) {
    die;
}

define('DH_AL_VERSION', '1.0');
define('DH_AL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DH_AL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DH_AL_OPTION_NAME', 'dh_al_apikey');
define('DH_AL_CACHE_GROUP', 'dh_al_cache');
define('DH_AL_CACHE_EXPIRATION', 3600);

// I18n - Disabled for now
// load_plugin_textdomain('dh_al', false, basename(dirname(__FILE__)) . '/languages');

/**
 * Plugin activation hook
 */
function dh_al_activate()
{
    // Add capabilities (Using manage_options consistent with menu/page check)
    $role = get_role('administrator');
    if ($role) {
        if (!$role->has_cap('manage_options')) {
            // Unlikely case
        }
    }

    // Clear any existing caches
    wp_cache_flush();
}

register_activation_hook(__FILE__, 'dh_al_activate');

/**
 * Plugin deactivation hook
 */
function dh_al_deactivate()
{
    // Clear caches
    wp_cache_flush();
}

register_deactivation_hook(__FILE__, 'dh_al_deactivate');

/**
 * Get cached API response
 */
function dh_al_get_cache($cache_key)
{
    return wp_cache_get($cache_key, DH_AL_CACHE_GROUP);
}

/**
 * Set API response cache
 */
function dh_al_set_cache($cache_key, $data)
{
    return wp_cache_set($cache_key, $data, DH_AL_CACHE_GROUP, DH_AL_CACHE_EXPIRATION);
}

/**
 * Get Announcements Lists via API
 */
function dh_al_api($function, $optional = false)
{
    $api_key = get_option(DH_AL_OPTION_NAME);

    if (empty($api_key)) {
        return new WP_Error('no_api_key', __('API key is not set.', 'dh_al'));
    }

    if ($function === 'announcement_list-list_lists') {
        $cached_data = dh_al_get_cache('list_lists');
        if ($cached_data !== false) {
            if (!is_wp_error($cached_data)) {
                return $cached_data;
            } else {
                wp_cache_delete('list_lists', DH_AL_CACHE_GROUP);
            }
        }
    }

    $api_url = add_query_arg(array(
        'key' => $api_key,
        'cmd' => $function,
        'format' => 'php'
    ), 'https://api.dreamhost.com/');

    $response = wp_remote_get($api_url, array(
        'timeout' => 15,
        'sslverify' => true
    ));

    if (is_wp_error($response)) {
        error_log(sprintf('[DH_AL] API Error (%s): %s', $function, $response->get_error_message()));
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        $error = new WP_Error('empty_response', __('Empty API response body.', 'dh_al'));
        error_log('[DH_AL] Empty API response body. URL: ' . $api_url);
        return $error;
    }

    $result = @unserialize($body);

    if ($result === false && $body !== serialize(false)) {
        $error = new WP_Error('invalid_response', __('Invalid API response (unserialize failed).', 'dh_al'));
        error_log('[DH_AL] Invalid API response (unserialize failed). Body: ' . substr($body, 0, 200) . '...');
        return $error;
    }

    if (isset($result['result']) && $result['result'] === 'error') {
        $error_message = isset($result['data']) ? $result['data'] : __('Unknown API error', 'dh_al');
        $error_reason = isset($result['reason']) ? $result['reason'] : __('No reason provided', 'dh_al');
        $error = new WP_Error('api_error', sprintf('%s: %s', $error_message, $error_reason));
        error_log(sprintf('[DH_AL] API Error Response (%s): %s - %s', $function, $error_message, $error_reason));
        return $error;
    }

    if ($function === 'announcement_list-list_lists' && isset($result['result']) && $result['result'] === 'success') {
        dh_al_set_cache('list_lists', $result);
    }

    return $result;
}

/**
 * Send Mail Via API
 */
function dh_al_api_send_mail($listdata, $subject, $message)
{
    if (empty($listdata) || !is_array($listdata)) {
        return new WP_Error('missing_data', __('No announcement lists selected.', 'dh_al'));
    }
    if (empty($subject)) {
        return new WP_Error('missing_data', __('Subject cannot be empty.', 'dh_al'));
    }
    if (empty($message)) {
        return new WP_Error('missing_data', __('Message cannot be empty.', 'dh_al'));
    }

    $last_send_timestamp = get_transient('dh_al_last_send');
    if ($last_send_timestamp) {
        $wait_time = 300 - (time() - $last_send_timestamp);
        if ($wait_time > 0) {
            return new WP_Error('rate_limit', sprintf(__('Please wait %d seconds before sending another announcement.', 'dh_al'), $wait_time));
        }
    }

    $api_key = get_option(DH_AL_OPTION_NAME);
    if (empty($api_key)) {
        return new WP_Error('no_api_key', __('API key is not set.', 'dh_al'));
    }

    $errors = array();
    $success_count = 0;

    foreach ($listdata as $encoded_data) {
        $decoded_data = base64_decode($encoded_data, true);
        if ($decoded_data === false) {
            $errors[] = __('Invalid list data format encountered.', 'dh_al');
            error_log('[DH_AL] Send Mail Error: Failed to base64 decode list data.');
            continue;
        }

        $data = @unserialize($decoded_data);
        if ($data === false || !is_array($data) || !isset($data['listname']) || !isset($data['domain']) || !isset($data['name'])) {
            $errors[] = __('Invalid list data structure encountered.', 'dh_al');
            error_log('[DH_AL] Send Mail Error: Failed to unserialize list data or missing keys.');
            continue;
        }

        $params = array(
            'listname' => $data['listname'],
            'domain' => $data['domain'],
            'subject' => $subject,
            'message' => $message,
            'name' => $data['name'],
            'charset' => 'UTF-8',
            'type' => 'html'
        );

        $api_url = add_query_arg(array_merge(
            array('key' => $api_key, 'cmd' => 'announcement_list-post_announcement', 'format' => 'php'),
            $params
        ), 'https://api.dreamhost.com/');

        $response = wp_remote_get($api_url, array('timeout' => 30, 'sslverify' => true));

        if (is_wp_error($response)) {
            $error_msg = sprintf(__('Network error sending to list "%s": %s', 'dh_al'), esc_html($data['name']), $response->get_error_message());
            $errors[] = $error_msg;
            error_log(sprintf('[DH_AL] Send Mail Network Error: %s', $response->get_error_message()));
            continue;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            $error_msg = sprintf(__('Empty API response sending to list "%s".', 'dh_al'), esc_html($data['name']));
            $errors[] = $error_msg;
            error_log('[DH_AL] Send Mail Error: Empty API response body for list ' . $data['name']);
            continue;
        }

        $result = @unserialize($body);

        if (($result === false && $body !== serialize(false)) || (isset($result['result']) && $result['result'] === 'error')) {
            $api_error_msg = __('Unknown API error', 'dh_al');
            $api_error_reason = __('No reason provided', 'dh_al');
            if (is_array($result)) {
                $api_error_msg = isset($result['data']) ? $result['data'] : $api_error_msg;
                $api_error_reason = isset($result['reason']) ? $result['reason'] : $api_error_reason;
            } else {
                $api_error_msg = __('Invalid API response format', 'dh_al');
                error_log('[DH_AL] Send Mail Error: Unserialize failed for list ' . $data['name']);
            }
            $error_message = sprintf(__('API error sending to list "%s": %s - %s', 'dh_al'), esc_html($data['name']), $api_error_msg, $api_error_reason);
            $errors[] = $error_message;
            error_log('[DH_AL] Send Mail API Error: ' . $error_message);
        } else {
            $success_count++;
        }
    }

    if ($success_count > 0 && empty($errors)) {
        set_transient('dh_al_last_send', time(), 300);
        return true;
    } elseif (!empty($errors)) {
        return array('success' => ($success_count > 0), 'errors' => $errors, 'success_count' => $success_count, 'total_lists' => count($listdata));
    } else {
        return new WP_Error('send_failed', __('Could not send to any lists, check list data.', 'dh_al'));
    }
}

/**
 * Register settings using the Settings API
 */
function dh_al_register_settings()
{
    register_setting(
        'dh_al_options_group',
        DH_AL_OPTION_NAME,
        array('type' => 'string', 'sanitize_callback' => 'dh_al_sanitize_api_key_setting', 'default' => '')
    );
    add_settings_section(
        'dh_al_api_key_section',
        __('API Key Settings', 'dh_al'),  // Section title remains generic
        '__return_false',
        'dh_al_settings_page'
    );
    add_settings_field(
        DH_AL_OPTION_NAME,
        __('DreamHost API Key', 'dh_al'),  // Field label is specific
        'dh_al_api_key_field_html',
        'dh_al_settings_page',
        'dh_al_api_key_section'
    );
}

add_action('admin_init', 'dh_al_register_settings');

/**
 * Sanitize API key for Settings API saving.
 */
function dh_al_sanitize_api_key_setting($api_key)
{
    $sanitized_key = sanitize_text_field($api_key);
    $old_key = get_option(DH_AL_OPTION_NAME);
    if (empty($sanitized_key) || strlen($sanitized_key) === 16) {
        if ($old_key !== $sanitized_key) {
            wp_cache_delete('list_lists', DH_AL_CACHE_GROUP);
        }
        return $sanitized_key;
    } else {
        add_settings_error(DH_AL_OPTION_NAME, 'dh_al_invalid_key', __('Invalid API Key. It must be exactly 16 characters.', 'dh_al'), 'error');
        return $old_key;
    }
}

/**
 * Render the HTML for the API key input field
 */
function dh_al_api_key_field_html()
{
    $api_key = get_option(DH_AL_OPTION_NAME);
    ?>
    <input name="<?php echo esc_attr(DH_AL_OPTION_NAME); ?>"
           id="<?php echo esc_attr(DH_AL_OPTION_NAME); ?>"
           type="text"
           value="<?php echo esc_attr($api_key); ?>"
           maxlength="16" size="20" class="regular-text" style="width: 300px;">
    <p class="description">
        <?php echo esc_html__('Enter your 16-character DreamHost API key.', 'dh_al'); ?>
        <?php printf(' <a href="%s" target="_blank">%s</a>', 'https://panel.dreamhost.com/index.cgi?tree=api.keys', __('Get your API key here.', 'dh_al')); ?>
    </p>
    <?php
}

/**
 * Render the admin page
 */
function dh_al_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'dh_al'));
    }

    // --- Announcement Sending Logic (Manual Form) ---
    $announcement_message = '';
    if (isset($_POST['sendannouncement']) && check_admin_referer('dh_al_send_announcement')) {
        $selected_lists = isset($_POST['dh_al_list']) ? (array) $_POST['dh_al_list'] : array();
        $subject = isset($_POST['dh_al_subject']) ? sanitize_text_field($_POST['dh_al_subject']) : '';
        $message_content = isset($_POST['dh_al_message']) ? wp_kses_post($_POST['dh_al_message']) : '';

        if (empty($selected_lists)) {
            $announcement_message = '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Please select at least one list.', 'dh_al') . '</p></div>';
        } elseif (empty($subject)) {
            $announcement_message = '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Please enter a subject.', 'dh_al') . '</p></div>';
        } elseif (empty($message_content)) {
            $announcement_message = '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Please enter a message.', 'dh_al') . '</p></div>';
        } else {
            $send_result = dh_al_api_send_mail($selected_lists, $subject, $message_content);
            if ($send_result === true) {
                $announcement_message = '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Newsletter sent successfully to all selected lists!', 'dh_al') . '</p></div>';
            } elseif (is_wp_error($send_result)) {
                $announcement_message = '<div class="notice notice-error is-dismissible"><p>' . esc_html($send_result->get_error_message()) . '</p></div>';
            } elseif (is_array($send_result) && isset($send_result['errors'])) {
                $error_html = implode('<br>', array_map('esc_html', $send_result['errors']));
                if ($send_result['success']) {
                    $announcement_message = '<div class="notice notice-warning is-dismissible"><p>' . sprintf(esc_html__('Newsletter sent with some errors (%d/%d lists succeeded):', 'dh_al'), $send_result['success_count'], $send_result['total_lists']) . '<br>' . $error_html . '</p></div>';
                } else {
                    $announcement_message = '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Failed to send newsletter:', 'dh_al') . '<br>' . $error_html . '</p></div>';
                }
            } else {
                $announcement_message = '<div class="notice notice-error is-dismissible"><p>' . esc_html__('An unexpected error occurred while sending.', 'dh_al') . '</p></div>';
            }
        }
    }

    // --- Display Page ---
    ?>
	<div class="wrap">
			<h1><?php echo esc_html__('DreamHost Newsletter Sender', 'dh_al'); // Updated H1 ?></h1>

			<?php
            // No settings_errors() call here to avoid duplicates in user's environment

            // Force Option Cache Clear After Save
            if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
                wp_cache_delete(DH_AL_OPTION_NAME, 'options');
                wp_cache_delete('alloptions', 'options');
            }
            $apikey = get_option(DH_AL_OPTION_NAME);
            ?>

			<!-- API Key Settings Form -->
			<form method="post" action="options.php">
					<?php
                    settings_fields('dh_al_options_group');
                    do_settings_sections('dh_al_settings_page');
                    submit_button(__('Save API Key', 'dh_al'));
                    ?>
			</form>

			<hr>

			<?php if (!empty($apikey)): ?>
					<h2><?php echo esc_html__('Send Custom Newsletter', 'dh_al'); // Updated H2 ?></h2>
					<?php echo $announcement_message; // Display newsletter success/error messages ?>
					<?php
                    $api_data = dh_al_api('announcement_list-list_lists');
                    if (is_wp_error($api_data)) {
                        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Error fetching lists:', 'dh_al') . ' ' . esc_html($api_data->get_error_message()) . '</p></div>';
                    } elseif (!isset($api_data['result']) || $api_data['result'] !== 'success' || !isset($api_data['data'])) {
                        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Error fetching lists: Invalid API response format.', 'dh_al') . '</p></div>';
                    } else {
                        $lists = $api_data['data'];
                        if (empty($lists)) {
                            echo '<p>' . esc_html__('No DreamHost Announcement Lists found for this API key.', 'dh_al') . '</p>';
                        } else {
                            ?>
									<form method="post" action="" class="dh-al-announcement-form">
											<?php wp_nonce_field('dh_al_send_announcement'); ?>
											<table class="form-table">
													<tr valign="top">
															<th scope="row"><?php echo esc_html__('Select List(s)', 'dh_al'); ?></th>
															<td>
																	<fieldset><legend class="screen-reader-text"><span><?php echo esc_html__('Lists', 'dh_al'); ?></span></legend>
																			<?php
                                                                            foreach ($lists as $list):
                                                                                if (!is_array($list) || !isset($list['listname']) || !isset($list['domain']) || !isset($list['name'])) {
                                                                                    continue;
                                                                                }
                                                                                $list_details_for_sending = array('listname' => $list['listname'], 'domain' => $list['domain'], 'name' => $list['name']);
                                                                                $data = base64_encode(serialize($list_details_for_sending));
                                                                                $list_id = 'dh_al_list_' . esc_attr($list['listname'] . '_' . $list['domain']);
                                                                                $subscribers = isset($list['num_subscribers']) ? (int) $list['num_subscribers'] : 0;
                                                                                ?>
																					<div class="dh-al-list-item" style="margin-bottom: 5px;">
																							<label for="<?php echo $list_id; ?>">
																									<input name="dh_al_list[]" type="checkbox" id="<?php echo $list_id; ?>" value="<?php echo esc_attr($data); ?>">
																									<?php echo esc_html($list['name']); ?> (<?php printf(esc_html__('%d subscribers', 'dh_al'), $subscribers); ?>)
																									<span style="font-size: smaller; color: #666;"> - <?php echo esc_html($list['listname'] . '@' . $list['domain']); ?></span>
																							</label>
																					</div>
																			<?php endforeach; ?>
																	</fieldset>
															</td>
													</tr>
													<tr valign="top">
															<th scope="row"><label for="dh_al_subject"><?php echo esc_html__('Subject', 'dh_al'); ?></label></th>
															<td><input type="text" name="dh_al_subject" id="dh_al_subject" class="regular-text" required></td>
													</tr>
													 <tr valign="top">
															<th scope="row"><label for="dh_al_message"><?php echo esc_html__('Message', 'dh_al'); ?></label></th>
															<td>
																	<?php wp_editor('', 'dh_al_message', array('textarea_rows' => 15, 'media_buttons' => false, 'teeny' => false, 'quicktags' => true)); ?>
																	 <p class="description"><?php echo esc_html__('HTML is allowed. The content will be sent as an HTML email.', 'dh_al'); ?></p>
															</td>
													</tr>
											</table>
											<p class="submit"><input name="sendannouncement" type="submit" class="button button-primary" value="<?php echo esc_attr__('Send Newsletter', 'dh_al'); // Updated button text ?>"></p>
									</form>
									<?php
                        }
                    }
                    ?>
			<?php else: ?>
					<p><?php echo esc_html__('Please save a valid API key above to send newsletters.', 'dh_al'); // Updated text ?></p>
			<?php endif; ?>
	</div><!-- .wrap -->
	<?php
}

/**
 * Register the admin menu
 */
function dh_al_admin_menu()
{
    add_submenu_page(
        'options-general.php',
        __('DreamHost Newsletter Sender', 'dh_al'),  // Updated Page Title
        __('DH Newsletter', 'dh_al'),  // Updated Menu Title
        'manage_options',
        'dh_al_settings_page',
        'dh_al_page'
    );
}

add_action('admin_menu', 'dh_al_admin_menu');

/**
 * Register the meta box for post editing screens.
 */
function dh_al_add_meta_boxes()
{
    add_meta_box(
        'dh_al_post_announcement_meta_box',
        __('Send Post as Newsletter (DH)', 'dh_al'),  // Updated Meta Box Title
        'dh_al_render_meta_box',
        'post',
        'side',
        'default'
    );
}

add_action('add_meta_boxes', 'dh_al_add_meta_boxes');

/**
 * Render the HTML content for the meta box.
 */
function dh_al_render_meta_box($post)
{
    wp_nonce_field('dh_al_save_meta_box_data', 'dh_al_meta_box_nonce');
    $send_announcement = get_post_meta($post->ID, '_dh_al_send_on_publish', true);
    $selected_lists_meta = (array) get_post_meta($post->ID, '_dh_al_target_lists', true);
    $api_key = get_option(DH_AL_OPTION_NAME);
    if (empty($api_key)) {
        echo '<p>' . esc_html__('Please configure the DreamHost API Key in Settings > DH Newsletter first.', 'dh_al') . '</p>';  // Updated menu path
        return;
    }
    $api_data = dh_al_api('announcement_list-list_lists');
    if (is_wp_error($api_data)) {
        echo '<p>' . esc_html__('Error fetching lists:', 'dh_al') . ' ' . esc_html($api_data->get_error_message()) . '</p>';
        return;
    }
    if (!isset($api_data['result']) || $api_data['result'] !== 'success' || !isset($api_data['data']) || empty($api_data['data'])) {
        echo '<p>' . esc_html__('No DreamHost Announcement Lists found or API error.', 'dh_al') . '</p>';
        return;
    }
    $lists = $api_data['data'];
    ?>
    <p>
        <label for="dh_al_send_on_publish">
            <input type="checkbox" name="dh_al_send_on_publish" id="dh_al_send_on_publish" value="1" <?php checked($send_announcement, '1'); ?> />
            <?php esc_html_e('Send newsletter when published?', 'dh_al'); // Updated text ?>
        </label>
    </p>
    <p><strong><?php esc_html_e('Select list(s) to notify:', 'dh_al'); ?></strong></p>
    <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ccd0d4; padding: 5px;">
        <?php
        foreach ($lists as $list):
            if (!is_array($list) || !isset($list['listname']) || !isset($list['domain']) || !isset($list['name'])) {
                continue;
            }
            $list_details_for_sending = array('listname' => $list['listname'], 'domain' => $list['domain'], 'name' => $list['name']);
            $data_value = base64_encode(serialize($list_details_for_sending));
            $list_id = 'dh_al_target_list_' . esc_attr($list['listname'] . '_' . $list['domain']);
            ?>
            <p style="margin: 0 0 5px 0;">
                <label for="<?php echo esc_attr($list_id); ?>">
                    <input type="checkbox" name="dh_al_target_lists[]" id="<?php echo esc_attr($list_id); ?>" value="<?php echo esc_attr($data_value); ?>" <?php checked(in_array($data_value, $selected_lists_meta)); ?> />
                    <?php echo esc_html($list['name']); ?>
                </label>
            </p>
        <?php endforeach; ?>
    </div>
    <p class="howto">
        <?php esc_html_e('Select the list(s) to send a notification to when this post is published. The subject will be the post title and the message will be the full post content.', 'dh_al'); ?>
    </p>
    <?php
}

/**
 * Save the meta box data when the post is saved,
 * AND send announcement if publishing for the first time.
 */
function dh_al_save_meta_box_data($post_id, $post)
{
    if (!isset($_POST['dh_al_meta_box_nonce']) || !wp_verify_nonce($_POST['dh_al_meta_box_nonce'], 'dh_al_save_meta_box_data')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if ('post' !== $post->post_type) {
        return;
    }

    $send_value_posted = isset($_POST['dh_al_send_on_publish']) ? '1' : '0';
    $selected_lists_posted = array();
    if (isset($_POST['dh_al_target_lists']) && is_array($_POST['dh_al_target_lists'])) {
        foreach ($_POST['dh_al_target_lists'] as $list_data) {
            if (is_string($list_data) && !empty($list_data)) {
                $selected_lists_posted[] = sanitize_text_field($list_data);
            }
        }
    }

    $old_send_value = get_post_meta($post_id, '_dh_al_send_on_publish', true);
    $old_selected_lists = get_post_meta($post_id, '_dh_al_target_lists', true);
    if ($send_value_posted !== $old_send_value) {
        update_post_meta($post_id, '_dh_al_send_on_publish', $send_value_posted);
    }
    if ($selected_lists_posted != $old_selected_lists) {
        update_post_meta($post_id, '_dh_al_target_lists', $selected_lists_posted);
    }

    $is_publishing_now = ($post->post_status === 'publish');
    $send_checked = ($send_value_posted === '1');
    $lists_selected = !empty($selected_lists_posted);
    $is_initial_publish = ($post->post_date_gmt === $post->post_modified_gmt);

    if ($is_publishing_now && $send_checked && $lists_selected && $is_initial_publish) {
        $subject = sprintf(__('New Post Published: %s', 'dh_al'), $post->post_title);

        $full_content = $post->post_content;

        $formatted_content = apply_filters('the_content', $full_content);
        $formatted_content = str_replace(']]>', ']]&gt;', $formatted_content);

        $message = $formatted_content;

        $permalink = get_permalink($post_id);
        $message .= '<p><a href="' . esc_url($permalink) . '">' . __('View original post', 'dh_al') . '</a></p>';

        $result = dh_al_api_send_mail($selected_lists_posted, $subject, $message);

        if ($result === true) {
            error_log('[DH_AL Save/Send] Post ID ' . $post_id . ': Newsletter announcement sent successfully.');
        }  // Updated log text
        elseif (is_wp_error($result)) {
            error_log('[DH_AL Save/Send] Post ID ' . $post_id . ': Failed to send newsletter announcement (WP_Error): ' . $result->get_error_message());
        }  // Updated log text
        elseif (is_array($result) && isset($result['errors'])) {
            error_log('[DH_AL Save/Send] Post ID ' . $post_id . ': Failed to send newsletter announcement (API Errors): ' . implode('; ', $result['errors']));
        }  // Updated log text
        else {
            error_log('[DH_AL Save/Send] Post ID ' . $post_id . ': Failed to send newsletter announcement (Unknown Error). Result: ' . print_r($result, true));
        }  // Updated log text
    }
}

add_action('save_post', 'dh_al_save_meta_box_data', 10, 2);

?>
