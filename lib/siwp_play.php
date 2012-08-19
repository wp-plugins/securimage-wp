<?php

/*
 Plugin Name: Securimage-WP
Plugin URI: http://phpcaptcha.org/download/wordpress-plugin
Description: Adds CAPTCHA protection to comment forms on posts and pages
Author: Drew Phillips
Version: 3.2-WP
Author URI: http://www.phpcaptcha.org/
License: GPL2
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

require_once dirname(__FILE__) . '/securimage.php';

$captchaId = (isset($_GET['id']) && strlen($_GET['id']) == 40) ?
             $_GET['id'] :
             sha1(uniqid($_SERVER['REMOTE_ADDR'] . $_SERVER['REMOTE_PORT']));

$options = array(
	'captchaId' => $captchaId,
	'no_session' => true,
	'use_database' => false
);

$img = new Securimage($options);

set_error_handler(array(&$img, 'errorHandler')); // set this early, WP omits a lot of warnings and errors

require_once dirname(__FILE__) . '/../../../../wp-load.php'; // backwards "lib/securimage-wp/plugins/wp-content/"

if (get_option('siwp_disable_audio', 0) == 1) {
	exit;
}

$result = siwp_get_code_from_database($captchaId);

if ($result == null) {
	$code = siwp_generate_code($captchaId, $img);

	$code_display = $code['display'];
} else {
	$code_display = $result->code_display;
}

$img->display_value = $code_display;


// To use an alternate language, uncomment the following and download the files from phpcaptcha.org
// $img->audio_path = $img->securimage_path . '/audio/es/';

// If you have more than one captcha on a page, one must use a custom namespace
// $img->namespace = 'form2';

$img->outputAudioFile();
