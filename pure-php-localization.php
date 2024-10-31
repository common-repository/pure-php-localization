<?php
/*
Plugin Name: Pure PHP Localization
Version: 0.7-trunk
Plugin URI: http://uplift.ru/projects/
Description: Converts gettext binary message catalogs to an array of strings. Allows to save some amount of RAM on a shared hosting server.
Author: Sergey Biryukov
Author URI: http://sergeybiryukov.ru/
*/

$php_l10n_locale = defined('WPLANG') ? WPLANG : '';
$php_l10n_upload_dir = wp_upload_dir();
if ( empty($php_l10n_upload_dir['basedir']) ) {
	$php_l10n_upload_dir['basedir'] = preg_replace('/\/\d{4}\/\d{2}/', '', $php_l10n_upload_dir['path']);
}
$php_l10n_path = "{$php_l10n_upload_dir['basedir']}/" . dirname(plugin_basename(__FILE__));
$php_l10n_filename = "$php_l10n_path/strings-$php_l10n_locale.php";
$php_l10n_filename_front = "$php_l10n_path/strings-{$php_l10n_locale}_front.php";
$php_l10n_filename_lite = "$php_l10n_path/strings-{$php_l10n_locale}_lite.php";
$php_l10n_lang_dir = defined('WP_LANG_DIR') ? WP_LANG_DIR : ABSPATH . LANGDIR;
$php_l10n_wplang_lite = "$php_l10n_lang_dir/{$php_l10n_locale}_lite.mo";
$php_l10n_loaded_files = array();

if ( empty($php_l10n_locale) || !is_writable($php_l10n_upload_dir['basedir']) )
	return;

function php_l10n_create_array() {
	global $l10n, $php_l10n_path, $php_l10n_filename, $php_l10n_loaded_files;

	if ( empty($l10n) )
		return;

	$strings = array();
	foreach ( $l10n as $domain => $catalog ) {
		if ( !empty($l10n[$domain]->entries) && !empty($l10n[$domain]->headers) ) {
			$strings[$domain][''] = '';
			foreach ( $l10n[$domain]->headers as $header => $value ) {
				$strings[$domain][''] .= "$header: $value\n";
			}

			foreach ( $l10n[$domain]->entries as $original => $translation_entry ) {
				if ( isset($translation_entry->plural) )
					$original .= chr(0) . $translation_entry->plural;
				$strings[$domain][$original] = implode(chr(0), $translation_entry->translations);
			}
		} elseif ( !empty($l10n[$domain]->cache_translations) ) {
			foreach ( $l10n[$domain]->cache_translations as $original => $translation ) {
				$strings[$domain][$original] = $translation;
			}
		}
	}

	if ( empty($strings) )
		return;

	$content = sprintf(
		"<?php\n\$php_l10n_strings = %s;\n\n\$php_l10n_files = %s;\n?>",
		var_export($strings, true),
		var_export($php_l10n_loaded_files, true)
	);
	$content = str_replace("\n  ", "\n\t", $content);
	$content = str_replace('\000', chr(0), $content);

	@wp_mkdir_p($php_l10n_path);
	@file_put_contents($php_l10n_filename, $content);
}

function php_l10n_remove_array() {
	global $php_l10n_path, $php_l10n_filename, $php_l10n_filename_front, $php_l10n_filename_lite;

	if ( file_exists($php_l10n_filename) )
		unlink($php_l10n_filename);

	if ( file_exists($php_l10n_filename_front) )
		unlink($php_l10n_filename_front);

	if ( file_exists($php_l10n_filename_lite) )
		unlink($php_l10n_filename_lite);

	if ( is_dir($php_l10n_path) )
		@rmdir($php_l10n_path);

	delete_option('php_l10n_update_needed');
	remove_action('shutdown', 'php_l10n_create_array');
}
register_deactivation_hook(__FILE__, 'php_l10n_remove_array');

function php_l10n_schedule_update() {
	update_option('php_l10n_update_needed', 1);
}
add_action('update_option_active_plugins', 'php_l10n_schedule_update');
add_action('update_option_template', 'php_l10n_schedule_update');

function php_l10n_is_regular_page() {
	$is_regular_page = true;

	if ( !is_admin() && !empty($_GET) )
		$is_regular_page = strpos(implode('', $_GET), 'css') === false;
	elseif ( basename($_SERVER['PHP_SELF']) == 'index-extra.php' )
		$is_regular_page = false;

	return $is_regular_page;
}

function php_l10n_check_mo_for_update($domain, $mofile) {
	global $php_l10n_filename, $php_l10n_strings, $php_l10n_files, $php_l10n_loaded_files;

	if ( !php_l10n_is_regular_page() )
		return;

	$php_l10n_loaded_files[] = $mofile;

	if ( file_exists($php_l10n_filename) && file_exists($mofile) ) {
		if ( filemtime($php_l10n_filename) < filemtime($mofile) ) {
			unset($php_l10n_strings[$domain]);
			php_l10n_schedule_update();
		} elseif ( isset($php_l10n_files) && !in_array($mofile, $php_l10n_files) ) {
			php_l10n_schedule_update();
		}
	}
}

function php_l10n_create_imaginary_reader($domain) {
	global $l10n;

	if ( class_exists('gettext_reader') ) {
		$l10n[$domain] = new gettext_reader(false);
	    $l10n[$domain]->cache_translations = array();
		$l10n[$domain]->table_originals = array();
		$l10n[$domain]->table_translations = array();
	}
}

function php_l10n_use_wplang_lite() {
	global $php_l10n_wplang_lite;

	return !is_admin() && file_exists($php_l10n_wplang_lite);
}

function php_l10n_load_wplang_lite($mofile, $domain) {
	global $php_l10n_wplang_lite;

	if ( $domain == 'default' && php_l10n_use_wplang_lite() )
		$mofile = $php_l10n_wplang_lite;

	return $mofile;
}
add_filter('load_textdomain_mofile', 'php_l10n_load_wplang_lite', 10, 2);

function php_l10n_override_textdomain($false, $domain, $mofile) {
	global $php_l10n_strings, $php_l10n_files, $php_l10n_wplang_lite;

	if ( $domain == 'default' && php_l10n_use_wplang_lite() )
		$mofile = $php_l10n_wplang_lite;

	php_l10n_check_mo_for_update($domain, $mofile);

	return isset($php_l10n_strings[$domain]) && is_array($php_l10n_files) && in_array($mofile, $php_l10n_files);
}
add_filter('override_load_textdomain', 'php_l10n_override_textdomain', 10, 3);

function php_l10n_override_textdomain_legacy($locale) {
	global $php_l10n_locale, $php_l10n_filename, $php_l10n_lang_dir, $php_l10n_strings;

	$plugin_dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : ABSPATH . PLUGINDIR;
	$locale = str_replace('_lite', '', $php_l10n_locale);

	$backtrace = debug_backtrace();
	if ( empty($backtrace) )
		return $locale;

	foreach ( $backtrace as $backtrace_entry ) {
		if ( $backtrace_entry['function'] == 'load_default_textdomain' ) {
			$domain = 'default';

			if ( php_l10n_use_wplang_lite() )
				$locale .= '_lite';

			php_l10n_check_mo_for_update($domain, "$php_l10n_lang_dir/$locale.mo");
			if ( isset($php_l10n_strings[$domain]) ) {
				$locale = '';
				php_l10n_create_imaginary_reader($domain);
			}
		} elseif ( $backtrace_entry['function'] == 'load_plugin_textdomain' ) {
			$domain = $backtrace_entry['args'][0];
			$abs_rel_path = !empty($backtrace_entry['args'][1]) ? $backtrace_entry['args'][1] : false;
			$plugin_rel_path = !empty($backtrace_entry['args'][2]) ? $backtrace_entry['args'][2] : false;

			if ( false !== $plugin_rel_path	)
				$path = "$plugin_dir/" . trim($plugin_rel_path, '/');
			else if ( false !== $abs_rel_path )
				$path = ABSPATH . trim($abs_rel_path, '/');
			else
				$path = $plugin_dir;

			php_l10n_check_mo_for_update($domain, "$path/$domain-$locale.mo");
			if ( isset($php_l10n_strings[$domain]) ) {
				$locale = '';
				php_l10n_create_imaginary_reader($domain);
			}
		} elseif ( $backtrace_entry['function'] == 'load_theme_textdomain' ) {
			$domain = $backtrace_entry['args'][0];
			$path = !empty($backtrace_entry['args'][1]) ? $backtrace_entry['args'][1] : false;

			$path = empty($path) ? get_template_directory() : $path;
	
			php_l10n_check_mo_for_update($domain, "$path/$locale.mo");
			if ( isset($php_l10n_strings[$domain]) ) {
				$locale = '';
				php_l10n_create_imaginary_reader($domain);
			}
		}
	}

	return $locale;
}
if ( !class_exists('POMO_FileReader') ) {
	add_filter('locale', 'php_l10n_override_textdomain_legacy');
}

function php_l10n_file_exists($filename) {
	$file_exists = false;

	if ( file_exists($filename) ) {
		$content = file_get_contents($filename);
		$file_exists = strpos($content, '?>');
	}

	return $file_exists;
}

function php_l10n_init() {
	global $php_l10n_filename, $php_l10n_filename_lite, $php_l10n_filename_front, $php_l10n_strings, $php_l10n_files;

	if ( php_l10n_use_wplang_lite() )
		$php_l10n_filename = $php_l10n_filename_lite;
	elseif ( !is_admin() )
		$php_l10n_filename = $php_l10n_filename_front;

	if ( function_exists('wpll_load_mofile') )
		remove_filter('load_textdomain_mofile', 'wpll_load_mofile');

	$php_l10n_update_needed = get_option('php_l10n_update_needed');
	if ( php_l10n_file_exists($php_l10n_filename) && empty($php_l10n_update_needed) ) {
		include($php_l10n_filename);
		include('gettext-filters.php');
	} elseif ( php_l10n_is_regular_page() ) {
		add_action('shutdown', 'php_l10n_create_array');
		update_option('php_l10n_update_needed', 0);
	}
}
add_action('plugins_loaded', 'php_l10n_init', 5);
?>