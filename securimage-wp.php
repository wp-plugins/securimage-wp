<?php
/*
Plugin Name: Securimage-WP
Plugin URI: http://phpcaptcha.org/download/wordpress-plugin
Description: CAPTCHA plugin for site registration and post/page comment forms
Author: Drew Phillips
Version: 3.6.3
Author URI: http://www.phpcaptcha.org/
*/

/*  Copyright (C) 2015 Drew Phillips  (http://phpcaptcha.org/download/securimage-wp)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

function siwp_get_plugin_url()
{
    return WP_PLUGIN_URL . '/' . str_replace(basename( __FILE__), "", plugin_basename(__FILE__));
}

function siwp_get_plugin_path()
{
    return WP_PLUGIN_DIR . '/' . str_replace(basename( __FILE__), "", plugin_basename(__FILE__));
}

function siwp_get_captcha_image_url()
{
    return siwp_get_plugin_url() . 'lib/siwp_captcha.php';
}

function siwp_default_flash_icon()
{
    return siwp_get_plugin_url() . 'lib/images/audio_icon.png';
}

function siwp_install()
{
    global $wpdb;

    $table_name = siwp_get_table_name();

    $sql = "CREATE TABLE $table_name (
      id VARCHAR(40) NOT NULL,
      code VARCHAR(10) NOT NULL DEFAULT '',
      code_display VARCHAR(10) NOT NULL DEFAULT '',
      created INT NOT NULL DEFAULT 0,
      PRIMARY KEY  (id),
      KEY (created)
    );";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    $defaultStyle = array_shift(siwp_get_sequence_list());
    if (get_option('siwp_display_sequence', null) === null) {
        update_option('siwp_display_sequence', $defaultStyle);
    }
}

function siwp_captcha_html($post_id = 0, $forceDisplay = false, $shortcode = false)
{
    static $count = 0;

    if ($shortcode) {
        $position_fix = false;
    } else {
        $position_fix = get_option('siwp_position_fix', 0);
    }

    $captcha_html = "<div id=\"siwp_captcha_input\">\n";

    if (!$forceDisplay && is_user_logged_in() && current_user_can('administrator')) {
        $captcha_html .= '<div style="font-size: 1.2em; text-align: center">' . __('Securimage-WP CAPTCHA would appear here if you were not logged in as a WordPress administrator') . '</div>';
    } else {
        $show_protected_by = get_option('siwp_show_protected_by', 0);
        $disable_audio     = get_option('siwp_disable_audio', 1);
        $flash_bgcol       = get_option('siwp_flash_bgcol', '#ffffff');
        $flash_icon        = get_option('siwp_flash_icon', siwp_default_flash_icon());
        $refresh_text      = get_option('siwp_refresh_text', 'Different Image');
        $use_refresh_text  = get_option('siwp_use_refresh_text', 0);
        $imgclass          = get_option('siwp_css_clsimg', '');
        $labelclass        = get_option('siwp_css_clslabel', '');
        $inputclass        = get_option('siwp_css_clsinput', '');
        $imgstyle          = get_option('siwp_css_cssimg');
        $labelstyle        = get_option('siwp_css_csslabel');
        $inputstyle        = get_option('siwp_css_cssinput');
        $expireTime        = siwp_get_captcha_expiration();
        $display_sequence  = get_option('siwp_display_sequence', 'captcha-input-label');
        $display_sequence  = preg_replace('/\s|\(.*?\)/', '', $display_sequence);
        $captchaId         = sha1(uniqid($_SERVER['REMOTE_ADDR'] . $_SERVER['REMOTE_PORT']));
        $plugin_url        = siwp_get_plugin_url();
        $hidInputTagId     = 'input_siwp_captcha_id_' . $count;
        $inputTagId        = 'siwp_captcha_value_' . $count;
        $imgTagId          = 'securimage_captcha_image_' . $count;
        $objTagId          = 'siwp_obj_' . $count;
        $timeout           = $expireTime * 1000;

        if ($count < 1) {
            $captcha_html .=
            "<script type=\"text/javascript\">
            function siwp_refresh(id) {
                // get new captcha id, refresh the image w/ new id, and update form input with new id
                var cid = siwp_genid();
                document.getElementById('input_siwp_captcha_id_' + id).value = cid;
                document.getElementById('securimage_captcha_image_' + id).src = '{$plugin_url}lib/siwp_captcha.php?id=' + cid;

                // update flash button with new id
                var obj = document.getElementById('siwp_obj_' + id);
                if (null !== obj) {
                    obj.setAttribute('data', obj.getAttribute('data').replace(/[a-zA-Z0-9]{40}$/, cid));
                    var par = document.getElementById('siwp_param'); // this was a comment...
                    par.value = par.value.replace(/[a-zA-Z0-9]{40}$/, cid);

                    // replace old flash w/ new one using new id
                    var newObj = obj.cloneNode(true);
                    obj.parentNode.insertBefore(newObj, obj);
                    obj.parentNode.removeChild(obj);
                }
            }
            function siwp_genid() {
                // generate a random id
                var cid = '', chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
                for (var c = 0; c < 40; ++c) { cid += chars.charAt(Math.floor(Math.random() * chars.length)); }
                return cid;
            };
            </script>
            ";
        }
        $captcha_html .= "<script type=\"text/javascript\">var siwp_interval = setInterval(function() { siwp_refresh('{$count}'); }, $timeout);</script>";

        $sequence = explode('-', $display_sequence);
        foreach($sequence as $part) {
            switch($part) {
                case 'break':
                    $captcha_html .= "<br />\n";
                    break;

                case 'captcha':
                {
                    $captcha_html .= '<div style="float: left">';
                    $captcha_html .= '<img id="' . $imgTagId . '" src="' .
                                     siwp_get_captcha_image_url() .
                                     '?id=' . $captchaId . '" alt="' . __('CAPTCHA Image') . '" style="vertical-align: middle;' .
                                     ($imgstyle != '' ?
                                     ' ' . htmlspecialchars($imgstyle) :
                                     '') . '" ' .
                                     ($imgclass != '' ?
                                     'class="' . htmlspecialchars($imgclass) . '" ' :
                                     '') .
                                     "/>";

                    if ($show_protected_by === '1') {
                        $captcha_html .= '<br /><a href="http://www.phpcaptcha.org/" ' .
                                         'target="_new" style="font-size: 12px; ' .
                                         'font-style: italic" class="' .
                                         'swip_protected_by">Protected by Securimage-WP' .
                                         '</a>' . "\n";
                    }

                    $captcha_html .= "</div>\n";
                    $captcha_html .= '<div style="float: left">';

                    if (!$disable_audio) {
                         $captcha_html .= '<object id="' . $objTagId . '" type="application/x-shockwave-flash"' .
                                          ' data="' . siwp_get_plugin_url() .
                                          'lib/securimage_play.swf?bgcol=%23' . $flash_bgcol .
                                          '&amp;icon_file=' . urlencode($flash_icon)  .
                                          '&amp;audio_file=' . urlencode(siwp_get_plugin_url()) .
                                          'lib/siwp_play.php%3Fid=' . $captchaId . '" height="32" width="32" style="margin-bottom: 5px">' .
                                          "\n" .
                                          '<param id="siwp_param" name="movie" value="' . siwp_get_plugin_url() .
                                          'lib/securimage_play.swf?bgcol=%23' . $flash_bgcol  .
                                          '&amp;icon_file=' . urlencode($flash_icon) .
                                          '&amp;audio_file=' . urlencode(siwp_get_plugin_url()) .
                                          'lib/siwp_play.php%3Fid=' . $captchaId . '">' .
                                          "\n</object>\n<br />";
                    }

                    if ($use_refresh_text) $captcha_html .= '[ ';
                    $captcha_html .= '<a tabindex="-1" style="border-style: none;"' .
                                     ' href="#" title="' . htmlspecialchars(__('Refresh Image')) . '" ' .
                                     'onclick="siwp_refresh(\'' . $count . '\'); this.blur(); return false">' .
                                     ($use_refresh_text == false ?
                                     '<img src="' . siwp_get_plugin_url() .
                                     'lib/images/refresh.png" alt="' . htmlspecialchars(__('Reload Image')) . '"' .
                                     ' onclick="this.blur()" style="vertical-align: middle; height: 32px; width: 32px"' .
                                     ' align="bottom" />' :
                                     $refresh_text
                                     ) .
                                     '</a>';
                    if ($use_refresh_text) $captcha_html .= ' ]';

                    $captcha_html .= '</div><div style="clear: both;"></div>' . "\n";

                    break;
                }

                case 'input':
                    $captcha_html .= '<input type="hidden" id="' . $hidInputTagId . '" name="siwp_captcha_id" value="' . $captchaId . '" />' .
                                     '<input id="' . $inputTagId . '" name="siwp_captcha_value" size="10" ' .
                                     'maxlength="8" type="text" aria-required="true"' .
                                     ($inputclass != '' ?
                                     ' class="' . htmlspecialchars($inputclass) . '"' :
                                     '') .
                                     ($inputstyle != '' ?
                                     ' style="' . htmlspecialchars($inputstyle) . '" ' :
                                     '') .
                                     ' />';

                    if (get_current_theme() == 'Twenty Eleven') {
                        $captcha_html .= '</p>';
                    }

                    $captcha_html .= "\n";
                    break;

                case 'label':
                    if (get_current_theme() == 'Twenty Eleven') {
                        $captcha_html .= '<p class="comment-form-email">';
                    }
                    $captcha_html .= '<label for="' . $inputTagId . '"' .
                                       ($labelclass != '' ?
                                     ' class="' . $labelclass . '"' :
                                     '') .
                                     ($labelstyle != '' ?
                                     ' style="' . htmlspecialchars($labelstyle) . '"' :
                                     '') .
                                     '>' .
                                     __('Enter Code') . ' <span class="required">*</span>' .
                                     '</label>' .
                                     "\n";
                    break;
            }
        }
    } // else current_user_can()

    $captcha_html .= "</div>\n"; // div#siwp_captcha_input

    if ($position_fix) {
        $captcha_html .=
        "
        <script type=\"text/javascript\">
        var commentSubButton = document.getElementById('comment');
        var csbParent = commentSubButton.parentNode;
        var captchaDiv = document.getElementById('siwp_captcha_input');
        csbParent.appendChild(captchaDiv, commentSubButton);
        </script>
        <noscript>
        <style tyle='text/css'>#submit {display: none}</style><br /><input name='submit' type='submit' id='submit-alt' tabindex='6' value='" . __('Submit Comment') . " />
        </noscript>
        ";
    }

    $count++;

    echo $captcha_html;
} // function siwp_captcha_html

function siwp_process_comment($commentdata)
{
    // admin comment reply using ajax from admin panel
    if ( isset($_POST['_ajax_nonce-replyto-comment']) && check_ajax_referer('replyto-comment', '_ajax_nonce-replyto-comment')) {
        return $commentdata;
    }

    // pingback or trackback comment
    if ( (!empty($commentdata['comment_type'])) && in_array($commentdata['comment_type'], array('pingback', 'trackback'))) {
        return $commentdata;
    }

    // admin comment from comment form
    if (is_user_logged_in() && current_user_can('administrator')) {
        return $commentdata;
    }

    // compatibility with tiled gallery carousel without jetpack
    if ( isset($_POST['action']) && $_POST['action'] == 'post_attachment_comment' && basename($_SERVER['REQUEST_URI']) == 'admin-ajax.php' && is_plugin_active('tiled-gallery-carousel-without-jetpack/tiled-gallery.php') ) {
        return $commentdata;
    }

    $error = '';

    if (false === siwp_check_captcha($error)) {
        wp_die(__('Error:') . ' ' . $error . ' ' . sprintf(__('Please go %sback%s and try again.'), '<a href="javascript:history.go(-1)">', '</a>'));
    }

    return $commentdata;
}

function siwp_process_registration($username, $email, $wperror)
{
    // admin user create account
    if (is_user_logged_in() && current_user_can('administrator')) {
        return true;
    }

    $error = '';
    if (false === siwp_check_captcha($error)) {
        if ( ($pos = strpos($error, 'Please')) !== false) {
            // strip javascript back message from error
            $error = substr($error, 0, $pos);
        }
        $wperror->add('registerfail', $error);
        return false;
    }

    return true;
}

function siwp_process_bp_registration()
{
    global $bp;

    if (!empty($bp->signup->errors)) return ; // don't validate if other signup errors present

    $error = '';
    if (false === siwp_check_captcha($error)) {
        $bp->signup->errors['siwp_captcha_value'] = $error;
        return false;
    }

    return true;
}

function siwp_process_login()
{
    $error = '';
    if (false === siwp_check_captcha($error)) {
        wp_die($error);
    }
}

function siwp_check_captcha(&$error)
{
    $valid       = false; // valid captcha entry?
    $code       = '';    // code entered
    $captchaId = '';    // captcha ID to check

    // check that a captcha id was submitted with the form
    if (!empty($_POST['siwp_captcha_id'])) {
        $captchaId = trim(stripslashes($_POST['siwp_captcha_id']));

        // make sure the captchaId is 40 characters
        if (strlen($captchaId) == 40) {
            // check for captcha solution, if one was entered
            if (!empty($_POST['siwp_captcha_value'])) {
                $code = trim(stripslashes($_POST['siwp_captcha_value']));
            }
        } else {
            // invalid token
            $error = __('The security token is invalid.');
            return false;
        }
    } else {
        // missing token
        $error = __('Missing security token from submission.');
        return false;
    }

    if (strlen($code) > 0) {
        // validate the code if we received an input
        if (siwp_validate_captcha_by_id($captchaId, $code) == true) {
            $valid = true;
        }
    }

    if (!$valid) {
        // captcha was typed wrong or was left empty
        $error = __('The security code entered was incorrect.');
        return false;
    }

    return true;
}

function siwp_captcha_shortcode($attrs)
{
    ob_start();
    siwp_captcha_html(0, true, true);
    $html = ob_get_clean();

    $html = str_replace('siwp_captcha_input', 'siwp_captcha_container', $html);

    return $html;
}

function siwp_captcha_html_bpregiseter()
{
    global $bp;
    static $executed = false;

    // hooked to multiple locations, but only display once
    if ($executed) return;

    ob_start();
    siwp_captcha_html(0, true, true);
    $html = ob_get_clean();

    // add class to captcha div element
    $html = str_replace('siwp_captcha_input">', 'siwp_captcha_input" class="submit">', $html);
    $executed = true;

    // if captcha error after submission, show error above captcha image
    if (!empty($bp->signup->errors['siwp_captcha_value'])) {
        echo '<div class="error">' . $bp->signup->errors['siwp_captcha_value'] . '</div>';
    }

    echo $html;
}

function siwp_validate_captcha_by_id($captchaId, $captchaValue)
{
    global $wpdb;

    $code = siwp_get_code_from_database($captchaId);

    $valid = false;

    if ($code != null) {
        if (strtolower($captchaValue) == $code->code) {
            $valid = true;
            siwp_delete_captcha_id($captchaId);
        }
    }

    if ($valid) {
        update_site_option('siwp_stat_passed', (int)get_site_option('siwp_stat_passed') + 1);
    } else {
        update_site_option('siwp_stat_failed', (int)get_site_option('siwp_stat_failed') + 1);
    }

    return $valid;
}

function siwp_generate_code($captchaId, Securimage $si)
{
    global $wpdb;
    $table_name = siwp_get_table_name();

    $code = $si->createCode();
    $code = $si->getCode(true, true);

    $wpdb->query(
            $wpdb->prepare("INSERT INTO $table_name (id, code, code_display, created)
                    VALUES
                    (%s, %s, %s, %s);",
                    $captchaId, $code['code'], $code['display'], time())
    );

    // random garbage collection
    if (mt_rand(0, 100) / 100.0 == 1.0) {
        siwp_purge_captchas();
    }

    return $code;
}

function siwp_get_code_from_database($captchaId)
{
    global $wpdb;
    $table_name = siwp_get_table_name();

    $result = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_name WHERE id = %s", $captchaId)
    );

    if ($result != null) {
        if (time() - $result->created >= siwp_get_captcha_expiration()) {
            $result = null;
        }
    }

    return $result;
}

function siwp_delete_captcha_id($captchaId)
{
    global $wpdb;
    $table_name = siwp_get_table_name();

    $wpdb->query(
        $wpdb->prepare("DELETE FROM $table_name WHERE id = %s", $captchaId)
    );
}

function siwp_purge_captchas()
{
    global $wpdb;
    $table_name = siwp_get_table_name();
    $expiry_time = (int)siwp_get_captcha_expiration();

    $res = $wpdb->query(
        $wpdb->prepare("DELETE FROM $table_name WHERE UNIX_TIMESTAMP() - created >= %d", $expiry_time)
    );

    if ($res !== false) {
        return $res;
    } else {
        return 0;
    }
}

function siwp_get_captcha_database_count()
{
    global $wpdb;
    $table_name = siwp_get_table_name();

    $result = $wpdb->get_row("SELECT COUNT(id) AS count FROM $table_name");

    if ($result) {
        return $result->count;
    } else {
        return 0;
    }
}

function siwp_get_table_name()
{
    global $wpdb;
    return $wpdb->prefix . 'securimagewp';
}

function siwp_get_sequence_list()
{
    return array(
        'break-captcha-label-input (Twenty Fifteen / WordPress Default Styles)',
        'break-captcha-label-break-input (Twenty Ten Style)',
        'break-captcha-input-label',
        'break-captcha-break-input-label',
        'captcha-break-label-input',
        'captcha-label-input',
        'captcha-break-input-label',
        'captcha-input-label',
    );
}

// register hooks
require_once ABSPATH . '/wp-includes/pluggable.php';

add_action('admin_menu', 'siwp_plugin_menu');
register_activation_hook(__FILE__, 'siwp_install');

// check to enable captcha on comment form
if (true == get_option('siwp_enabled_comments', 1)) {
    add_action('comment_form', 'siwp_captcha_html', 10, 1);
    add_action('preprocess_comment', 'siwp_process_comment', 0);
}

// check to enable captcha on signup form
if (true == get_option('siwp_enabled_signup', 1)) {
    add_action('register_form', 'siwp_captcha_html', 99, 1);
    add_action('register_post', 'siwp_process_registration', 0, 3);

    if (function_exists('buddypress')) {
        add_action('bp_signup_profile_fields', 'siwp_captcha_html_bpregiseter', 99, 1);
        add_action('bp_before_registration_submit_buttons', 'siwp_captcha_html_bpregiseter', 99, 1);
        add_action('bp_signup_validate', 'siwp_process_bp_registration', 0);
    }
}

// check to enable captcha on login form
if (true == get_option('siwp_enabled_loginform', 0)) {
    add_action('login_form', 'siwp_captcha_html', 99, 1);

    if (strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
        add_action('login_form_login', 'siwp_process_login', 0);
    }
}

// check to enable captcha on lost password form
if (true == get_option('siwp_enabled_lostpassword', 0)) {
    add_action('lostpassword_form', 'siwp_captcha_html', 99, 1);

    if (strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
        add_action('lostpassword_post', 'siwp_process_login', 0);
    }
}

add_shortcode('siwp_show_captcha', 'siwp_captcha_shortcode');
// end register hooks

// Admin menu and admin functions below...

function siwp_plugin_menu()
{
    $screen = get_current_screen();
    $plugin = plugin_basename(__FILE__);
    $prefix = '';
    if (is_object($screen) && isset($screen->is_network)) {
        $prefix = $screen->is_network ? 'network_admin_' : '';
    }

    add_options_page('Securimage-WP Options', 'Securimage-WP', 'manage_options', 'securimage-wp-options', 'siwp_plugin_options');
    add_action('admin_init', 'siwp_register_settings');
    add_filter("{$prefix}plugin_action_links_{$plugin}", 'siwp_plugin_settings_link', 10, 2);
}

function siwp_plugin_settings_link($links) {
    $settings_link = '<a href="' . siwp_plugin_settings_url() . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

function siwp_plugin_settings_url()
{
    return 'options-general.php?page=securimage-wp-options';
}

function siwp_callback_check_code_length($value) {
    $value = preg_replace('/[^\d]/', '', $value);
    if ((int)$value < 4 || (int)$value > 8) {
        $value = 6;
    }

    return (int)$value;
}

function siwp_callback_check_image_width($value) {
    $value = preg_replace('/[^\d]/', '', $value);
    if ((int)$value < 125 || (int)$value > 500) {
        $value = 215;
    }

    return (int)$value;
}

function siwp_callback_check_image_height($value) {
    $value = preg_replace('/[^\d]/', '', $value);
    if ((int)$value < 40 || (int)$value > 200) {
        $value = 80;
    }

    return (int)$value;
}

function siwp_callback_check_expiration($value) {
    $value = preg_replace('/[^\d]/', '', $value);
    if ((int)$value < 60 || (int)$value > 3600) {
        $value = 900;
    }

    return (int)$value;
}

function siwp_register_settings()
{
    register_setting('securimage-wp-options', 'siwp_code_length', 'siwp_callback_check_code_length');
    register_setting('securimage-wp-options', 'siwp_image_width', 'siwp_callback_check_image_width');
    register_setting('securimage-wp-options', 'siwp_image_height', 'siwp_callback_check_image_height');
    register_setting('securimage-wp-options', 'siwp_image_bg_color');
    register_setting('securimage-wp-options', 'siwp_text_color');
    register_setting('securimage-wp-options', 'siwp_line_color');
    register_setting('securimage-wp-options', 'siwp_num_lines', 'intval');
    register_setting('securimage-wp-options', 'siwp_captcha_expiration', 'siwp_callback_check_expiration');
    register_setting('securimage-wp-options', 'siwp_image_signature');
    register_setting('securimage-wp-options', 'siwp_signature_color');
    register_setting('securimage-wp-options', 'siwp_randomize_background');
    register_setting('securimage-wp-options', 'siwp_show_protected_by');
    register_setting('securimage-wp-options', 'siwp_debug_image');
    register_setting('securimage-wp-options', 'siwp_use_math');
    register_setting('securimage-wp-options', 'siwp_noise_level');
    register_setting('securimage-wp-options', 'siwp_noise_color');
    register_setting('securimage-wp-options', 'siwp_disable_audio');
    register_setting('securimage-wp-options', 'siwp_audio_lang');
    register_setting('securimage-wp-options', 'siwp_flash_bgcol');
    register_setting('securimage-wp-options', 'siwp_flash_icon');
    register_setting('securimage-wp-options', 'siwp_position_fix');
    register_setting('securimage-wp-options', 'siwp_display_sequence');
    register_setting('securimage-wp-options', 'siwp_refresh_text');
    register_setting('securimage-wp-options', 'siwp_use_refresh_text');
    register_setting('securimage-wp-options', 'siwp_captcha_expiration');
    register_setting('securimage-wp-options', 'siwp_css_clsimg');
    register_setting('securimage-wp-options', 'siwp_css_clsinput');
    register_setting('securimage-wp-options', 'siwp_css_clslabel');
    register_setting('securimage-wp-options', 'siwp_css_cssimg');
    register_setting('securimage-wp-options', 'siwp_css_cssinput');
    register_setting('securimage-wp-options', 'siwp_css_csslabel');
    register_setting('securimage-wp-options', 'siwp_dismiss_donate');
    register_setting('securimage-wp-options', 'siwp_has_donated');
    register_setting('securimage-wp-options', 'siwp_enabled_comments');
    register_setting('securimage-wp-options', 'siwp_enabled_signup');
    register_setting('securimage-wp-options', 'siwp_enabled_loginform');
    register_setting('securimage-wp-options', 'siwp_enabled_lostpassword');

    register_setting('securimage-wp-stats', 'siwp_stat_failed');
    register_setting('securimage-wp-stats', 'siwp_stat_passed');
    register_setting('securimage-wp-stats', 'siwp_stat_displayed');
}

function siwp_show_donate()
{
    if ((bool)get_option('siwp_dismiss_donate', 0) === true) {
        return true;
    } else {
        return false;
    }
}

function siwp_get_captcha_expiration()
{
    $value = get_option('siwp_captcha_expiration', 900);
    if (!is_numeric($value) || (int)$value < 1) {
        $value = 900;
    }

    return $value;
}

function siwp_get_language_files()
{
    return array(
        'en'    => 'securimage_audio-en.zip',
        'de'    => 'securimage_audio-de.zip',
        'fr'    => 'securimage_audio-fr.zip',
        'it'    => 'securimage_audio-it.zip',
        'nl'    => 'securimage_audio-nl.zip',
        'pt-br' => 'securimage_audio-pt-2.zip',
        'tr'    => 'securimage_audio-tr.zip',
        'noise' => 'securimage_audio-noise.zip',
    );
}

function siwp_install_language()
{
    $installBase = 'http://www.phpcaptcha.org/downloads/audio/';
    $langFiles   = siwp_get_language_files();
    $lang        = @$_GET['lang'];

    if (!isset($langFiles[$lang])) {
        return __('The language you are attempting to install is not a supported language');
    }

    $downloadUrl = $installBase . $langFiles[$lang];
    $langBase    = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'audio' . DIRECTORY_SEPARATOR;

    if (!is_writable($langBase)) {
        return sprintf(__('The audio directory (%s) is not writable by the server - cannot install language files.'), $langBase);
    }

    $langPath = $langBase . $lang;

    if (!file_exists($langPath)) {
        if (!mkdir($langPath)) {
            return __('Failed to create directory for audio files - cannot install language files');
        }
    }

    $langFile = @file_get_contents($downloadUrl);

    if (!$langFile) {
        $reason = null;
        if (function_exists('error_get_last')) {
            $err    = error_get_last();
            $reason = $err['message'];
        }

        $msg = sprintf(__('The language file could not be downloaded.  You may need to manually download and install the language files from %s'),
            '<a href="' . $downloadUrl . '" target="_blank">' . $downloadUrl . '</a>');

        if ($reason !== null) {
            $msg .= '<br /><br />' . __('Reason:') . ' ' . $reason;
        }

        return $msg;
    }

    require_once dirname(__FILE__) . '/phpziputils/PhpZipUtils.php';

    $zip = new Dapphp_PhpZipUtils_ZipFile();

    // open zip file from memory string
    if (!$zip->openFromString($langFile)) {
        $err = $zip->getStatusString();
        $msg = __('Failed to unzip language file.');
        $msg .= '<br /><br />' . __('Reason:') . ' ' . $err;

        return $msg;
    }

    // iterate over each file/directory in the archive, only extract WAV files
    foreach($zip->getFiles() as $file) {
        if ($file->is_directory) continue; // skip directories
        if ('.wav' != substr($file->name, -4)) continue; // skip non-wav files

        if (!file_put_contents($langPath . DIRECTORY_SEPARATOR . $file->name, $file->data)) {
            $msg = __('Failed to extract contents of language file %s to %s.  Ensure directory is writable by the server.');
            return sprintf($msg, $file->name, $langPath);
        }
    }

    return true;
}

function siwp_plugin_options()
{
    if (!current_user_can('manage_options'))  {
        wp_die( __('You do not have sufficient permissions to access this page.') );
    }

    $rateUrl  = 'https://wordpress.org/support/view/plugin-reviews/securimage-wp#postform';

    $languages = array(
        'en'    => 'English (en)',
        'pt-br' => 'Brazilian Portuguese (pt-BR)',
        'nl'    => 'Dutch (nl)',
        'fr'    => 'French (fr)',
        'de'    => 'German (de)',
        'it'    => 'Italian (it)',
        'tr'    => 'Turkish (tr)',
    );

    if (isset($_GET['action'])) {
        $msg_class = 'updated';

        switch($_GET['action']) {
            case 'purge':
                $num_purged = siwp_purge_captchas();
                $plugin_messages = sprintf(__('%d old CAPTCHAs were removed from the database.'), $num_purged);
                break;

            case 'dismissdonate':
                update_option('siwp_dismiss_donate', 1);
                $plugin_messages = "Thank you for using Securimage-WP.  The donation window will no longer appear.<br /><br />If you have a few minutes, please take a moment to <a href='{$rateUrl}' target='_blank'>rate this plugin</a> if you can.  Thanks!";
                break;

            case 'donated':
                update_option('siwp_dismiss_donate', 1);
                update_option('siwp_has_donated', 1);
                $plugin_messages = __('Thank you very much for your contribution!  Your support is greatly appreciated.');
                break;

            case 'reset-stats':
                update_site_option('siwp_stat_displayed', 0);
                update_site_option('siwp_stat_failed', 0);
                update_site_option('siwp_stat_passed', 0);
                $plugin_messages = __('The plugin statistics have been reset.');
                break;

            case 'install-language':
                @set_time_limit(600);
                @ini_set('memory_limit', '128M');

                $plugin_messages = siwp_install_language();

                if (true === $plugin_messages) {
                    $plugin_messages = __('The audio files were downloaded and installed successfully!');
                } else {
                    $msg_class = 'error';
                }
                break;
        }
    }
?>
    <script type="text/javascript" src="<?php echo siwp_get_plugin_url() ?>jscolor/jscolor.js"></script>
    <div class="wrap">
    <div id="icon-options-general" class="icon32"><br /></div>
    <h2><?php _e('Securimage-WP Options') ?></h2>
    <a href="https://www.phpcaptcha.org/contact" target="_blank"><?php _e('Plugin Support/Contact') ?></a>&nbsp; - &nbsp; <a href="https://www.phpcaptcha.org/download/wordpress-plugin/#respond" target="_blank"><?php _e('Leave a Comment') ?></a> &nbsp; - &nbsp; <a href="<?php echo $rateUrl ?>" target="_blank"><?php _e('Rate This Plugin') ?></a><br/>

    <?php if (!empty($plugin_messages)): ?>
    <div id="message" class="<?php echo $msg_class ?> below-h2"><p>
        <?php echo $plugin_messages; ?>
    </p></div>
    <?php endif; ?>

    <?php $numDisplayed = (int)get_site_option('siwp_stat_displayed'); ?>
    <?php if ($numDisplayed > 0): ?>
    <div style="width: 500px; margin: 10px 10px 20px; padding: 10px 10px 20px; background-color: rgb(242, 242, 242); border: 1px solid rgb(220, 220, 220); border-radius: 8px; text-shadow: 1px 1px 0pt rgb(255, 255, 255); box-shadow: 1px 1px 0pt rgb(255, 255, 255) inset, -1px -1px 0pt rgb(255, 255, 255); position: relative">
        <h3><?php _e('Plugin Stats:') ?></h3>
        <div style="clear: both"></div>
        <strong><?php _e('Number of CAPTCHAs displayed:') ?></strong> <em><?php echo number_format($numDisplayed) ?></em>
        <span style="float: right"><a href="#" onclick="return confirmResetStats()"><?php _e('Reset Statistics') ?></a></span>
        <br />
        <strong><?php _e('Number of failed validations:') ?></strong> <em><?php echo number_format(get_site_option('siwp_stat_failed')) ?></em><br />
        <strong><?php _e('Number of passed validations:') ?></strong> <em><?php echo number_format(get_site_option('siwp_stat_passed')) ?></em><br />
        <strong><?php _e('Number of codes in the database:') ?></strong> <em><?php echo siwp_get_captcha_database_count() ?></em>
        <span style="float: right"><a href="<?php echo siwp_plugin_settings_url() ?>&amp;action=purge"><?php _e('Purge Expired Codes') ?></a></span>

    </div>
    <?php endif; ?>

    <?php if (siwp_show_donate()): ?>
    <div id="donation_plate" style="width: 600px; margin: 10px 10px 20px; padding: 10px 10px 20px; background-color: rgb(242, 242, 242); border: 1px solid rgb(220, 220, 220); border-radius: 8px; text-shadow: 1px 1px 0pt rgb(255, 255, 255); box-shadow: 1px 1px 0pt rgb(255, 255, 255) inset, -1px -1px 0pt rgb(255, 255, 255); position: relative">
        <h4 style="font-size: 1.4em; line-height: 1; margin: 5px 0 3px 0; padding: 0; color: rgb(30, 34, 38); font-weight: bold; font-family: 'Helvetica Neue',Arial,Helvetica,Geneva,sans-serif; text-shadow: 1px 1px 1px #fff; font-style: italic">Donate</h4>

        <div style="float: left; width: 350px; vertical-align: top">
            <p><?php printf(__('If you have found that this plugin has been helpful and saved you time, please consider making a one-time donation.  The requested donation amount is %s.'), '<em><a>$2.49 USD</a></em>') ?></p>
            <p><strong><em><?php _e('Thank you for using this plugin.') ?></em></strong></p>
        </div>
        <div style="float: left; padding-left: 20px;">
            <form action="https://www.paypal.com/cgi-bin/webscr" target="_blank" method="post">
            <input type="hidden" name="cmd" value="_s-xclick">
            <input type="hidden" name="hosted_button_id" value="5QG875L5LXSDG">
            <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
            <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
            </form>
            <a href="http://flattr.com/thing/645565/Securimage-WP-WordPress-Captcha-Plugin" target="_blank"><img style="padding-left: 5px" src="http://api.flattr.com/button/flattr-badge-large.png" alt="Flattr this" title="Flattr this" border="0" /></a>
            <br /><br />
            <a href="?page=securimage-wp-options&amp;action=dismissdonate"><?php _e('No Thanks') ?></a> &nbsp;-&nbsp; <a href="?page=securimage-wp-options&amp;action=donated"><?php _e("I've Already Donated") ?></a>
        </div>
        <div style="clear: both"></div>
    </div>
    <?php endif; ?>

    <form method="post" action="options.php">
    <?php settings_fields('securimage-wp-options'); ?>
    <?php do_settings_sections('securimage-wp-options'); ?>

    <table class="form-table">
        <tr valign="top"><td width="300"><input type="submit" name="submit" value="<?php _e('Save Changes') ?>" /></td><td></td></tr>

        <tr valign="top">
            <th colspan="2" scope="row" style="font-size: 1.4em"><?php _e('Protection Options') ?></th>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="siwp_enabled_comments"><?php _e('Enable on comment form:') ?><br /><span style="font-size: 0.8em"><?php _e('Enable captcha protection on comment form') ?></span></label></th>
            <td><input type="checkbox" name="siwp_enabled_comments" value="1" <?php checked(1, get_option('siwp_enabled_comments', 1)) ?>/></td>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="siwp_enabled_signup"><?php _e('Enable on registration form:') ?><br /><span style="font-size: 0.8em"><?php _e('Enable captcha protecion on registration form') ?></span></label></th>
            <td><input type="checkbox" id="siwp_enabled_signup" name="siwp_enabled_signup" value="1" <?php checked(1, get_option('siwp_enabled_signup', 1)) ?>/></td>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="siwp_enabled_loginform"><?php _e('Enable on login form:') ?><br /><span style="font-size: 0.8em"><?php _e('Enable captcha protecion on login form') ?></span></label></th>
            <td><input type="checkbox" id="siwp_enabled_loginform" name="siwp_enabled_loginform" value="1" <?php checked(1, get_option('siwp_enabled_loginform', 0)) ?>/></td>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="siwp_enabled_lostpassword"><?php _e('Enable on lost password form:') ?><br /><span style="font-size: 0.8em"><?php _e('Enable captcha protecion on the lost password retrieval form') ?></span></label></th>
            <td><input type="checkbox" id="siwp_enabled_lostpassword" name="siwp_enabled_lostpassword" value="1" <?php checked(1, get_option('siwp_enabled_lostpassword', 0)) ?>/></td>
        </tr>

        <tr valign="top">
            <th colspan="2" scope="row" style="font-size: 1.4em"><?php _e('CAPTCHA Image Options') ?></th>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Code Length:') ?><br /><span style="font-size: 0.8em"><?php _e('Does not apply to math CAPTCHA') ?></span></th>
            <td>
                <select name="siwp_code_length">
                    <?php for ($i = 3; $i <= 8; ++$i): ?>
                    <option<?php if ($i == get_option('siwp_code_length', 5)): ?> selected="selected"<?php endif; ?>><?php echo $i ?></option>
                    <?php endfor; ?>
                </select>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Image Width:') ?><br /><span style="font-size: 0.8em"><?php _e('Image width in pixels from 125-500 (Default: 215)') ?></span></th>
            <td><input type="text" name="siwp_image_width" value="<?php echo get_option('siwp_image_width', 215) ?>" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Image Height:') ?><br /><span style="font-size: 0.8em"><?php _e('Image height in pixels from 40-200 (Default: 80)') ?></span></th>
            <td><input type="text" name="siwp_image_height" value="<?php echo get_option('siwp_image_height', 80) ?>" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Image Background Color') ?></th>
            <td><input class="color" type="text" name="siwp_image_bg_color" value="<?php echo get_option('siwp_image_bg_color', 'F2F2F2'); ?>" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Text Color') ?></th>
            <td><input class="color" type="text" name="siwp_text_color" value="<?php echo get_option('siwp_text_color', '7D7D7D'); ?>" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Number of Distortion Lines') ?></th>
            <td><input type="text" name="siwp_num_lines" value="<?php echo get_option('siwp_num_lines', '5'); ?>" size="5" maxlength="2" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Distortion Line Color') ?></th>
            <td><input class="color" type="text" name="siwp_line_color" value="<?php echo get_option('siwp_line_color', '7D7D7D'); ?>" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Noise Level (0-10)') ?></th>
            <td><input type="text" name="siwp_noise_level" value="<?php echo get_option('siwp_noise_level', '3'); ?>" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Noise Color') ?></th>
            <td><input class="color" type="text" name="siwp_noise_color" value="<?php echo get_option('siwp_noise_color', '7D7D7D'); ?>" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Image Signature Text') ?></th>
            <td><input type="text" name="siwp_image_signature" value="<?php echo get_option('siwp_image_signature', ''); ?>" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Signature Text Color') ?></th>
            <td><input class="color" type="text" name="siwp_signature_color" value="<?php echo get_option('siwp_signature_color', '777777'); ?>" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="siwp_randomize_background"><?php _e('Randomize Image Backgrounds') ?></label></th>
            <td><input type="checkbox" id="siwp_randomize_background" name="siwp_randomize_background" value="1" <?php checked(1, get_option('siwp_randomize_background', 0)) ?> /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="siwp_use_math"><?php _e('Use Mathematic Captcha') ?></label></th>
            <td><input type="checkbox" id="siwp_use_math" name="siwp_use_math" value="1" <?php checked(1, get_option('siwp_use_math', 0)) ?> /></td>
        </tr>

        <tr valign="top">
            <th colspan="2" scope="row" style="font-size: 1.4em"><?php _e('Audio CAPTCHA Options') ?></th>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="siwp_disable_audio"><?php _e('Disable Audio CAPTCHA') ?></label></th>
            <td><input type="checkbox" id="siwp_disable_audio" name="siwp_disable_audio" value="1" <?php checked(1, get_option('siwp_disable_audio', 1)) ?> /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Audio Language') ?><br /><span style="font-size: 0.8em"><?php _e('Language to use for the audio CAPTCHA') ?><br /><?php _e('The language files must be installed separately from the plugin before they can be selected') ?></span></th>
            <td><select name="siwp_audio_lang">
            <?php foreach($languages as $lang => $langDisp): ?>
            <option value="<?php echo $lang ?>"<?php if ($lang == get_option('siwp_audio_lang', 'en')): ?> selected="selected"<?php endif; ?>><?php echo $langDisp ?></option>
            <?php endforeach; ?>
            </select>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Installed Languages') ?></th>
            <td>
            <?php $i = 0; foreach($languages as $lang => $langDisp): ?>
                <?php $installUrl = siwp_plugin_settings_url() . '&amp;action=install-language&amp;lang=' . $lang; ?>
                <?php echo $langDisp ?>:
                <?php if (file_exists(dirname(__FILE__) . '/lib/audio/' . $lang . '/A.wav')): ?>
                <?php _e('Installed') ?> <a href="<?php echo $installUrl ?>"><?php _e('Reinstall') ?></a>
                <?php else: ?>
                <span style="color: #f00"><?php _e('Not Installed') ?></span> &nbsp;
                <a href="<?php echo $installUrl ?>"><?php echo _e('Install') ?></a>
                <?php endif; ?>
                <br/>
            <?php endforeach; ?>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Noise Files') ?><br /><span style="font-size: 0.8em"><?php _e('Noise files are optional but can greatly increase the security of the CAPTCHA audio by adding random, background noise to the generated files.') ?></span></th>
            <td>
                <?php
                $installUrl = siwp_plugin_settings_url() . '&amp;action=install-language&amp;lang=noise';
                $noiseFile  = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR .
                             'audio' . DIRECTORY_SEPARATOR . 'noise' . DIRECTORY_SEPARATOR . 'crowd-talking-1.wav';

                if (file_exists($noiseFile)): ?>
                <?php _e('Installed') ?> <a href="<?php echo $installUrl ?>"><?php _e('Reinstall') ?></a>
                <?php else: ?>
                <span style="color: #f00"><?php _e('Not Installed') ?></span> &nbsp;
                <a href="<?php echo $installUrl ?>"><?php echo _e('Install') ?></a>
                <?php endif; ?>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Flash Button Icon URL') ?><br /><span style="font-size: 0.8em"><?php _e('For best results, this should be hosted on the same domain as WordPress') ?></span></th>
            <td><input type="text" name="siwp_flash_icon" value="<?php echo get_option('siwp_flash_icon', siwp_default_flash_icon()); ?>" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Flash Button Background') ?></th>
            <td><input class="color" type="text" name="siwp_flash_bgcol" value="<?php echo get_option('siwp_flash_bgcol', '#ffffff') ?>" /></td>
        </tr>

        <tr valign="top">
            <th colspan="2" scope="row" style="font-size: 1.4em"><?php _e('Miscellaneous Options') ?></th>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Display Sequence') ?><br /><span style="font-size: 0.8em"><?php _e('Control the arrangement of CAPTCHA inputs') ?><br />&quot;captcha&quot; denotes the image captcha, audio, and refresh icon<br />&quot;break&quot; indicates a line break<br />&quot;label&quot; denotes the input label<br />&quot;input&quot; denotes the captcha text input</span></th>
            <td><select name="siwp_display_sequence">
            <?php foreach(siwp_get_sequence_list() as $sequence): ?>
            <option value="<?php echo $sequence ?>"<?php if ($sequence == get_option('siwp_display_sequence', 'captcha-input-label')): ?> selected="selected"<?php endif; ?>><?php echo $sequence ?></option>
            <?php endforeach; ?>
            </select>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="siwp_use_refresh_text"><?php _e('Use Text for Image Refresh') ?></label><br /></th>
            <td>
                <input type="checkbox" id="siwp_use_refresh_text" name="siwp_use_refresh_text" value="1" <?php checked(1, get_option('siwp_use_refresh_text', 0)) ?> />
                &nbsp; Display Text:
                <input type="text" name="siwp_refresh_text" value="<?php echo get_option('siwp_refresh_text', 'Different Image') ?>" />
            </td>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="siwp_position_fix"><?php _e('Fix CAPTCHA Position') ?><br /><span style="font-size: 0.8em"><?php _e('If CAPTCHA shows up below comment submit button, enable this option') ?></span></label></th>
            <td><input type="checkbox" id="siwp_position_fix" name="siwp_position_fix" value="1" <?php checked(1, get_option('siwp_position_fix', 0)) ?> /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><label for="siwp_show_protected_by"><?php _e('Show "Protected By" Link') ?></label></th>
            <td><input type="checkbox" id="siwp_show_protected_by" name="siwp_show_protected_by" value="1" <?php checked(1, get_option('siwp_show_protected_by', 0)) ?> /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('CAPTCHA expiration time') ?><br /><span style="font-size: 0.8em"><?php _e('In seconds, how long before the CAPTCHA expires and is no longer valid') ?></span></th>
            <td><input type="text" name="siwp_captcha_expiration" value="<?php echo siwp_get_captcha_expiration() ?>" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e('Debug Image Errors:') ?></th>
            <td>
                <input type="checkbox" name="siwp_debug_image" value="1" <?php checked(1, get_option('siwp_debug_image', 0)) ?> />
                <a href="<?php echo siwp_get_captcha_image_url() ?>" target="_new"><?php _e('View image directly') ?></a>.<br />
                <span style="font-size: 0.8em">
                    If any PHP errors or warnings are displayed, visit the <a href="http://www.phpcaptcha.org/faq/" target="_new">Securimage FAQ Page</a> to see if the problem is listed.  If not, please file a bug report using the <a href="http://www.phpcaptcha.org/contact/" target="_new">contact</a> page.<br />
                    Use the <a href="<?php echo siwp_get_plugin_url() ?>siwp_test.php" target="_new">Securimage Test Script</a> to verify that your server meets the requirements.
                </span>
            </td>
        </tr>

        <tr valign="top">
            <th colspan="2" scope="row" style="font-size: 1.4em">CSS Styling</th>
        </tr>

        <tr valign="top">
            <th scope="row"><?php printf(__('Class(es) to add to CAPTCHA %s tag'), '&lt;img&gt;') ?></th>
            <td><input type="text" name="siwp_css_clsimg" value="<?php echo get_option('siwp_css_clsimg', ''); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php printf(__('CSS Style to add to CAPTCHA %s tag'), '&lt;img&gt;') ?></th>
            <td><input type="text" name="siwp_css_cssimg" value="<?php echo get_option('siwp_css_cssimg', ''); ?>" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php printf(__('Class(es) to add to CAPTCHA %s tag'), '&lt;input&gt;') ?></th>
            <td><input type="text" name="siwp_css_clsinput" value="<?php echo get_option('siwp_css_clsinput', ''); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php printf(__('CSS Style to add to CAPTCHA %s tag'), '&lt;input&gt;') ?></th>
            <td><input type="text" name="siwp_css_cssinput" value="<?php echo get_option('siwp_css_cssinput', ''); ?>" /></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php printf(__('Class(es) to add to CAPTCHA %s tag'), '&lt;label&gt;') ?></th>
            <td><input type="text" name="siwp_css_clslabel" value="<?php echo get_option('siwp_css_clslabel', ''); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php printf(__('CSS style to add to CAPTCHA %s tag'), '&lt;label&gt;') ?></th>
            <td><input type="text" name="siwp_css_csslabel" value="<?php echo get_option('siwp_css_csslabel', ''); ?>" /></td>
        </tr>
    </table>

    <p class="submit">
    <input type="submit" name="submit" value="<?php _e('Save Changes') ?>" />
    </p>
    </form>

    <p><?php _e('Image Preview:') ?></p>
    <?php echo siwp_captcha_html(0, true, true) ?>
    <?php update_site_option('siwp_stat_displayed', $numDisplayed - 1); // don't count previews ?>

    </div>

    <script type="text/javascript">
    function confirmResetStats() {
        if (confirm('<?php htmlspecialchars(_e('Are you sure you want to reset the plugin statistics?')) ?>')) {
            window.location = window.location = '<?php echo siwp_plugin_settings_url() ?>&action=reset-stats';
            return false;
        } else {
            return false;
        }
    }
    </script>

<?php } // function siwp_plugin_options
