<?php
if ( empty($php_l10n_strings) )
	return;

function php_l10n_guess_domain($text) {
	global $php_l10n_strings;
	static $domains;

	if ( isset($php_l10n_strings['default'][$text]) )
		return 'default';

	if ( !isset($domains) )
		$domains = array_keys($php_l10n_strings);

	$domain = '';
	foreach ( $domains as $domain ) {
		if ( isset($php_l10n_strings[$domain][$text]) )
			break;
	}

	return $domain;
}

function php_l10n_gettext($translation, $text, $domain = '') {
	global $php_l10n_strings;

	if ( empty($text) )
		return $translation;

	if ( empty($domain) )
		$domain = php_l10n_guess_domain($text);

	if ( isset($php_l10n_strings[$domain][$text]) )
		$translation = $php_l10n_strings[$domain][$text];

	return $translation;
}
add_filter('gettext', 'php_l10n_gettext', 10, 3);

function php_l10n_gettext_with_context($translation, $text, $context, $domain = '') {
	global $php_l10n_strings;

	if ( empty($text) )
		return $translation;

	$index = $context . chr(4) . $text;

	if ( empty($domain) )
		$domain = php_l10n_guess_domain($index);

	if ( isset($php_l10n_strings[$domain][$index]) )
		$translation = $php_l10n_strings[$domain][$index];

	return $translation;
}
add_filter('gettext_with_context', 'php_l10n_gettext_with_context', 10, 4);

function php_l10n_parenthesize_plural_expression($expression) {
	$expression .= ';';
	$res = '';
	$depth = 0;
	for ( $i = 0; $i < strlen($expression); ++$i ) {
		$char = $expression[$i];
		switch ( $char ) {
			case '?':
				$res .= ' ? (';
				$depth++;
				break;
			case ':':
				$res .= ') : (';
				break;
			case ';':
				$res .= str_repeat(')', $depth) . ';';
				$depth= 0;
				break;
			default:
				$res .= $char;
		}
	}
	return rtrim($res, ';');
}

function php_l10n_select_plural_form($number, $domain) {
	global $php_l10n_locale, $php_l10n_strings;
	static $select_plural_form;

	if ( !isset($select_plural_form) ) {
		$plural_forms = eregi("plural-forms: ([^\n]*)\n", $php_l10n_strings[$domain][''], $regs) ? $regs[1] : '';

		if ( preg_match('/nplurals\s*=\s*(\d+)\s*\;\s*plural\s*=\s*(.*?)\;+/', $plural_forms, $matches) ) {
			$nplurals = (int)$matches[1];
			$expression = trim(php_l10n_parenthesize_plural_expression($matches[2]));
		} else {
			$nplurals = 2;
			$expression = 'n != 1';
		}

		$expression = str_replace('n', '$n', $expression);
		$func_body = "
			\$index = (int)($expression);
			return (\$index < $nplurals) ? \$index : $nplurals - 1;";

		$select_plural_form = create_function('$n', $func_body);
	}

	return call_user_func($select_plural_form, $number);
}

function php_l10n_ngettext($translation, $single, $plural, $number, $domain = '') {
	global $php_l10n_strings;

	$index = $single . chr(0) . $plural;

	if ( empty($domain) )
		$domain = php_l10n_guess_domain($index);

	if ( isset($php_l10n_strings[$domain][$index]) ) {
		$translations = explode(chr(0), $php_l10n_strings[$domain][$index]);
		$translation = $translations[php_l10n_select_plural_form($number, $domain)];
	}

	return $translation;
}
add_filter('ngettext', 'php_l10n_ngettext', 10, 5);

function php_l10n_ngettext_with_context($translation, $single, $plural, $number, $context, $domain = '') {
	global $php_l10n_strings;

	$index = $context . chr(4) . $single . chr(0) . $plural;

	if ( empty($domain) )
		$domain = php_l10n_guess_domain($index);

	if ( isset($php_l10n_strings[$domain][$index]) ) {
		$translations = explode(chr(0), $php_l10n_strings[$domain][$index]);
		$translation = $translations[php_l10n_select_plural_form($number, $domain)];
	}

	return $translation;
}
add_filter('ngettext_with_context', 'php_l10n_ngettext_with_context', 10, 6);
?>