<?php

// A board setting from the lightoxygen_settings extension (config row $key), or $default while the extension is not installed or
// disabled (its co_common hook defines LO_SETTINGS). Reads the cached $forum_config only.
function lo_setting(string $key, $default)
{
	global $forum_config;

	return (defined('LO_SETTINGS') && isset($forum_config[$key])) ? $forum_config[$key] : $default;
}

// $value when it is one of $allowed, else the first of them
function lo_pick($value, array $allowed)
{
	return in_array($value, $allowed, true) ? $value : $allowed[0];
}

// A stored upload (img/lightoxygen/<name>.<ext>?v=<time>) or an http(s) URL as an absolute URL; '' for anything else
function lo_setting_url($value, $name)
{
	global $base_url;

	if (preg_match('#^img/lightoxygen/'.$name.'\.(png|jpg|webp|svg|ico)\?v=\d+$#', $value))
		return $base_url.'/'.$value;

	return preg_match('#^https?://[^\s"\'<>\\\\]+$#i', $value) ? $value : '';
}

function lo_logo_url()
{
	$url = lo_setting_url(lo_setting('o_lo_logo', ''), 'logo');
	return $url !== '' ? $url : lo_theme_url().'/assets/logo.svg';
}

// data-theme (light / dark; auto follows the system) and the accent colour with derived shades, for <html>
function lo_html_attr()
{
	$out = '';
	$scheme = lo_scheme();
	if ($scheme !== 'auto')
		$out .= ' data-theme="'.$scheme.'"';

	$accent = strtolower((string)lo_setting('o_lo_accent', ''));
	if (preg_match('/^#[0-9a-f]{6}$/', $accent))
		$out .= ' style="--color-accent-500:'.$accent.';--color-accent-600:'.$accent.';--color-accent-400:color-mix(in srgb,'.$accent.' 75%,#fff)'.
			';--color-accent-700:color-mix(in srgb,'.$accent.' 80%,#000);--color-accent-800:color-mix(in srgb,'.$accent.' 65%,#000)"';

	return $out;
}

function lo_font()
{
	$font = lo_pick(lo_setting('o_lo_font', 'default'), array('default', 'system', 'bahij'));
	// the Bahij files are not shipped with the theme (licence); without them the option falls back to the default
	return ($font === 'bahij' && !is_file(FORUM_ROOT.'style/LightOxygen/assets/fonts/BahijMyriadArabicRegular.woff2')) ? 'default' : $font;
}

// After the theme stylesheet: the font choice, then the admin's custom CSS (it cannot close the <style> element)
function lo_head_style()
{
	$css = '';
	$font = lo_font();
	if ($font === 'system')
		$css .= ':root{--font-sans:ui-sans-serif,system-ui,sans-serif}';
	else if ($font === 'bahij')
	{
		$dir = lo_theme_url().'/assets/fonts/';
		$css .= '@font-face{font-family:"Bahij Myriad Arabic";src:url("'.lo_css_str($dir.'BahijMyriadArabicRegular.woff2').'") format("woff2");font-weight:400;font-display:swap}';
		if (is_file(FORUM_ROOT.'style/LightOxygen/assets/fonts/BahijMyriadArabicBold.woff2'))
			$css .= '@font-face{font-family:"Bahij Myriad Arabic";src:url("'.lo_css_str($dir.'BahijMyriadArabicBold.woff2').'") format("woff2");font-weight:600 700;font-display:swap}';
		$css .= ':root{--font-sans:"Bahij Myriad Arabic",ui-sans-serif,system-ui,sans-serif}';
	}

	// repeated, so a split sequence such as </st</styleyle cannot join up again
	$custom = (string)lo_setting('o_lo_custom_css', '');
	while (stripos($custom, '</style') !== false)
		$custom = str_ireplace('</style', '', $custom);
	if (forum_trim($custom) !== '')
		$css .= "\n".$custom;

	return $css !== '' ? '<style id="lo-settings">'.$css.'</style>' : '';
}

// Extra body classes from the settings
function lo_body_class()
{
	return (lo_setting('o_lo_category_colors', '1') == '1' ? '' : ' lo-no-cat-colors').(lo_setting('o_lo_show_member_avatars', '1') == '1' ? '' : ' lo-no-avatars');
}

// The footer line and up to five social links (label|url per line), all escaped
function lo_footer()
{
	$out = '';
	$text = forum_trim((string)lo_setting('o_lo_footer_text', ''));
	if ($text !== '')
		$out .= '<p class="lo-footer-text" dir="auto">'.forum_htmlencode($text).'</p>';

	$links = array();
	foreach (array_slice(preg_split('/\R/', (string)lo_setting('o_lo_social_links', '')), 0, 5) as $line)
	{
		$parts = array_map('forum_trim', explode('|', $line, 2));
		if (count($parts) == 2 && $parts[0] !== '' && preg_match('#^https?://[^\s"\'<>]+$#i', $parts[1]))
			$links[] = '<a href="'.forum_htmlencode($parts[1]).'" rel="noopener me" dir="auto">'.
				'<svg class="lo-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M10 14a4 4 0 006 0l3-3a4 4 0 00-6-6l-1 1M14 10a4 4 0 00-6 0l-3 3a4 4 0 006 6l1-1"/></svg>'.
				'<span>'.forum_htmlencode($parts[0]).'</span></a>';
	}
	if ($links)
		$out .= '<nav class="lo-social">'.implode('', $links).'</nav>';

	return $out;
}

function lo_theme_url()
{
	global $base_url;

	return $base_url.'/style/LightOxygen';
}

// Dev server tags when dist/hot exists, otherwise the built assets from the Vite manifest.
function lo_asset_tags()
{
	$dir = FORUM_ROOT.'style/LightOxygen/dist/';
	$url = lo_theme_url().'/dist/';

	if (is_file($dir.'hot'))
	{
		$origin = rtrim(forum_trim(file_get_contents($dir.'hot')), '/');
		return '<script type="module" src="'.forum_htmlencode($origin).'/@vite/client"></script>'."\n".
			'<script type="module" src="'.forum_htmlencode($origin).'/src/main.jsx"></script>';
	}

	$manifest = is_file($dir.'.vite/manifest.json') ? json_decode(file_get_contents($dir.'.vite/manifest.json'), true) : null;
	if (!is_array($manifest) || !isset($manifest['src/main.jsx']))
		return '<!-- LightOxygen: run npm run build in style/LightOxygen -->';

	$entry = $manifest['src/main.jsx'];
	$tags = array();
	foreach (isset($entry['css']) ? $entry['css'] : array() as $css)
		$tags[] = '<link rel="stylesheet" href="'.forum_htmlencode($url.$css).'" />';
	$tags[] = '<script type="module" src="'.forum_htmlencode($url.$entry['file']).'"></script>';

	return implode("\n", $tags);
}

function lo_icon($id)
{
	// 24x24 outline paths, shared stroke attributes
	static $paths = array(
		'navindex' => 'M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10',
		'navuserlist' => 'M16 11a3 3 0 100-6 3 3 0 000 6zM8 11a3 3 0 100-6 3 3 0 000 6zM2 20c0-3 3-5 6-5s6 2 6 5M14 15c3 0 8 1 8 5',
		'navrules' => 'M6 3h9l4 4v14H6zM14 3v5h5M9 13h7M9 17h7',
		'navsearch' => 'M11 18a7 7 0 100-14 7 7 0 000 14zM20 20l-4-4',
		'navregister' => 'M12 5v14M5 12h14',
		'navlogin' => 'M14 4h6v16h-6M3 12h12M11 8l4 4-4 4',
		'navprofile' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21c0-4 4-6 8-6s8 2 8 6',
		'navadmin' => 'M12 3l8 3v6c0 5-4 8-8 9-4-1-8-4-8-9V6z',
		'navlogout' => 'M10 4H4v16h6M21 12H9M17 8l4 4-4 4',
		'navpm' => 'M3 6h18v12H3zM3 7l9 7 9-7',
		'nav_pun_pm' => 'M3 6h18v12H3zM3 7l9 7 9-7',
	);

	if (!isset($paths[$id]))
		return '';

	return '<svg class="lo-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.$paths[$id].'"/></svg>';
}

// The stock nav items, each with the theme class and an icon; ids, active state and extension items are kept.
function lo_nav()
{
	if (!preg_match_all('#<li\b([^>]*)>(.*?)</li>#s', generate_navlinks(), $items, PREG_SET_ORDER))
		return '';

	$out = array();
	foreach ($items as $item)
	{
		$id = preg_match('#\bid="([^"]*)"#', $item[1], $m) ? $m[1] : '';
		$class = preg_match('#\bclass="([^"]*)"#', $item[1], $m) ? $m[1] : '';

		$out[] = '<li'.($id !== '' ? ' id="'.$id.'"' : '').' class="lo-nav-item'.($class !== '' ? ' '.$class : '').'">'.lo_icon($id).$item[2].'</li>';
	}

	return '<ul class="lo-nav-list">'."\n".implode("\n", $out)."\n".'</ul>';
}

function lo_auth()
{
	global $forum_user, $forum_url, $lang_common;

	if ($forum_user['is_guest'])
		return '<div class="lo-auth">'.
			'<a class="lo-btn lo-btn-ghost" href="'.forum_link($forum_url['login']).'">'.$lang_common['Login'].'</a>'.
			'<a class="lo-btn lo-btn-primary" href="'.forum_link($forum_url['register']).'">'.$lang_common['Register'].'</a>'.
			'</div>';

	$links = '<a href="'.forum_link($forum_url['user'], $forum_user['id']).'">'.lo_icon('navprofile').$lang_common['Profile'].'</a>';
	if ($forum_user['is_admmod'])
		$links .= '<a href="'.forum_link($forum_url['admin_index']).'">'.lo_icon('navadmin').$lang_common['Admin'].'</a>';
	$links .= '<a href="'.forum_link($forum_url['logout'], array($forum_user['id'], generate_form_token('logout'.$forum_user['id']))).'">'.lo_icon('navlogout').$lang_common['Logout'].'</a>';

	$avatar = generate_avatar_markup($forum_user['id'], $forum_user['avatar'], $forum_user['avatar_width'], $forum_user['avatar_height'], $forum_user['username']);

	return '<details class="lo-auth lo-user-menu"><summary>'.$avatar.'<span>'.forum_htmlencode($forum_user['username']).'</span></summary>'.
		'<div class="lo-user-links">'.$links.'</div></details>';
}

function lo_search($class = 'lo-search')
{
	global $forum_user, $forum_url, $lang_common;

	if ($forum_user['g_read_board'] != '1' || $forum_user['g_search'] != '1')
		return '';

	$action = forum_link($forum_url['search']);
	$props = array('action' => $action, 'placeholder' => $lang_common['Search'], 'topic_url' => forum_link($forum_url['topic'], array(987654321, '')), 'list_id' => $class.'-list');

	return '<div class="'.$class.'" data-island="search-box" data-props=\''.forum_htmlencode(json_encode($props)).'\'>'.
		'<form method="get" action="'.forum_htmlencode($action).'" role="search">'.
		'<input type="hidden" name="action" value="search" />'.
		'<input type="search" name="keywords" maxlength="100" placeholder="'.forum_htmlencode($lang_common['Search']).'" aria-label="'.forum_htmlencode($lang_common['Search']).'" />'.
		'</form></div>';
}

// Escapes a value for a double-quoted CSS string (also keeps it from closing the <style> element)
function lo_css_str($v)
{
	return str_replace(array('\\', '"', "\n", "\r", '<'), array('\\\\', '\\"', '\\a ', '\\a ', '\\3c '), $v);
}

function lo_meta()
{
	global $base_url, $forum_config;

	// shortcut: pretty API URLs need .htaccess, which the PHP built-in server ignores
	$api = (is_file(FORUM_ROOT.'api/.htaccess') && PHP_SAPI !== 'cli-server') ? $base_url.'/api/v1' : $base_url.'/api/index.php?route=';

	$out = '<meta name="api-base" content="'.forum_htmlencode($api).'" />'."\n".
		'<meta name="api-csrf" content="'.forum_htmlencode(generate_form_token('api')).'" />';

	$favicon = lo_setting_url(lo_setting('o_lo_favicon', ''), 'favicon');
	if ($favicon !== '')
		$out .= "\n".'<link rel="icon" href="'.forum_htmlencode($favicon).'" />';

	// translated labels for the quick reply toolbar, which main.jsx mounts without a server element of its own
	if (defined('FORUM_PAGE') && FORUM_PAGE === 'viewtopic')
		$out .= "\n".'<template id="lo-qr-props" data-props=\''.forum_htmlencode(json_encode(array('labels' => lo_labels()))).'\'></template>';

	// core's user list prints no avatars and the body is not available here, so the real ones are matched to the
	// profile links by CSS (the default comes from the stylesheet). The query mirrors userlist.php lines 28-48 and 214-231 (filters, order, 50 per page).
	if (defined('FORUM_PAGE') && FORUM_PAGE === 'userlist')
	{
		global $forum_db, $forum_url, $forum_user, $db_type;
		$like = ($db_type == 'pgsql') ? 'ILIKE' : 'LIKE';
		$show_post_count = ($forum_config['o_show_post_count'] == '1' || $forum_user['is_admmod']);
		$where = 'u.id > 1 AND u.group_id != '.FORUM_UNVERIFIED;
		if ($forum_user['g_search_users'] == '1' && isset($_GET['username']) && is_string($_GET['username']) && $_GET['username'] != '-' && $_GET['username'] != '')
			$where .= ' AND u.username '.$like.' \''.$forum_db->escape(str_replace('*', '%', $_GET['username'])).'\'';
		$group = (!isset($_GET['show_group']) || intval($_GET['show_group']) < -1 && intval($_GET['show_group']) > 2) ? -1 : intval($_GET['show_group']);
		if ($group > -1)
			$where .= ' AND u.group_id='.$group;
		$sort_by = (!isset($_GET['sort_by']) || $_GET['sort_by'] != 'username' && $_GET['sort_by'] != 'registered' && ($_GET['sort_by'] != 'num_posts' || !$show_post_count)) ? 'username' : $_GET['sort_by'];
		$sort_dir = (!isset($_GET['sort_dir']) || strtoupper($_GET['sort_dir']) != 'ASC' && strtoupper($_GET['sort_dir']) != 'DESC') ? 'ASC' : strtoupper($_GET['sort_dir']);
		$result = $forum_db->query_build(array('SELECT' => 'COUNT(u.id)', 'FROM' => 'users AS u', 'WHERE' => $where)) or error(__FILE__, __LINE__);
		$pages = ceil($forum_db->result($result) / 50);
		$page = (!isset($_GET['p']) || !is_numeric($_GET['p']) || $_GET['p'] <= 1 || $_GET['p'] > $pages) ? 1 : intval($_GET['p']);

		$rules = '';
		$result = $forum_db->query_build(array('SELECT' => 'u.id, u.avatar, u.avatar_width, u.avatar_height', 'FROM' => 'users AS u', 'WHERE' => $where, 'ORDER BY' => $sort_by.' '.$sort_dir.', u.id ASC', 'LIMIT' => (50 * ($page - 1)).', 50')) or error(__FILE__, __LINE__);
		while ($u = $forum_db->fetch_assoc($result))
			if ($u['avatar'] > 0 && preg_match('/ src="([^"]+)"/', generate_avatar_markup($u['id'], $u['avatar'], $u['avatar_width'], $u['avatar_height']), $m))
				$rules .= '#brd-userlist td.tc0 a[href="'.lo_css_str(html_entity_decode(forum_link($forum_url['user'], $u['id']))).'"]::before{background-image:url("'.lo_css_str(html_entity_decode($m[1])).'")}';
		if ($rules !== '')
			$out .= "\n".'<style>'.$rules.'</style>';
	}

	return $out;
}

function lo_labels()
{
	global $forum_user;

	$all = array(
		'English' => array('activity' => 'Activity', 'recent' => 'Recent topics', 'online' => 'Online members', 'guests' => 'guests', 'updated' => 'updated',
			'browse' => 'Browse', 'filters' => 'Filters', 'recent_week' => 'Recent', 'my_posts' => 'My posts', 'subscriptions' => 'Subscriptions',
			'stats' => 'Statistics', 'members' => 'Members', 'topics' => 'Topics', 'posts' => 'Posts', 'newest' => 'Newest member', 'sidebar' => 'Sidebar',
			'layout' => 'Layout', 'layout_sidebar' => 'Sidebar layout', 'layout_wide' => 'Wide layout',
			'view' => 'View', 'skin' => 'Look', 'skin_calm' => 'Calm look', 'skin_meko' => 'Meko look',
			'scheme' => 'Colour scheme', 'scheme_auto' => 'Follow the system', 'scheme_light' => 'Light', 'scheme_dark' => 'Dark',
			'pinned' => 'Pinned', 'more' => 'more online', 'reply' => 'reply', 'replies' => 'replies', 'active' => 'Most active members',
			'bbcode' => 'Formatting', 'bold' => 'Bold', 'italic' => 'Italic', 'underline' => 'Underline', 'quote' => 'Quote', 'code' => 'Code', 'link' => 'Link', 'image' => 'Image', 'list' => 'List'),
		'Kurdish' => array('activity' => 'چالاکی', 'recent' => 'بابەتە نوێیەکان', 'online' => 'ئەندامانی سەرهێڵ', 'guests' => 'میوان', 'updated' => 'نوێکرایەوە',
			'browse' => 'بەشەکان', 'filters' => 'پاڵاوتن', 'recent_week' => 'ئەم هەفتەیە', 'my_posts' => 'پەیامەکانم', 'subscriptions' => 'بەشداربوونەکانم',
			'stats' => 'ئامار', 'members' => 'ئەندامان', 'topics' => 'بابەتەکان', 'posts' => 'پەیامەکان', 'newest' => 'نوێترین ئەندام', 'sidebar' => 'لاتەنیشت',
			'layout' => 'شێوازی پیشاندان', 'layout_sidebar' => 'لەگەڵ لاتەنیشت', 'layout_wide' => 'پان',
			'view' => 'بینین', 'skin' => 'ڕووکار', 'skin_calm' => 'ڕووکاری ئارام', 'skin_meko' => 'ڕووکاری مەکۆ',
			'scheme' => 'ڕەنگی گشتی', 'scheme_auto' => 'وەک سیستەم', 'scheme_light' => 'ڕووناک', 'scheme_dark' => 'تاریک',
			'pinned' => 'هەڵواسراو', 'more' => 'ی تر سەرهێڵن', 'reply' => 'وەڵام', 'replies' => 'وەڵام', 'active' => 'چالاکترین ئەندامەکان',
			'bbcode' => 'ڕێکخستنی دەق', 'bold' => 'تۆخ', 'italic' => 'لار', 'underline' => 'هێڵ بەژێردا', 'quote' => 'وەرگرتە', 'code' => 'کۆد', 'link' => 'بەستەر', 'image' => 'وێنە', 'list' => 'لیست'),
	);

	return isset($all[$forum_user['language']]) ? $all[$forum_user['language']] : $all['English'];
}

// Avatar markup wrapped for CSS sizing (24 / 32 / 40 / 96 px); the default silhouette when the user has none.
// $row may carry avatar, avatar_width and avatar_height; without it the user is looked up once.
function lo_avatar($user_id, $username, $size = 40, $row = null)
{
	global $forum_db, $forum_config;
	static $cache = array();

	$user_id = (int)$user_id;
	if ($row === null)
	{
		if (!isset($cache[$user_id]))
		{
			$result = $forum_db->query_build(array('SELECT' => 'u.avatar, u.avatar_width, u.avatar_height', 'FROM' => 'users AS u', 'WHERE' => 'u.id='.$user_id)) or error(__FILE__, __LINE__);
			$cache[$user_id] = $forum_db->fetch_assoc($result);
		}
		$row = $cache[$user_id];
	}

	$html = $row ? generate_avatar_markup($user_id, $row['avatar'], $row['avatar_width'], $row['avatar_height'], $username) : '';
	$class = 'lo-avatar '.($size <= 24 ? 'lo-avatar-sm' : ($size <= 32 ? 'lo-avatar-32' : ($size >= 96 ? 'lo-avatar-lg' : 'lo-avatar-md')));
	if ($html !== '')
		return '<span class="'.$class.'">'.$html.'</span>';

	return '<span class="'.$class.' lo-avatar-default" aria-hidden="true"><img src="'.lo_theme_url().'/assets/avatar-default.svg" alt="" width="'.(int)$size.'" height="'.(int)$size.'"></span>';
}

// The real avatar's URL inside lo_avatar() markup, or null for the default silhouette (the island props carry URLs, not HTML)
function lo_avatar_src($html)
{
	return preg_match('/ src="([^"]+)"/', $html, $m) && strpos($m[1], 'avatar-default.svg') === false ? html_entity_decode($m[1]) : null;
}

define('LO_ONLINE_MAX', 16);

// The whole activity <aside> (sidebar pages; only the index polls) with a server-rendered list; the island takes over the same markup.
function lo_activity()
{
	global $forum_db, $forum_user, $forum_url, $forum_config;

	if ($forum_user['g_read_board'] != '1')
		return '';

	// the index lists more (o_lo_activity_count, 5 to 30); other sidebar pages 6
	$limit = FORUM_PAGE === 'index' ? max(5, min(30, (int)lo_setting('o_lo_activity_count', 10))) : 6;
	$l = lo_labels();

	$query = array(
		'SELECT'	=> 't.id, t.subject, t.last_post, t.last_poster, t.forum_id, t.num_replies, f.forum_name, u.id AS uid, u.avatar, u.avatar_width, u.avatar_height',
		'FROM'		=> 'topics AS t',
		'JOINS'		=> array(
			array('INNER JOIN' => 'forums AS f', 'ON' => 'f.id=t.forum_id'),
			array('LEFT JOIN' => 'users AS u', 'ON' => 'u.username=t.last_poster'),
			array('LEFT JOIN' => 'forum_perms AS fp', 'ON' => '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')')
		),
		'WHERE'		=> '(fp.read_forum IS NULL OR fp.read_forum=1) AND t.moved_to IS NULL',
		'ORDER BY'	=> 't.last_post DESC',
		'LIMIT'		=> (string)$limit
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$topics = $items = array();
	while ($t = $forum_db->fetch_assoc($result))
	{
		if ($forum_config['o_censoring'] == '1')
			$t['subject'] = censor_words($t['subject']);
		$t['last_post_formatted'] = format_time($t['last_post']);
		// a guest poster or a renamed user matches no row: the default silhouette
		$avatar = lo_avatar((int)$t['uid'], $t['last_poster'], 32, $t['uid'] === null ? false : $t);
		$t['last_poster_avatar'] = lo_avatar_src($avatar);
		$t['num_replies'] = (int)$t['num_replies'];
		$topics[] = array_intersect_key($t, array_flip(array('id', 'subject', 'last_post', 'last_post_formatted', 'last_poster', 'last_poster_avatar', 'forum_id', 'forum_name', 'num_replies')));
		$items[] = '<li class="lo-act"><a href="'.forum_link($forum_url['topic'], array($t['id'], sef_friendly($t['subject']))).'" title="'.forum_htmlencode($t['last_poster']).'">'.
			'<span aria-hidden="true">'.$avatar.'</span><span class="lo-act-body"><span class="lo-act-subject" dir="auto">'.forum_htmlencode($t['subject']).'</span>'.
			'<span class="lo-act-meta"><span class="sr-only">'.forum_htmlencode($t['last_poster']).' · </span><span dir="auto">'.forum_htmlencode($t['forum_name']).'</span> · '.$t['num_replies'].' '.($t['num_replies'] == 1 ? $l['reply'] : $l['replies']).' · <span dir="auto">'.$t['last_post_formatted'].'</span></span></span></a></li>';
	}

	$online = null;
	$avatar_urls = array();
	$online_html = '';
	if ($forum_config['o_users_online'] == '1')
	{
		$result = $forum_db->query_build(array('SELECT' => 'o.user_id, o.ident, u.avatar, u.avatar_width, u.avatar_height', 'FROM' => 'online AS o', 'JOINS' => array(array('LEFT JOIN' => 'users AS u', 'ON' => 'u.id=o.user_id')), 'WHERE' => 'o.idle=0', 'ORDER BY' => 'o.ident')) or error(__FILE__, __LINE__);
		$online = array('users' => array(), 'guests' => 0);
		$avatars = $avatar_urls = array();
		while ($o = $forum_db->fetch_assoc($result))
		{
			if ($o['user_id'] > 1)
			{
				$online['users'][] = array('id' => (int)$o['user_id'], 'username' => $o['ident']);
				$avatars[(int)$o['user_id']] = lo_avatar($o['user_id'], $o['ident'], 24, $o);
				if (($src = lo_avatar_src($avatars[(int)$o['user_id']])) !== null)
					$avatar_urls[(int)$o['user_id']] = $src;
			}
			else
				++$online['guests'];
		}

		// a row of avatars, the name as title and for screen readers; past LO_ONLINE_MAX a +N count (two rows in the sidebar)
		$names = array();
		foreach (array_slice($online['users'], 0, LO_ONLINE_MAX) as $u)
		{
			$name = forum_htmlencode($u['username']);
			$inner = '<span aria-hidden="true">'.$avatars[$u['id']].'</span><span class="sr-only">'.$name.'</span>';
			$names[] = $forum_user['g_view_users'] == '1' ? '<a class="lo-who" href="'.forum_link($forum_url['user'], $u['id']).'" title="'.$name.'">'.$inner.'</a>' : '<span class="lo-who" title="'.$name.'">'.$inner.'</span>';
		}
		$more = count($online['users']) - LO_ONLINE_MAX;
		if ($more > 0)
			$names[] = '<span class="lo-more" title="'.$more.' '.$l['more'].'">+'.$more.'</span>';
		$online_html = '<h3>'.$l['online'].'</h3><p class="lo-online">'.implode('', $names).($online['guests'] ? '<span class="lo-guests" dir="auto">'.$online['guests'].' '.$l['guests'].'</span>' : '').'</p>';
	}

	$props = array(
		'limit' => $limit, 'poll' => FORUM_PAGE === 'index', 'refresh' => (int)lo_setting('o_lo_activity_refresh', 60), 'max' => LO_ONLINE_MAX, 'labels' => $l, 'online' => $online, 'topics' => $topics,
		'avatars' => $avatar_urls, 'defaultAvatar' => lo_theme_url().'/assets/avatar-default.svg',
		'topicUrl' => forum_link($forum_url['topic'], array(987654321, '')), 'userUrl' => $forum_user['g_view_users'] == '1' ? forum_link($forum_url['user'], 987654321) : '',
	);

	return '<aside id="lo-activity"><div class="lo-panel" data-island="activity-panel" data-props=\''.forum_htmlencode(json_encode($props)).'\'>'.
		'<h2>'.$l['activity'].'</h2><h3>'.$l['recent'].'</h3><ul>'.implode('', $items).'</ul>'.$online_html.
		'<p class="lo-updated">'.$l['updated'].' '.format_time(time()).'</p></div></aside>';
}


// lo_layout / lo_skin / lo_scheme, while switching is allowed: a member's saved choice (the users column lightoxygen_settings adds, read
// only while that extension is enabled), else the visitor's cookie; then the board default, then $allowed[0]. Invalid values are skipped.
function lo_pref(string $name, array $allowed)
{
	global $forum_user;

	if (lo_setting('o_lo_allow_switch', '1') == '1')
	{
		if (defined('LO_SETTINGS') && empty($forum_user['is_guest']) && isset($forum_user[$name]) && in_array($forum_user[$name], $allowed, true))
			return $forum_user[$name];
		if (isset($_COOKIE[$name]) && in_array($_COOKIE[$name], $allowed, true))
			return $_COOKIE[$name];
	}

	$board = array('lo_layout' => 'o_lo_default_layout', 'lo_skin' => 'o_lo_default_skin', 'lo_scheme' => 'o_lo_color_scheme');
	return lo_pick(lo_setting($board[$name], $allowed[0]), $allowed);
}

// 'auto' (default: follow the system), 'light' or 'dark'
function lo_scheme()
{
	return lo_pref('lo_scheme', array('auto', 'light', 'dark'));
}

// 'sidebar' (default) or 'wide'
function lo_layout()
{
	return lo_pref('lo_layout', array('sidebar', 'wide'));
}

function lo_has_sidebar()
{
	return defined('FORUM_PAGE') && in_array(FORUM_PAGE, array('index', 'viewforum', 'searchtopics', 'searchposts', 'userlist'), true);
}

// 'calm' (default, the LightOxygen look) or 'meko' (the live board's look)
function lo_skin()
{
	return lo_pref('lo_skin', array('calm', 'meko'));
}

// This page's URL on the board with the query minus $name (and $name=$value added when given). Built from $base_url and the
// validated SCRIPT_NAME (or, behind friendly URLs, the REQUEST_URI path), so a crafted path such as /\host or //host cannot turn
// it into an off-site redirect.
function lo_pref_url(string $name, ?string $value = null): string
{
	global $base_url;

	$origin = preg_replace('#^([a-z][a-z0-9+.-]*://[^/]+).*$#i', '$1', $base_url);
	$script = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
	$query = $_GET;
	$safe = fn($p) => preg_match('#^/[A-Za-z0-9_./-]*$#', $p) && strpos($p, '..') === false && strpos($p, '//') === false;
	if (basename($script) === 'rewrite.php')
	{
		// every friendly URL is served by rewrite.php: the visitor's path is the page, kept only when it stays under the board
		$uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
		// split by hand: parse_url() would read //host/x as a host
		list($path, $qs) = explode('?', $uri, 2) + array('', '');
		// rewrite.php adds the rule's own parameters to $_GET; the path already carries them
		parse_str(explode('#', $qs)[0], $query);
		$url = ($safe($path) && strpos($path, rtrim((string)parse_url($base_url, PHP_URL_PATH), '/').'/') === 0) ? $origin.$path : $base_url.'/index.php';
	}
	else
		$url = ($safe($script) && substr($script, -4) === '.php') ? $origin.$script : $base_url.'/index.php';

	unset($query[$name]);
	if ($value !== null)
		$query[$name] = $value;

	return $url.($query ? '?'.http_build_query($query) : '');
}

function lo_layout_url($value = null)
{
	return lo_pref_url('lo_layout', $value);
}

// One group of plain links (no script needed) for the view menu; $icons maps each value to its svg body
function lo_pref_switch($name, $cur, $icons, $class, $label, $titles)
{
	$out = array();
	foreach ($icons as $value => $svg)
		$out[] = '<a href="'.forum_htmlencode(lo_pref_url($name, $value)).'" aria-pressed="'.($cur === $value ? 'true' : 'false').'" title="'.$titles[$value].'" rel="nofollow">'.
			'<svg class="lo-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true">'.$svg.'</svg>'.
			'<span>'.$titles[$value].'</span></a>';

	return '<div class="'.$class.'" role="group" aria-label="'.$label.'"><p aria-hidden="true">'.$label.'</p>'.implode('', $out).'</div>';
}

function lo_layout_switch()
{
	if (!lo_has_sidebar())
		return '';

	$l = lo_labels();
	return lo_pref_switch('lo_layout', lo_layout(), array('sidebar' => '<path d="M4 5h16v14H4zM15 5v14"/>', 'wide' => '<path d="M4 5h16v14H4zM4 10h16"/>'),
		'lo-layout-switch', $l['layout'], array('sidebar' => $l['layout_sidebar'], 'wide' => $l['layout_wide']));
}

// Look switch: calm (outlined card) and meko (card under a solid header bar); shown on every page
function lo_skin_switch()
{
	$l = lo_labels();
	return lo_pref_switch('lo_skin', lo_skin(), array('calm' => '<path d="M4 5h16v14H4z"/>', 'meko' => '<path d="M4 5h16v14H4z"/><path d="M4 5h16v4H4z" fill="currentColor"/>'),
		'lo-skin-switch', $l['skin'], array('calm' => $l['skin_calm'], 'meko' => $l['skin_meko']));
}

// Colour scheme switch: follow the system, light, dark; shown on every page
function lo_scheme_switch()
{
	$l = lo_labels();
	return lo_pref_switch('lo_scheme', lo_scheme(), array('auto' => '<circle cx="12" cy="12" r="8"/><path d="M12 4a8 8 0 010 16z" fill="currentColor"/>',
		'light' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5"/>',
		'dark' => '<path d="M20 14.5A8 8 0 019.5 4 8 8 0 1020 14.5z"/>'),
		'lo-scheme-switch', $l['scheme'], array('auto' => $l['scheme_auto'], 'light' => $l['scheme_light'], 'dark' => $l['scheme_dark']));
}

// The header's "View" menu: a <details> popover holding the layout group (sidebar pages only), the look group and the colour scheme group
function lo_view_menu()
{
	if (lo_setting('o_lo_allow_switch', '1') != '1')
		return '';

	$l = lo_labels();
	return '<details class="lo-view-menu"><summary title="'.$l['view'].'">'.
		'<svg class="lo-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/></svg>'.
		'<span class="sr-only">'.$l['view'].'</span></summary><div class="lo-view-pop">'.lo_layout_switch().lo_skin_switch().lo_scheme_switch().'</div></details>';
}

// Categories with their readable forums, as index.php reads them; runs once per request.
function lo_forums()
{
	global $forum_db, $forum_user;
	static $cats = null;

	if ($cats !== null)
		return $cats;

	$query = array(
		'SELECT'	=> 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.redirect_url, f.num_topics',
		'FROM'		=> 'categories AS c',
		'JOINS'		=> array(
			array('INNER JOIN' => 'forums AS f', 'ON' => 'c.id=f.cat_id'),
			array('LEFT JOIN' => 'forum_perms AS fp', 'ON' => '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')')
		),
		'WHERE'		=> 'fp.read_forum IS NULL OR fp.read_forum=1',
		'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$cats = array();
	while ($f = $forum_db->fetch_assoc($result))
	{
		$cats[$f['cid']]['name'] = $f['cat_name'];
		$cats[$f['cid']]['forums'][] = $f;
	}

	return $cats;
}

// Browse, Filters, Activity, Statistics on the end side; on the index Activity, Filters, Statistics (its forum list already shows
// every forum, so Browse only with o_lo_index_browse); in the wide layout only the activity panel, on the index.
function lo_sidebar()
{
	global $forum_user, $forum_url, $forum_config, $lang_common, $id;

	if (!lo_has_sidebar() || $forum_user['g_read_board'] != '1')
		return '';

	$l = lo_labels();

	if (lo_layout() === 'wide')
		return FORUM_PAGE === 'index' ? '<div id="lo-sidebar" class="lo-sidebar-wide">'.lo_activity().'</div>' : '';

	$current = FORUM_PAGE === 'viewforum' ? (int)$id : 0;
	$browse = '';
	foreach (lo_forums() as $cat)
	{
		$items = '';
		foreach ($cat['forums'] as $f)
		{
			$href = $f['redirect_url'] != '' ? $f['redirect_url'] : forum_link($forum_url['forum'], array($f['fid'], sef_friendly($f['forum_name'])));
			$items .= '<li><a href="'.forum_htmlencode($href).'"'.((int)$f['fid'] === $current ? ' aria-current="page"' : '').'><span>'.forum_htmlencode($f['forum_name']).'</span>'.
				($f['redirect_url'] == '' ? '<small class="lo-stat">'.forum_number_format($f['num_topics']).'</small>' : '').'</a></li>';
		}
		$browse .= '<h3>'.forum_htmlencode($cat['name']).'</h3><ul>'.$items.'</ul>';
	}

	$filters = array();
	if (!$forum_user['is_guest'])
		$filters[] = array(forum_link($forum_url['search_new']), $lang_common['New posts']);
	$filters[] = array(forum_link($forum_url['search_recent']), $lang_common['Active topics']);
	$filters[] = array(forum_link($forum_url['search_recent_results'], 604800), $l['recent_week']);
	$filters[] = array(forum_link($forum_url['search_unanswered']), $lang_common['Unanswered topics']);
	if (!$forum_user['is_guest'])
	{
		$filters[] = array(forum_link($forum_url['search_user_posts'], $forum_user['id']), $l['my_posts']);
		if ($forum_config['o_subscriptions'] == '1')
			$filters[] = array(forum_link($forum_url['search_subscriptions'], $forum_user['id']), $l['subscriptions']);
	}
	$filter_items = '';
	foreach ($filters as $f)
		$filter_items .= '<li><a href="'.$f[0].'">'.$f[1].'</a></li>';

	$browse = '<details class="lo-panel lo-browse" open><summary><h2>'.$l['browse'].'</h2></summary><nav>'.$browse.'</nav></details>';
	$filters = '<section class="lo-panel lo-filters"><h2>'.$l['filters'].'</h2><ul>'.$filter_items.'</ul></section>';
	$stats = lo_setting('o_lo_show_stats', '1') == '1' ? lo_stats() : '';

	return '<aside id="lo-sidebar" aria-label="'.$l['sidebar'].'">'.
		(FORUM_PAGE === 'index' ? (lo_setting('o_lo_index_browse', '0') == '1' ? $browse : '').lo_activity().$filters : $browse.$filters.lo_activity()).$stats.
		'</aside>';
}

function lo_stats()
{
	global $forum_stats, $forum_user, $forum_url;

	if (!defined('FORUM_STATS_LOADED') && file_exists(FORUM_CACHE_DIR.'cache_stats.php'))
		include FORUM_CACHE_DIR.'cache_stats.php';

	if (!defined('FORUM_STATS_LOADED') || $forum_stats['cached'] < (time() - 1800))
	{
		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/cache.php';

		generate_stats_cache();
		require FORUM_CACHE_DIR.'cache_stats.php';
	}

	$l = lo_labels();
	$newest = forum_htmlencode($forum_stats['last_user']['username']);
	if ($forum_user['g_view_users'] == '1')
		$newest = '<a href="'.forum_link($forum_url['user'], $forum_stats['last_user']['id']).'">'.$newest.'</a>';

	return '<section class="lo-panel lo-stats"><h2>'.$l['stats'].'</h2><dl>'.
		'<div><dt>'.$l['members'].'</dt><dd class="lo-stat" dir="auto">'.forum_number_format($forum_stats['total_users']).'</dd></div>'.
		'<div><dt>'.$l['topics'].'</dt><dd class="lo-stat" dir="auto">'.forum_number_format($forum_stats['total_topics']).'</dd></div>'.
		'<div><dt>'.$l['posts'].'</dt><dd class="lo-stat" dir="auto">'.forum_number_format($forum_stats['total_posts']).'</dd></div>'.
		'<div class="lo-newest"><dt>'.$l['newest'].'</dt><dd dir="auto">'.$newest.'</dd></div>'.
		'</dl></section>';
}

// The index hero: title and tagline, a large search, sign-in for guests. Shown to guests, to everyone, or to nobody, by
// o_lo_hero_mode, under both looks (the look only styles it); the admin's background image, or the theme's
// assets/hero-default.jpg when none is set, goes under a dark overlay for legibility.
function lo_hero()
{
	global $forum_user, $forum_config, $forum_url, $lang_common;

	$mode = lo_pick(lo_setting('o_lo_hero_mode', 'guests'), array('guests', 'all', 'none'));
	if (!defined('FORUM_PAGE') || FORUM_PAGE !== 'index' || $mode === 'none' || ($mode === 'guests' && !$forum_user['is_guest']))
		return '';

	$title = forum_trim((string)lo_setting('o_lo_hero_title', ''));
	$text = forum_trim((string)lo_setting('o_lo_hero_text', ''));
	if ($title === '')
		$title = $forum_config['o_board_title'];
	if ($text === '')
		$text = $forum_config['o_board_desc'];

	$bg = lo_setting_url(lo_setting('o_lo_hero_bg', ''), 'hero');
	if ($bg === '')
		$bg = lo_theme_url().'/assets/hero-default.jpg';
	$style = ' style="'.forum_htmlencode('background:linear-gradient(rgb(0 0 0 / .5),rgb(0 0 0 / .5)),url("'.lo_css_str($bg).'") center/cover no-repeat').'"';

	return '<section class="lo-hero"'.$style.'><div class="lo-hero-text"><p class="lo-hero-title">'.forum_htmlencode($title).'</p>'.
		($text != '' ? '<p class="lo-hero-desc">'.forum_htmlencode($text).'</p>' : '').'</div>'.
		lo_search('lo-hero-search').
		($forum_user['is_guest'] ? '<div class="lo-hero-auth">'.
			'<a class="lo-btn lo-btn-ghost" href="'.forum_link($forum_url['login']).'">'.$lang_common['Login'].'</a>'.
			'<a class="lo-btn lo-btn-primary" href="'.forum_link($forum_url['register']).'">'.$lang_common['Register'].'</a>'.
		'</div>' : '').'</section>'.(lo_skin() === 'meko' && lo_setting('o_lo_show_member_avatars', '1') == '1' ? lo_members() : '');
}

// Meko skin, under the hero: the members with the most posts as a row of avatars, as on the live board
function lo_members()
{
	global $forum_db, $forum_user, $forum_url;

	if ($forum_user['g_read_board'] != '1')
		return '';

	$result = $forum_db->query_build(array('SELECT' => 'u.id, u.username, u.avatar, u.avatar_width, u.avatar_height', 'FROM' => 'users AS u',
		'WHERE' => 'u.id>1 AND u.group_id!='.FORUM_UNVERIFIED.' AND u.num_posts>0', 'ORDER BY' => 'u.num_posts DESC', 'LIMIT' => '12')) or error(__FILE__, __LINE__);
	$out = array();
	while ($u = $forum_db->fetch_assoc($result))
	{
		$name = forum_htmlencode($u['username']);
		$inner = '<span aria-hidden="true">'.lo_avatar($u['id'], $u['username'], 40, $u).'</span><span class="sr-only">'.$name.'</span>';
		$out[] = $forum_user['g_view_users'] == '1' ? '<a class="lo-who" href="'.forum_link($forum_url['user'], $u['id']).'" title="'.$name.'">'.$inner.'</a>' : '<span class="lo-who" title="'.$name.'">'.$inner.'</span>';
	}
	if (!$out)
		return '';

	$l = lo_labels();
	return '<section class="lo-members"><h2>'.$l['active'].'</h2><p>'.implode('', $out).'</p></section>';
}

// Attributes for #brd-main on topic lists: the topic-avatars mount point (and whether the viewer may look users up), the translated group labels and, when this page
// of a forum starts with sticky topics (core prints them first), the class that turns the Pinned / Topics labels on.
function lo_main_attr()
{
	global $forum_db, $forum_user, $id;

	if (!defined('FORUM_PAGE') || !in_array(FORUM_PAGE, array('viewforum', 'searchtopics', 'searchposts'), true))
		return '';

	$pinned = false;
	if (FORUM_PAGE === 'viewforum' && (!isset($_GET['p']) || (int)$_GET['p'] <= 1))
	{
		$result = $forum_db->query_build(array('SELECT' => 't.id', 'FROM' => 'topics AS t', 'WHERE' => 't.forum_id='.(int)$id.' AND t.sticky=1', 'LIMIT' => '1')) or error(__FILE__, __LINE__);
		$pinned = (bool)$forum_db->fetch_assoc($result);
	}

	$l = lo_labels();
	$vars = '--lo-l-pinned:"'.lo_css_str($l['pinned']).'";--lo-l-topics:"'.lo_css_str($l['topics']).'"';

	return ($pinned ? ' class="lo-has-pinned"' : '').' data-topic-avatars data-can-view-users="'.($forum_user['g_view_users'] == '1' ? '1' : '0').'" style="'.forum_htmlencode($vars).'"';
}
