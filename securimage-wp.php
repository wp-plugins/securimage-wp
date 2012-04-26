<?php
/*
Plugin Name: Securimage-WP
Plugin URI: http://phpcaptcha.org/download/wordpress-plugin
Description: Adds CAPTCHA protection to comment forms on posts and pages
Author: Drew Phillips
Version: 3.2
Author URI: http://www.phpcaptcha.org/
*/

/*  Copyright (C) 2012 Drew Phillips  (http://phpcaptcha.org/download/securimage-wp)

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
}

function siwp_captcha_html()
{
	$show_protected_by = get_option('siwp_show_protected_by', 1);
	$disable_audio	   = get_option('siwp_disable_audio', 0);
	$flash_bgcol	   = get_option('siwp_flash_bgcol', '#ffffff');
	$flash_icon		   = get_option('siwp_flash_icon', siwp_default_flash_icon());
	$position_fix	   = get_option('siwp_position_fix', 0);
	$refresh_text	   = get_option('siwp_refresh_text', 'Different Image');
	$use_refresh_text  = get_option('siwp_use_refresh_text', 0);
	$imgclass		   = get_option('siwp_css_clsimg', '');
	$labelclass		   = get_option('siwp_css_clslabel', '');
	$inputclass		   = get_option('siwp_css_clsinput', '');
	$imgstyle		   = get_option('siwp_css_cssimg');
	$labelstyle		   = get_option('siwp_css_csslabel');
	$inputstyle		   = get_option('siwp_css_cssinput');
	$expireTime		   = siwp_get_captcha_expiration();
	$display_sequence  = get_option('siwp_display_sequence', 'captcha-input-label');
	$display_sequence  = preg_replace('/\s|\(.*?\)/', '', $display_sequence);
	$captchaId		   = sha1(uniqid($_SERVER['REMOTE_ADDR'] . $_SERVER['REMOTE_PORT']));
	$plugin_url		   = siwp_get_plugin_url();
	
	$captcha_html = "<div id=\"siwp_captcha_input\">\n";
	$captcha_html .=
	"<script type=\"text/javascript\">
	<!--
	function siwp_refresh() {
	    // get new captcha id, refresh the image w/ new id, and update form input with new id
		var cid = siwp_genid();
		document.getElementById('input_siwp_captcha_id').value = cid;
		document.getElementById('securimage_captcha_image').src = '{$plugin_url}lib/siwp_captcha.php?id=' + cid;

		// update flash button with new id
		var obj = document.getElementById('siwp_obj');
		obj.setAttribute('data', obj.getAttribute('data').replace(/[a-zA-Z0-9]{40}$/, cid));
		var par = document.getElementById('siwp_param'); // this was a comment...
		par.value = par.value.replace(/[a-zA-Z0-9]{40}$/, cid);

		// replace old flash w/ new one using new id
		var newObj = obj.cloneNode(true);
		obj.parentNode.insertBefore(newObj, obj);
		obj.parentNode.removeChild(obj);
	}
	function siwp_genid() {
	    // generate a random id
		var cid = '', chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		for (var c = 0; c < 40; ++c) { cid += chars.charAt(Math.floor(Math.random() * chars.length)); }
		return cid;
	};
	var siwp_interval = setInterval(siwp_refresh, " . ($expireTime * 1000) . ");
	-->
	</script>
	";
	
	$sequence = explode('-', $display_sequence);
	foreach($sequence as $part) {
		switch($part) {
			case 'break':
				$captcha_html .= "<br />\n";
				break;
				
			case 'captcha':
			{
				$captcha_html .= '<div style="float: left">';
				$captcha_html .= '<img id="securimage_captcha_image" src="' .
								 siwp_get_captcha_image_url() .
								 '?id=' . $captchaId . '" alt="CAPTCHA Image" style="vertical-align: middle;' .
								 ($imgstyle != '' ?
								 ' ' . htmlspecialchars($imgstyle) :
								 '') . '" ' .
								 ($imgclass != '' ?
								 'class="' . htmlspecialchars($imgclass) . '" ' :
								 '') . 
								 "/>";

				if ($show_protected_by) {
					$captcha_html .= '<br /><a href="http://www.phpcaptcha.org/" ' .
									 'target="_new" style="font-size: 12px; ' .
									 'font-style: italic" class="' .
									 'swip_protected_by">Protected by ' .
									 'Securimage-WP</a>' . "\n";
				}
				
				$captcha_html .= "</div>\n";

				if (!$disable_audio) {
					 $captcha_html .= '<div style="float: left">';
					 $captcha_html .= '<object id="siwp_obj" type="application/x-shockwave-flash"' .
									  ' data="' . siwp_get_plugin_url() . 
									  'lib/securimage_play.swf?bgcol=#' . $flash_bgcol .
									  '&amp;icon_file=' . urlencode($flash_icon)  .
									  '&amp;audio_file=' . urlencode(siwp_get_plugin_url()) .
									  'lib/siwp_play.php?id=' . $captchaId . '" height="32" width="32">' .
									  "\n" .
									  '<param id="siwp_param" name="movie" value="' . siwp_get_plugin_url() . 
									  'lib/securimage_play.swf?bgcol=#' . $flash_bgcol  . 
									  '&amp;icon_file=' . urlencode($flash_icon) .
									  '&amp;audio_file=' . urlencode(siwp_get_plugin_url()) .
									  'lib/siwp_play.php?id=' . $captchaId . '">' .
									  "\n</object>\n<br />";
				}
				
				if ($use_refresh_text) $captcha_html .= '[ ';
				$captcha_html .= '<a tabindex="-1" style="border-style: none;"' .
								 ' href="#" title="Refresh Image" ' .
								 'onclick="siwp_refresh(); return false">' .
								 ($use_refresh_text == false ?
								 '<img src="' . siwp_get_plugin_url() .
								 'lib/images/refresh.png" alt="Reload Image"' .
								 ' onclick="this.blur()" style="height: 32px; width: 32px"' .
								 ' align="bottom" />' :
								 $refresh_text
								 ) .
								 '</a>';
				if ($use_refresh_text) $captcha_html .= ' ]';
				
				$captcha_html .= '</div><div style="clear: both;"></div>' . "\n";
				
				break;
			}

			case 'input':
				$captcha_html .= '<input type="hidden" id="input_siwp_captcha_id" name="siwp_captcha_id" value="' . $captchaId . '" />' .
								 '<input id="siwp_captcha_value" ' .
								 'name="siwp_captcha_value" size="10" ' .
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
				$captcha_html .= '<label for="siwp_captcha_value"' . 
	  							 ($labelclass != '' ?
								 ' class="' . $labelclass . '"' :
								 '') .
								 ($labelstyle != '' ?
								 ' style="' . htmlspecialchars($labelstyle) . '"' :
								 '') .
								 '>' .
								 'Enter Code</label> <span class="required">*</span>' . "\n";
				break;
		}
	}
	
	$captcha_html .= "</div>\n";

	if ($position_fix) {
		$captcha_html .= 
		"
		<script type=\"text/javascript\">
		<!--
		var commentSubButton = document.getElementById('comment');
	  	var csbParent = commentSubButton.parentNode;
		var captchaDiv = document.getElementById('siwp_captcha_input');
		csbParent.appendChild(captchaDiv, commentSubButton);
		-->
		</script>
		<noscript>
		<style tyle='text/css'>#submit {display: none}</style><br /><input name='submit' type='submit' id='submit-alt' tabindex='6' value='Submit Comment' />
		</noscript>
		";
	}
	
	echo $captcha_html;
} // function siwp_captcha_html

function siwp_check_captcha($commentdata)
{
	// admin comment reply using ajax from admin panel
	if ( isset($_POST['_ajax_nonce-replyto-comment']) && check_ajax_referer('replyto-comment', '_ajax_nonce-replyto-comment')) {
		return $commentdata;
	}
	
	// pingback or trackback comment
	if ( (!empty($commentdata['comment_type'])) &&
			in_array($commentdata['comment_type'], array('pingback', 'trackback'))) {
		return $commentdata;
	}
	
	// admin comment from comment form
	if (is_user_logged_in() && current_user_can('administrator')) {
		return $commentdata;
	}
	
	$valid	   = false; // valid captcha entry?
	$code	   = '';	// code entered
	$captchaId = '';	// captcha ID to check
	
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
			wp_die( __('Error: The security token is invalid.') );
		}
	} else {
		// missing token
		wp_die( __('Error: Missing security token from submission.') );
	}
	
	if (strlen($code) > 0) {
		// validate the code if we received an input
		if (siwp_validate_captcha_by_id($captchaId, $code) == true) {
			$valid = true;
		}
	}
	
	if (!$valid) {
		// captcha was typed wrong or was left empty
		wp_die( __('Error: The security code entered was incorrect.  Please go <a href="javascript:history.go(-1)">back</a> and try again.') );
	}

	return $commentdata;
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
	$expiry_time = siwp_get_captcha_expiration();
	
	$res = $wpdb->query(
		$wpdb->prepare("DELETE FROM $table_name WHERE UNIX_TIMESTAMP() - created >= %d", $expiry_time)
	);
	
	if ($res !== false) {
		return $res;
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
		'break-captcha-label-input (Twenty Eleven Style)',
		'break-captcha-label-break-input (Twenty Ten Style)',
		'break-captcha-input-label',
		'break-captcha-break-input-label',
		'captcha-break-label-input',
		'captcha-label-input',
		'captcha-break-input-label',
		'captcha-input-label',
	);
}

require_once ABSPATH . '/wp-includes/pluggable.php';

if (!is_user_logged_in() || !current_user_can('administrator')) {
	add_action('comment_form', 'siwp_captcha_html');
}

add_action('preprocess_comment', 'siwp_check_captcha', 0);


// Admin menu and admin functions below...

add_action('admin_menu', 'siwp_plugin_menu');
register_activation_hook(__FILE__, 'siwp_install');


function siwp_plugin_menu()
{
	$screen = get_current_screen();
	$plugin = plugin_basename(__FILE__);
	$prefix = $screen->is_network ? 'network_admin_' : '';
	
	add_options_page('Securimage-WP Options', 'Securimage-WP', 'manage_options', 'securimage-wp-options', 'siwp_plugin_options');
	add_action('admin_init', 'siwp_register_settings');
	add_filter("{$prefix}plugin_action_links_{$plugin}", 'siwp_plugin_settings_link', 10, 2);
}

function siwp_plugin_settings_link($links) {
	$settings_link = '<a href="options-general.php?page=securimage-wp-options">Settings</a>';
	array_unshift($links, $settings_link);
	return $links;
}

function siwp_register_settings()
{
	register_setting('securimage-wp-options', 'siwp_code_length', function($value) {
		$value = preg_replace('/[^\d]/', '', $value);
		if ((int)$value < 4 || (int)$value > 8) {
			$value = 6;
		}
		
		return (int)$value;
	});
	
	register_setting('securimage-wp-options', 'siwp_image_width', function($value) {
		$value = preg_replace('/[^\d]/', '', $value);
		if ((int)$value < 125 || (int)$value > 500) {
			$value = 215;
		}
	
		return (int)$value;
	});
	
	register_setting('securimage-wp-options', 'siwp_image_height', function($value) {
		$value = preg_replace('/[^\d]/', '', $value);
		if ((int)$value < 40 || (int)$value > 200) {
			$value = 80;
		}
	
		return (int)$value;
	});
	
	register_setting('securimage-wp-options', 'siwp_image_bg_color');
	register_setting('securimage-wp-options', 'siwp_text_color');
	register_setting('securimage-wp-options', 'siwp_line_color');
	register_setting('securimage-wp-options', 'siwp_num_lines', 'intval');
	
	register_setting('securimage-wp-options', 'siwp_captcha_expiration', function($value) {
		$value = preg_replace('/[^\d]/', '', $value);
		if ((int)$value < 60 || (int)$value > 3600) {
			$value = 900;
		}
		
		return (int)$value;
	});
	
	register_setting('securimage-wp-options', 'siwp_image_signature');
	register_setting('securimage-wp-options', 'siwp_signature_color');
	register_setting('securimage-wp-options', 'siwp_randomize_background');
	register_setting('securimage-wp-options', 'siwp_show_protected_by');
	register_setting('securimage-wp-options', 'siwp_debug_image');
	register_setting('securimage-wp-options', 'siwp_use_math');
	register_setting('securimage-wp-options', 'siwp_noise_level');
	register_setting('securimage-wp-options', 'siwp_noise_color');
	register_setting('securimage-wp-options', 'siwp_disable_audio');
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
}

function siwp_show_donate()
{
	if (get_option('siwp_dismiss_donate', 0) == 0) {
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

function siwp_plugin_options()
{
	if (!current_user_can('manage_options'))  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}
	
	if (isset($_GET['action'])) {
		switch($_GET['action']) {
			case 'purge':
				$num_purged = siwp_purge_captchas();
				$plugin_messages = "$num_purged old CAPTCHAs were removed from the database.";
				break;
				
			case 'dismissdonate':
				update_option('siwp_dismiss_donate', 1);
				$plugin_messages = "Thanks for using this plugin.  Tell your friends!";
				break;
				
			case 'donated':
				update_option('siwp_dismiss_donate', 1);
				update_option('siwp_has_donated', 1);
				$plugin_messages = "Thank you very much for your contribution!  Your support is greatly appreciated.";
				break;
				
		}
	}
?>
	<script type="text/javascript" src="<?php echo siwp_get_plugin_url() ?>jscolor/jscolor.js"></script>
	<div class="wrap">
	<div id="icon-options-general" class="icon32"><br /></div>
	<h2>Securimage-WP Options</h2>
	<span style="margin: 5px 0 0 45px"><a href="http://www.phpcaptcha.org/download/wordpress-plugin/#respond" target="_blank">Leave a Comment</a> &nbsp; - &nbsp; <a href="http://phpcaptcha.org/contact" target="_blank">Support/Contact</a></span>
	<div style="clear: both"></div>
	
	<?php if (!empty($plugin_messages)): ?>
	<div id="message" class="updated below-h2"><p>
		<?php echo $plugin_messages; ?>
	</p></div>
	<?php endif; ?>
	
	<?php if (siwp_show_donate()): ?>
	<div id="donation_plate" style="width: 600px; margin: 10px 10px 20px; padding: 10px 10px 20px; background-color: rgb(242, 242, 242); border: 1px solid rgb(220, 220, 220); border-radius: 8px; text-shadow: 1px 1px 0pt rgb(255, 255, 255); box-shadow: 1px 1px 0pt rgb(255, 255, 255) inset, -1px -1px 0pt rgb(255, 255, 255); position: relative">
		<h4 style="font-size: 1.4em; line-height: 1; margin: 5px 0 3px 0; padding: 0; color: rgb(30, 34, 38); font-weight: bold; font-family: 'Helvetica Neue',Arial,Helvetica,Geneva,sans-serif; text-shadow: 1px 1px 1px #fff; font-style: italic">Donate</h4>
		
		<div style="float: left; width: 350px; vertical-align: top">
			<p>If you have found that this plugin has been helpful and saved you time, please consider making a one-time donation.  The requested donation amount is <em><a>$2.49 USD</a></em>.</p>
			<p><strong><em>Thank you for using this plugin.</em></strong></p>
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
			<a href="?page=securimage-wp-options&amp;action=dismissdonate">No Thanks</a> &nbsp;-&nbsp; <a href="?page=securimage-wp-options&amp;action=donated">I've Already Donated</a>
		</div>
		<div style="clear: both"></div>
	</div>
	<?php endif; ?>	
	
	<table class="form-table">
		<tr valign="top">
			<th colspan="2" scope="row" style="font-size: 1.4em">Operations</th>
		</tr>
		<tr valign="top">
			<td valign="top"><a href="<?php echo $_SERVER['PHP_SELF'] ?>?page=securimage-wp-options&amp;action=purge">Purge expired codes now</a></td>
		</tr>
	</table>
	
	
	<form method="post" action="options.php">
	<?php settings_fields('securimage-wp-options'); ?>
	<?php do_settings_sections('securimage-wp-options'); ?>
	
	<table class="form-table">
		<tr valign="top"><td width="300"></td><td></td></tr>
		<tr valign="top">
			<th colspan="2" scope="row" style="font-size: 1.4em">CAPTCHA Image Options</th>
		</tr>
		
		<tr valign="top">
			<th scope="row">Code Length:<br /><span style="font-size: 0.8em">Does not apply to math CAPTCHA</span></th>
			<td>
				<select name="siwp_code_length">
					<?php for ($i = 3; $i <= 8; ++$i): ?>
					<option<?php if ($i == get_option('siwp_code_length', 6)): ?> selected="selected"<?php endif; ?>><?php echo $i ?></option>
					<?php endfor; ?>
				</select>
			</td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Image Width:<br /><span style="font-size: 0.8em">Image width in pixels from 125-500 (Default: 215)</span></th>
			<td><input type="text" name="siwp_image_width" value="<?php echo get_option('siwp_image_width', 215) ?>" /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Image Height:<br /><span style="font-size: 0.8em">Image height in pixels from 40-200 (Default: 80)</span></th>
			<td><input type="text" name="siwp_image_height" value="<?php echo get_option('siwp_image_height', 80) ?>" /></td>
		</tr>		
		
		<tr valign="top">
			<th scope="row">Image Background Color</th>
			<td><input class="color" type="text" name="siwp_image_bg_color" value="<?php echo get_option('siwp_image_bg_color', 'FFFFFF'); ?>" /></td>
		</tr>
		 
		<tr valign="top">
			<th scope="row">Text Color</th>
			<td><input class="color" type="text" name="siwp_text_color" value="<?php echo get_option('siwp_text_color', '000000'); ?>" /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Number of Distortion Lines</th>
			<td><input type="text" name="siwp_num_lines" value="<?php echo get_option('siwp_num_lines', '6'); ?>" size="5" maxlength="2" /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Distortion Line Color</th>
			<td><input class="color" type="text" name="siwp_line_color" value="<?php echo get_option('siwp_line_color', '000000'); ?>" /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Noise Level (0-10)</th>
			<td><input type="text" name="siwp_noise_level" value="<?php echo get_option('siwp_noise_level', '3'); ?>" /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Noise Color</th>
			<td><input class="color" type="text" name="siwp_noise_color" value="<?php echo get_option('siwp_noise_color', '000000'); ?>" /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Image Signature Text</th>
			<td><input type="text" name="siwp_image_signature" value="<?php echo get_option('siwp_image_signature', ''); ?>" /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Signature Text Color</th>
			<td><input class="color" type="text" name="siwp_signature_color" value="<?php echo get_option('siwp_signature_color', '777777'); ?>" /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Randomize Image Backgrounds</th>
			<td><input type="checkbox" name="siwp_randomize_background" value="1" <?php checked(1, get_option('siwp_randomize_background', 0)) ?> /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Use Mathematic Captcha</th>
			<td><input type="checkbox" name="siwp_use_math" value="1" <?php checked(1, get_option('siwp_use_math', 0)) ?> /></td>
		</tr>
		
		<tr valign="top">
			<th colspan="2" scope="row" style="font-size: 1.4em">Flash Button Options</th>
		</tr>
		
		<tr valign="top">
			<th scope="row">Disable Audio CAPTCHA</th>
			<td><input type="checkbox" name="siwp_disable_audio" value="1" <?php checked(1, get_option('siwp_disable_audio', 0)) ?> /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Flash Button Icon URL<br /><span style="font-size: 0.8em">For best results, this should be hosted on the same domain as WordPress</span></th>
			<td><input type="text" name="siwp_flash_icon" value="<?php echo get_option('siwp_flash_icon', siwp_default_flash_icon()); ?>" /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Flash Button Background Color</th>
			<td><input class="color" type="text" name="siwp_flash_bgcol" value="<?php echo get_option('siwp_flash_bgcol', '#ffffff') ?>" /></td>
		</tr>
		
		<tr valign="top">
			<th colspan="2" scope="row" style="font-size: 1.4em">Miscellaneous Options</th>
		</tr>
		
		<tr valign="top">
			<th scope="row">Display Sequence<br /><span style="font-size: 0.8em">Control the arrangement of CAPTCHA inputs<br />&quot;captcha&quot; denotes the image captcha, audio, and refresh icon<br />&quot;break&quot; indicates a line break<br />&quot;label&quot; denotes the input label<br />&quot;input&quot; denotes the captcha text input</span></th>
			<td><select name="siwp_display_sequence">
			<?php foreach(siwp_get_sequence_list() as $sequence): ?>
			<option value="<?php echo $sequence ?>"<?php if ($sequence == get_option('siwp_display_sequence', 'captcha-input-label')): ?> selected="selected"<?php endif; ?>><?php echo $sequence ?></option>
			<?php endforeach; ?>
			</select>
			</td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Use Text for Image Refresh<br /></th>
			<td>
				<input type="checkbox" name="siwp_use_refresh_text" value="1" <?php checked(1, get_option('siwp_use_refresh_text', 0)) ?> />
				&nbsp; Display Text:
				<input type="text" name="siwp_refresh_text" value="<?php echo get_option('siwp_refresh_text', 'Different Image') ?>" />
			</td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Fix CAPTCHA Position<br /><span style="font-size: 0.8em">If CAPTCHA shows up below comment submit button, enable this option</span></th>
			<td><input type="checkbox" name="siwp_position_fix" value="1" <?php checked(1, get_option('siwp_position_fix', 0)) ?> /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Show "Protected By" Link</th>
			<td><input type="checkbox" name="siwp_show_protected_by" value="1" <?php checked(1, get_option('siwp_show_protected_by', 1)) ?> /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">CAPTCHA expiration time<br /><span style="font-size: 0.8em">In seconds, how long before the CAPTCHA expires and is no longer valid</span></th>
			<td><input type="text" name="siwp_captcha_expiration" value="<?php echo siwp_get_captcha_expiration() ?>" /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Debug Image Errors:</th>
			<td>
				<input type="checkbox" name="siwp_debug_image" value="1" <?php checked(1, get_option('siwp_debug_image', 0)) ?> />
				Click to <a href="<?php echo siwp_get_captcha_image_url() ?>" target="_new">view image directly</a>.<br />
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
			<th scope="row">Class(es) to add to CAPTCHA &lt;img&gt; tag</th>
			<td><input type="text" name="siwp_css_clsimg" value="<?php echo get_option('siwp_css_clsimg', ''); ?>" /></td>
		</tr>
		<tr valign="top">
			<th scope="row">CSS Style to add to CAPTCHA &lt;img&gt; tag</th>
			<td><input type="text" name="siwp_css_cssimg" value="<?php echo get_option('siwp_css_cssimg', ''); ?>" /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Class(es) to add to CAPTCHA &lt;input&gt; tag</th>
			<td><input type="text" name="siwp_css_clsinput" value="<?php echo get_option('siwp_css_clsinput', ''); ?>" /></td>
		</tr>
		<tr valign="top">
			<th scope="row">CSS Style to add to CAPTCHA &lt;input&gt; tag</th>
			<td><input type="text" name="siwp_css_cssinput" value="<?php echo get_option('siwp_css_cssinput', ''); ?>" /></td>
		</tr>
		
		<tr valign="top">
			<th scope="row">Class(es) to add to CAPTCHA &lt;label&gt; tag</th>
			<td><input type="text" name="siwp_css_clslabel" value="<?php echo get_option('siwp_css_clslabel', ''); ?>" /></td>
		</tr>
		<tr valign="top">
			<th scope="row">CSS style to add to CAPTCHA &lt;label&gt; tag</th>
			<td><input type="text" name="siwp_css_csslabel" value="<?php echo get_option('siwp_css_csslabel', ''); ?>" /></td>
		</tr>
	</table>

	<p class="submit">
	<input type="submit" name="submit" value="<?php _e('Save Changes') ?>" />
	</p>
	</form>
	
	<p>Image Preview:</p>
	<?php echo siwp_captcha_html() ?>
	
	</div>

<?php } // function siwp_plugin_options
