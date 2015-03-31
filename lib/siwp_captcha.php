<?php

/*  Copyright (C) 2015 Drew Phillips  (http://phpcaptcha.org/download/securimage-wp.zip)

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

require_once dirname(__FILE__) . '/../../../../wp-load.php'; // backwards "lib/securimage-wp/plugins/wp-content/"

if (get_option('siwp_debug_image', 0) == 1) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    if (!defined('WP_DEBUG'))
        define('WP_DEBUG', 1);
} else {
    ini_set('display_errors', 0);
}

require_once dirname(__FILE__) . '/securimage.php';

$captchaId = (isset($_GET['id']) && strlen($_GET['id']) == 40) ?
              $_GET['id'] :
              sha1(uniqid($_SERVER['REMOTE_ADDR'] . $_SERVER['REMOTE_PORT']));

$captcha_type = (get_option('siwp_use_math', 0) == 1) ? 1 : 0;

$options   = array(
    'code_length'     => get_option('siwp_code_length', 6),
    'image_width'     => get_option('siwp_image_width', 215),
    'image_height'    => get_option('siwp_image_height', 80),
    'image_bg_color'  => get_option('siwp_image_bg_color', 'FFFFFF'),
    'text_color'      => get_option('siwp_text_color', '000000'),
    'line_color'      => get_option('siwp_line_color', '000000'),
    'num_lines'       => get_option('siwp_num_lines', '6'),
    'image_signature' => get_option('siwp_image_signature', ''),
    'signature_color' => get_option('siwp_signature_color', '777777'),
    'noise_level'     => get_option('siwp_noise_level', 3),
    'noise_color'     => get_option('siwp_noise_color', '000000'),
    'captcha_type'    => $captcha_type,
    'captchaId'       => $captchaId,
    'no_session'      => true,
    'use_database'    => false,
);

if (get_option('siwp_randomize_background', 0) == 1) {
    $img->background_directory = dirname(__FILE__) . '/backgrounds/';
}

if (!is_numeric($options['noise_level']) ||
    $options['noise_level'] < 0 ||
    $options['noise_level'] > 10) {
    $options['noise_level'] = 0;
}

$img = new Securimage($options);

$table_name = $wpdb->prefix . 'securimagewp';

$result = siwp_get_code_from_database($captchaId);

if ($result == null) {
    $code = siwp_generate_code($captchaId, $img);

    $code_display = $code['display'];
} else {
    $code_display = $result->code_display;
}

$img->display_value = $code_display;

//$img->image_width         = 275;
//$img->image_height        = 90;
//$img->perturbation        = 0.9; // 1.0 = high distortion, higher numbers = more distortion
/*
$img->image_bg_color        = new Securimage_Color("#{$image_bg_color}");
$img->text_color            = new Securimage_Color("#{$text_color}");
$img->num_lines             = $num_lines;
$img->line_color            = new Securimage_Color("#{$line_color}");
$img->signature_color       = new Securimage_Color("#{$signature_color}");
$img->image_signature       = $image_signature;
$img->draw_lines_over_text  = true;
$img->noise_level           = (int)$noise_level;
$img->noise_color           = $noise_color;
*/


$img->show(); // alternate use:  $img->show('/path/to/background_image.jpg');
