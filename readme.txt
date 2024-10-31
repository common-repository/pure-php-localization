=== Pure PHP Localization ===
Contributors: SergeyBiryukov
Tags: l10n, translations, php, memory, optimization
Requires at least: 2.1
Tested up to: 3.0.5
Stable tag: 0.6.1

Converts gettext binary message catalogs to an array of strings.

== Description ==

Converts gettext binary message catalogs to an array of strings. Allows to save some amount of RAM on a shared hosting server.
Works with plugin and theme textdomains as well as with the default.

Thanks to AlexPTS for the idea.

== Installation ==

1. Upload `pure-php-localization` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Changelog ==

= 0.6.1 =
* Fixed working with legacy WordPress versions
* Fixed translation of an empty string

= 0.6 =
* Added support for WordPress 3.0 Multisite

= 0.5.1 =
* The array is now stored inside the uploads directory

= 0.5 =
* Added support for [WPLANG Lite](http://wordpress.org/extend/plugins/wplang-lite/)
* Optimized synchronization with .mo files

= 0.4 =
* Added support for plugin and theme textdomains

= 0.3 =
* Added compatibility with WordPress 2.1+
* Fixed selection of plural forms

= 0.2 =
* Improved synchronization of string array with .mo file
* Added checking for .mo file existence

= 0.1 =
* Initial release
