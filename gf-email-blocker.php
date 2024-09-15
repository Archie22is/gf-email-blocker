<?php 
/**
 * Plugin Name: Gravity Forms Email Blocker
 * Version: 1.0.0
 * Plugin URI: https://www.thrivedx.com
 * Description: Adds a checkbox to Gravity Forms to block submissions from free email providers and logs attempts.
 * Author: ThriveDX 
 *
 * @author Archie M
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// Add settings to Gravity Forms menu under "Forms"
add_action('admin_menu', 'gf_email_blocker_menu', 20);
function gf_email_blocker_menu() {

    // Add Email Blocker Settings page under "Forms"
    add_submenu_page(
        'gf_edit_forms', // Parent slug
        __('Email Blocker Settings', 'gravityforms'), // Page title
        __('Email Blocker Settings', 'gravityforms'), // Menu title
        'manage_options', // Capability
        'gf_email_blocker_settings', // Menu slug
        'gf_email_blocker_settings_page' // Callback function
    );

    // Add Failed Emails page under "Forms"
    add_submenu_page(
        'gf_edit_forms', // Parent slug
        __('Failed Emails - Free Email Provider', 'gravityforms'), // Page title
        __('Failed Emails - Free Email Provider', 'gravityforms'), // Menu title
        'manage_options', // Capability
        'gf_email_blocker_failed_emails', // Menu slug
        'gf_email_blocker_failed_emails_page' // Callback function
    );

}


// Email Blocker Settings page callback
function gf_email_blocker_settings_page() {
    ?>
    <div class="wrap">
        <h2>Email Blocker Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('gf_email_blocker_options');
            do_settings_sections('gf-email-blocker');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Free Email Domains:</th>
                    <td><input type="text" name="gf_email_blocker_domains" value="<?php echo esc_attr(get_option('gf_email_blocker_domains', 'gmail.com,yahoo.com,gmx.com,gmx.de,icloud.com,mail.com,mail.ru,protonmail.com,yandex.com,tutanota.com,fastmail.com,aol.com')); ?>" style="width:100%" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Custom Message for Rejected Email:</th>
                    <td><input type="text" name="gf_email_blocker_message" value="<?php echo esc_attr(get_option('gf_email_blocker_message', 'Free email addresses are not allowed.')); ?>" style="width:100%" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}


// Register plugin settings
add_action('admin_init', 'gf_email_blocker_settings');
function gf_email_blocker_settings() {
    register_setting('gf_email_blocker_options', 'gf_email_blocker_message');
    register_setting('gf_email_blocker_options', 'gf_email_blocker_domains'); // Register the new domain setting
}


// Add checkbox to each form's settings under "Form Settings"
add_filter('gform_form_settings', 'gf_email_blocker_form_settings', 10, 2);
function gf_email_blocker_form_settings($settings, $form) {
    $is_checked = rgar($form, 'gf_email_blocker_enabled') ? 'checked="checked"' : '';
    $settings['Form Options']['gf_email_blocker_enabled'] = '
        <tr>
            <th>Block Free Email Providers</th>
            <td><input type="checkbox" name="gf_email_blocker_enabled" value="1" ' . $is_checked . '> Enable blocking of free email providers</td>
        </tr>';
    return $settings;
}


// Save form settings
add_filter('gform_pre_form_settings_save', 'gf_email_blocker_save_form_settings');
function gf_email_blocker_save_form_settings($form) {
    $form['gf_email_blocker_enabled'] = isset($_POST['gf_email_blocker_enabled']) ? 1 : 0;
    return $form;
}


// Validate email input and log failed attempts
add_filter('gform_field_validation', 'gf_email_blocker_validate_email', 10, 4);
function gf_email_blocker_validate_email($result, $value, $form, $field) {
    $is_blocking_enabled = rgar($form, 'gf_email_blocker_enabled');

    if ($is_blocking_enabled && $field->type === 'email') {
        // Get free domains from settings
        $domains = get_option('gf_email_blocker_domains', 'gmail.com,yahoo.com,gmx.com,gmx.de,icloud.com,mail.com,mail.ru,protonmail.com,yandex.com,tutanota.com,fastmail.com,aol.com');
        $free_domains = array_map('trim', explode(',', $domains)); // Convert the string to an array

        $user_domain = substr(strrchr($value, "@"), 1);

        if (in_array($user_domain, $free_domains)) {
            $result['is_valid'] = false;
            $result['message'] = get_option('gf_email_blocker_message', 'Free email addresses are not allowed.');

            // Log failed email
            $failed_emails = get_option('gf_email_blocker_failed_emails', array());
            $failed_emails[] = array(
                'email' => $value,
                'time' => current_time('mysql'),
                'form_id' => $form['id']
            );
            update_option('gf_email_blocker_failed_emails', $failed_emails);
        }
    }
    return $result;
}


// Display failed emails in the admin menu
function gf_email_blocker_failed_emails_page() {
    $failed_emails = get_option('gf_email_blocker_failed_emails', array());

    echo '<div class="wrap">';
    echo '<h2>Email Blocker Failed Emails</h2>';
    if (empty($failed_emails)) {
        echo '<p>No failed submissions found.</p>';
    } else {
        echo '<table class="widefat fixed" cellspacing="0">';
        echo '<thead><tr><th>Email Address</th><th>Time</th><th>Form ID</th></tr></thead>';
        echo '<tbody>';
        foreach ($failed_emails as $entry) {
            echo '<tr>';
            echo '<td>' . esc_html($entry['email']) . '</td>';
            echo '<td>' . esc_html($entry['time']) . '</td>';
            echo '<td>' . esc_html($entry['form_id']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
    echo '</div>';
}
