<?php
/**
 * LightOxygen settings: the setting list, validation and upload handling.
 *
 * @package lightoxygen_settings
 */

if (!defined('FORUM')) die();

define('LO_EXT_UPLOAD_DIR', 'img/lightoxygen/');
define('LO_EXT_MAX_UPLOAD', 2097152);

// form key (config row "o_<key>") => array(type, default[, allowed values])
function lo_ext_settings()
{
	return array(
		'lo_default_layout'			=> array('choice', 'sidebar', array('sidebar', 'wide')),
		'lo_default_skin'			=> array('choice', 'calm', array('calm', 'meko')),
		'lo_allow_switch'			=> array('bool', '1'),
		'lo_color_scheme'			=> array('choice', 'auto', array('auto', 'light', 'dark')),
		'lo_accent'					=> array('hex', ''),
		'lo_font'					=> array('choice', 'default', array('default', 'system', 'bahij')),
		'lo_hero_mode'				=> array('choice', 'guests', array('guests', 'all', 'none')),
		'lo_hero_title'				=> array('line', ''),
		'lo_hero_text'				=> array('line', ''),
		'lo_hero_bg'				=> array('url', ''),
		'lo_show_member_avatars'	=> array('bool', '1'),
		'lo_logo'					=> array('file', ''),
		'lo_favicon'				=> array('file', ''),
		'lo_category_colors'		=> array('bool', '1'),
		'lo_show_stats'				=> array('bool', '1'),
		'lo_activity_refresh'		=> array('refresh', '60'),
		'lo_footer_text'			=> array('line', ''),
		'lo_social_links'			=> array('social', ''),
		'lo_custom_css'				=> array('css', ''),
	);
}

// upload name => array(config key, allowed IMAGETYPE_* => extension, svg allowed)
function lo_ext_uploads()
{
	return array(
		'hero'		=> array('lo_hero_bg', array(IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'), false),
		'logo'		=> array('lo_logo', array(IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'), true),
		'favicon'	=> array('lo_favicon', array(IMAGETYPE_PNG => 'png', IMAGETYPE_ICO => 'ico'), false),
	);
}

function lo_ext_bahij_available()
{
	return is_file(FORUM_ROOT.'style/LightOxygen/assets/fonts/BahijMyriadArabicRegular.woff2');
}

function lo_ext_url_ok($url)
{
	return preg_match('#^https?://[^\s"\'<>\\\\]+$#i', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// A stored upload path: img/lightoxygen/<name>.<ext>?v=<time>
function lo_ext_is_own_file($value, $name)
{
	return (bool)preg_match('#^'.preg_quote(LO_EXT_UPLOAD_DIR, '#').preg_quote($name, '#').'\.(png|jpg|webp|svg|ico)\?v=\d+$#', $value);
}

// Plain SVG only: no scripts, event handler attributes, foreign content, entities or links that leave the file
function lo_ext_svg_ok($svg)
{
	if (!preg_match('#<svg[\s>]#i', $svg))
		return false;

	return !preg_match('#<script|<foreignobject|<!entity|@import|<iframe|<embed|<object|\son[a-z]+\s*=|javascript:|(?:xlink:)?href\s*=\s*["\']?\s*(?!\#)[^\s"\'>]|url\(\s*["\']?\s*(?!\#)#i', $svg);
}

function lo_ext_delete_files($name)
{
	foreach (glob(FORUM_ROOT.LO_EXT_UPLOAD_DIR.$name.'.*') ?: array() as $file)
		@unlink($file);
}

// Validates $form (the posted settings) in place. Returns array(errors, moves): moves are uploads to store once nothing failed.
function lo_ext_validate(array &$form, array $lang)
{
	global $forum_config;

	$errors = $moves = array();
	$labels = array('lo_default_layout' => 'Default layout', 'lo_default_skin' => 'Default skin', 'lo_color_scheme' => 'Color scheme', 'lo_font' => 'Font',
		'lo_hero_mode' => 'Hero mode', 'lo_hero_title' => 'Hero title', 'lo_hero_text' => 'Hero text', 'lo_footer_text' => 'Footer text', 'lo_custom_css' => 'Custom css');

	foreach (lo_ext_settings() as $key => $def)
	{
		list($type, $default) = $def;
		$label = isset($labels[$key]) ? $lang[$labels[$key]] : $key;

		// unchecked boxes are not posted
		if ($type == 'bool')
		{
			$form[$key] = (isset($form[$key]) && $form[$key] == '1') ? '1' : '0';
			continue;
		}

		// uploads are set below, never typed in
		if ($type == 'file' || !isset($form[$key]))
		{
			unset($form[$key]);
			continue;
		}

		$v = $form[$key];
		switch ($type)
		{
			case 'choice':
				if (!in_array($v, $def[2], true) || ($v == 'bahij' && !lo_ext_bahij_available()))
					$errors[] = sprintf($lang['Bad choice'], $label);
				break;

			case 'hex':
				if ($v != '' && !preg_match('/^#[0-9a-f]{6}$/i', $v))
					$errors[] = $lang['Bad accent'];
				$form[$key] = strtolower($v);
				break;

			case 'line':
				$form[$key] = $v = forum_trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $v));
				if (utf8_strlen($v) > 255)
					$errors[] = sprintf($lang['Too long'], $label);
				break;

			case 'url':
				if ($v != '' && !lo_ext_url_ok($v) && !($v === $forum_config['o_'.$key] && lo_ext_is_own_file($v, 'hero')))
					$errors[] = $lang['Bad url'];
				break;

			case 'refresh':
				if (!ctype_digit($v) || ((int)$v != 0 && ((int)$v < 15 || (int)$v > 600)))
					$errors[] = $lang['Bad refresh'];
				else
					$form[$key] = (string)(int)$v;
				break;

			case 'social':
				$lines = array();
				foreach (preg_split('/\R/', $v) as $line)
				{
					if (forum_trim($line) == '')
						continue;
					$parts = array_map('forum_trim', explode('|', $line, 2));
					if (count($parts) != 2 || $parts[0] == '' || utf8_strlen($parts[0]) > 50 || !lo_ext_url_ok($parts[1]))
						$errors['social'] = $lang['Bad social'];
					$lines[] = implode('|', $parts);
				}
				if (count($lines) > 5)
					$errors['social'] = $lang['Bad social'];
				$form[$key] = implode("\n", $lines);
				break;

			case 'css':
				$form[$key] = str_replace("\r\n", "\n", $v);
				if (strlen($v) > 60000)
					$errors[] = sprintf($lang['Too long'], $label);
				break;
		}
	}

	// remove buttons
	$remove = isset($_POST['lo_remove']) && is_array($_POST['lo_remove']) ? $_POST['lo_remove'] : array();

	foreach (lo_ext_uploads() as $name => $up)
	{
		list($key, $types, $svg) = $up;
		$label = $lang[$name == 'hero' ? 'Hero upload' : ($name == 'logo' ? 'Logo upload' : 'Favicon upload')];

		if (isset($remove[$name]))
			$form[$key] = '';

		$f = isset($_FILES['lo_'.$name.'_file']) && is_array($_FILES['lo_'.$name.'_file']) ? $_FILES['lo_'.$name.'_file'] : null;
		if (!$f || !is_int($f['error']) || $f['error'] == UPLOAD_ERR_NO_FILE)
			continue;

		if ($f['error'] == UPLOAD_ERR_INI_SIZE || $f['error'] == UPLOAD_ERR_FORM_SIZE || ($f['error'] == UPLOAD_ERR_OK && $f['size'] > LO_EXT_MAX_UPLOAD))
		{
			$errors[] = sprintf($lang['Too big'], $label);
			continue;
		}
		if ($f['error'] != UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']))
		{
			$errors[] = sprintf($lang['Bad upload'], $label);
			continue;
		}

		$info = @getimagesize($f['tmp_name']);
		if ($info && isset($types[$info[2]]))
			$ext = $types[$info[2]];
		else if ($svg && !$info && lo_ext_svg_ok((string)file_get_contents($f['tmp_name'])))
			$ext = 'svg';
		else
		{
			$errors[] = ($svg && !$info && preg_match('#<svg[\s>]#i', (string)file_get_contents($f['tmp_name']))) ? $lang['Bad svg'] : sprintf($lang['Bad upload'], $label);
			continue;
		}

		$moves[$name] = array($f['tmp_name'], $ext, $key);
	}

	if ($moves && !lo_ext_ensure_dir())
		$errors[] = $lang['Not writable'];

	return array($errors, $moves);
}

function lo_ext_ensure_dir()
{
	$dir = FORUM_ROOT.LO_EXT_UPLOAD_DIR;
	if (!is_dir($dir) && !@mkdir($dir, 0755, true))
		return false;
	if (!is_file($dir.'index.html'))
		@file_put_contents($dir.'index.html', "<html>\n<head>\n<title>.</title>\n</head>\n<body>\n.\n</body>\n</html>");

	return is_writable($dir);
}

// After a valid save: store the uploads under their fixed names (the old file of each name goes first) and drop
// files that the new values no longer point to (removed, or the hero replaced by a typed URL).
function lo_ext_store_files(array &$form, array $moves)
{
	global $forum_config;

	foreach (lo_ext_uploads() as $name => $up)
	{
		$key = $up[0];
		if (isset($moves[$name]))
		{
			lo_ext_delete_files($name);
			$target = LO_EXT_UPLOAD_DIR.$name.'.'.$moves[$name][1];
			if (move_uploaded_file($moves[$name][0], FORUM_ROOT.$target))
			{
				@chmod(FORUM_ROOT.$target, 0644);
				$form[$key] = $target.'?v='.time();
			}
		}
		else if (isset($form[$key]) && !lo_ext_is_own_file($form[$key], $name) && isset($forum_config['o_'.$key]) && lo_ext_is_own_file($forum_config['o_'.$key], $name))
			lo_ext_delete_files($name);
	}
}
