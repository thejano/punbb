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
		'lo_activity_count'			=> array('count', '10'),
		'lo_index_browse'			=> array('bool', '0'),
		'lo_footer_text'			=> array('line', ''),
		'lo_social_links'			=> array('social', ''),
		'lo_custom_css'				=> array('css', ''),
	);
}

// users column => allowed values of the member's own choice (empty / NULL: the board default)
function lo_ext_prefs()
{
	return array('lo_layout' => array('sidebar', 'wide'), 'lo_skin' => array('calm', 'meko'), 'lo_scheme' => array('auto', 'light', 'dark'));
}

// The profile's Appearance fieldset: one select per preference, the board default first
function lo_ext_appearance_fieldset($user, $lang, &$forum_page)
{
	$labels = array(
		'lo_layout' => array($lang['Pref layout'], array('sidebar' => $lang['Layout sidebar'], 'wide' => $lang['Layout wide'])),
		'lo_skin' => array($lang['Pref skin'], array('calm' => $lang['Skin calm'], 'meko' => $lang['Skin meko'])),
		'lo_scheme' => array($lang['Color scheme'], array('auto' => $lang['Scheme auto'], 'light' => $lang['Scheme light'], 'dark' => $lang['Scheme dark'])),
	);

	$forum_page['item_count'] = 0;
	$out = "\t\t\t".'<fieldset class="frm-group group'.++$forum_page['group_count'].' lo-appearance"><legend class="group-legend"><strong>'.$lang['Appearance'].'</strong></legend>'."\n";
	foreach ($labels as $col => $field)
	{
		$options = '<option value="">'.$lang['Board default'].'</option>';
		foreach ($field[1] as $value => $text)
			$options .= '<option value="'.$value.'"'.($user[$col] === $value ? ' selected="selected"' : '').'>'.$text.'</option>';
		$out .= "\t\t\t\t".'<div class="sf-set set'.++$forum_page['item_count'].'"><div class="sf-box select"><label for="fld'.++$forum_page['fld_count'].'"><span>'.$field[0].'</span></label><br />'.
			'<span class="fld-input"><select id="fld'.$forum_page['fld_count'].'" name="form['.$col.']">'.$options.'</select></span></div></div>'."\n";
	}

	return $out."\t\t\t\t".'<p class="lo-appearance-help">'.$lang['Appearance help'].'</p>'."\n\t\t\t".'</fieldset>'."\n";
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

// Plain SVG only, checked as a DOM against an allowlist: SVG-namespace elements from a fixed list, no DOCTYPE, entities or
// processing instructions, no event or style attributes, links only to #fragments, no script-capable values. The logo is only
// ever shown through <img>, which runs no scripts, as a second line of defence.
function lo_ext_svg_ok($svg)
{
	static $elements = array('svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'defs', 'use',
		'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'title', 'desc', 'symbol');

	// character references are decoded by the parser, so they are refused in the raw file; <!DOCTYPE and <!ENTITY likewise
	if (strlen($svg) > 524288 || strpos($svg, '&#') !== false || stripos($svg, '<!') !== false || !class_exists('DOMDocument'))
		return false;

	$prev = libxml_use_internal_errors(true);
	libxml_clear_errors();
	$doc = new DOMDocument();
	$loaded = $doc->loadXML($svg, LIBXML_NONET);	// no LIBXML_NOENT: entities are never substituted
	$errors = libxml_get_errors();
	libxml_clear_errors();
	libxml_use_internal_errors($prev);

	if (!$loaded || $errors || $doc->doctype !== null || !$doc->documentElement || $doc->documentElement->localName !== 'svg')
		return false;

	$walk = array($doc);
	while ($walk)
	{
		$node = array_pop($walk);
		foreach ($node->childNodes as $child)
		{
			if (in_array($child->nodeType, array(XML_PI_NODE, XML_ENTITY_REF_NODE, XML_ENTITY_NODE, XML_DOCUMENT_TYPE_NODE, XML_ENTITY_DECL_NODE), true))
				return false;
			if ($child->nodeType !== XML_ELEMENT_NODE)
				continue;

			if ($child->namespaceURI !== 'http://www.w3.org/2000/svg' || !in_array(strtolower($child->localName), $elements, true))
				return false;

			foreach ($child->attributes as $attr)
			{
				$name = strtolower($attr->localName);
				$value = strtolower(preg_replace('/\s+/', '', $attr->value));
				if (strpos($name, 'on') === 0 || $name === 'style')
					return false;
				if ($name === 'href' && ($value === '' || $value[0] !== '#'))
					return false;
				if (strpos($value, 'javascript:') !== false || strpos($value, 'data:') !== false || strpos($value, '&#') !== false || strpos($value, 'expression(') !== false || strpos($attr->value, '\\') !== false
					|| preg_match('/url\((?![\'"]?#)/', $value))
					return false;
			}

			$walk[] = $child;
		}
	}

	return true;
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
	// this section writes only its own settings
	$form = array_intersect_key($form, lo_ext_settings());
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
				// the field is left empty while an uploaded image is in use: keep it (Remove clears it)
				if ($v == '' && isset($forum_config['o_'.$key]) && lo_ext_is_own_file($forum_config['o_'.$key], 'hero'))
					unset($form[$key]);
				else if ($v != '' && !lo_ext_url_ok($v) && !($v === $forum_config['o_'.$key] && lo_ext_is_own_file($v, 'hero')))
					$errors[] = $lang['Bad url'];
				break;

			case 'refresh':
				if (!ctype_digit($v) || ((int)$v != 0 && ((int)$v < 15 || (int)$v > 600)))
					$errors[] = $lang['Bad refresh'];
				else
					$form[$key] = (string)(int)$v;
				break;

			case 'count':
				if (!ctype_digit($v) || (int)$v < 5 || (int)$v > 30)
					$errors[] = $lang['Bad count'];
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

// After a valid save: each upload is moved to a temporary name in the upload folder first and renamed over its fixed name only
// when every move worked, so a failure never leaves a setting pointing at a deleted file. Files that the new values no longer point
// to (removed, or the hero replaced by a typed URL) are deleted. Returns error messages; on any error nothing is changed.
function lo_ext_store_files(array &$form, array $moves, array $lang)
{
	global $forum_config;

	$tmp = array();
	foreach ($moves as $name => $move)
	{
		$path = FORUM_ROOT.LO_EXT_UPLOAD_DIR.'.'.$name.'-'.bin2hex(random_bytes(6)).'.tmp';
		if (!move_uploaded_file($move[0], $path))
		{
			foreach ($tmp as $t)
				@unlink($t);
			return array($lang['Not writable']);
		}
		$tmp[$name] = $path;
	}

	foreach (lo_ext_uploads() as $name => $up)
	{
		$key = $up[0];
		if (isset($tmp[$name]))
		{
			$file = $name.'.'.$moves[$name][1];
			if (!@rename($tmp[$name], FORUM_ROOT.LO_EXT_UPLOAD_DIR.$file))
			{
				foreach ($tmp as $t)
					@unlink($t);
				return array($lang['Not writable']);
			}
			unset($tmp[$name]);
			@chmod(FORUM_ROOT.LO_EXT_UPLOAD_DIR.$file, 0644);
			// the same name with another extension is the old file
			foreach (glob(FORUM_ROOT.LO_EXT_UPLOAD_DIR.$name.'.*') ?: array() as $old)
				if (basename($old) !== $file)
					@unlink($old);
			$form[$key] = LO_EXT_UPLOAD_DIR.$file.'?v='.time();
		}
		else if (isset($form[$key]) && !lo_ext_is_own_file($form[$key], $name) && isset($forum_config['o_'.$key]) && lo_ext_is_own_file($forum_config['o_'.$key], $name))
			lo_ext_delete_files($name);
	}

	return array();
}
