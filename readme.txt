=== Securimage-WP ===
Contributors: drew010
Donate link: http://www.phpcaptcha.org/donate/
Tags: CAPTCHA, comments, spam protection, comment form
Requires at least: 3.0
Tested up to: 3.3
Stable tag: 3.2

Securimage-WP adds powerful CAPTCHA protection to comment forms on posts and pages to help prevent comment spam from getting onto your site.  CAPTCHA images are highly customizable, and support audio output.

== Description ==

Securimage-WP utilizes the powerful CAPTCHA protection of [Securimage Captcha](http://phpcaptcha.org/ "Securimage PHP CAPTCHA") to add protection to your WordPress comment forms.

From your WordPress Settings menu, you can easily customize all aspects of the CAPTCHA image to match your site's look, as well as customize the security features of the CAPTCHA.

Securimage-WP also has the ability to stream secure, high-quality, dynamic audio CAPTCHAs to visitors.

Addtional Features Include:

*	Customize code-length, image dimensions, colors and distortion factors from a menu
*	Supports word or math based CAPTCHA images and audio
*	Add a custom signature to your images
*	Customize icon used in Flash button for streaming audio
*	Easily add CSS classes and styles to the CAPTCHA inputs
*	Select the sequence of the CAPTCHA inputs to match your site layout
*	Allows pingbacks and trackbacks, and replies from administration panel
*	Visitors do not need cookies enabled, stores codes in a database table

Requirements:

*	WordPress 3.0 or greater
*	Requires PHP 5.2+ with GD and FreeType

About This Plugin:

This plugin was developed by Drew Phillips, the developer of [Securimage PHP CAPTCHA](http://phpcaptcha.org/).  Securimage is completely free and open-source for the community and your use, as is this WordPress plugin.  If you find either of these things useful, please consider [donating](http://phpcaptcha.org/donate).  Thank you for using this plugin!

== Installation ==

Installation of Securimage-WP is simple.

1. From the `Plugins` menu, select `Add New` and then `Upload`.  Select the .zip file containing Securimage-WP.  Alternatively, you can upload the `securimage-wp` directory to your `/wp-content/plugins` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Customize the CAPTCHA options from `Securimage-WP` under the WordPress `Settings` menu.

== Frequently Asked Questions ==

= What are the requirements? =

Securimage-WP requires `PHP 5.2+`, `GD2`, `FreeType`, and `WordPress 3+`.
If you install Securimage-WP, there is a test script that will tell you whether or not your system meets the requirements.

= The CAPTCHA image is not displaying =

From the Securimage-WP settings menu, enable the `Debug Image Errors` option, save the settings, and then click the link labeled `View Image Directly`.  Ideally, this will reveal any error messages that may be causing the image generation to fail.  Try to troubleshoot the error, or contact us for assistance.

= The refresh button does not work =

Javascript must be enabled for the refresh buttons to work.  Make sure Javascript is enabled or check for errors that may prevent it from functioning.

= I noticed the image refresh by itself when I was looking at my comment form =

CAPTCHA codes have expiration times in order to reduce the amount of time spammers have to break the CAPTCHA.  The default time is 15 minutes.  After this time lapses, the CAPTCHA refreshes since it is no longer valid.  You can customize this setting in the options menu.

== Screenshots ==

1. Securimage-WP shown on a comment form
2. A math CAPTCHA with custom text instead of a refresh button in the Twenty Ten theme
3. A CAPTCHA customized to use a CSS border and margin
4. Admin options to control image appearance
5. Miscellanous options for captcha functionality and look

== Changelog ==

= 3.2-WP =
* Initial release of WordPress plugin

== Upgrade Notice ==

None yet!