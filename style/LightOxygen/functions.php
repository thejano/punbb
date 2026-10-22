<?php

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

function lo_search()
{
	global $forum_user, $forum_url, $lang_common;

	if ($forum_user['g_read_board'] != '1' || $forum_user['g_search'] != '1')
		return '';

	$action = forum_link($forum_url['search']);
	$props = array('action' => $action, 'placeholder' => $lang_common['Search'], 'topic_url' => forum_link($forum_url['topic'], 987654321));

	return '<div class="lo-search" data-island="search-box" data-props=\''.forum_htmlencode(json_encode($props)).'\'>'.
		'<form method="get" action="'.forum_htmlencode($action).'" role="search">'.
		'<input type="hidden" name="action" value="search" />'.
		'<input type="search" name="keywords" maxlength="100" placeholder="'.forum_htmlencode($lang_common['Search']).'" aria-label="'.forum_htmlencode($lang_common['Search']).'" />'.
		'</form></div>';
}

function lo_meta()
{
	global $base_url;

	// shortcut: pretty API URLs need .htaccess, which the PHP built-in server ignores
	$api = (is_file(FORUM_ROOT.'api/.htaccess') && PHP_SAPI !== 'cli-server') ? $base_url.'/api/v1' : $base_url.'/api/index.php?route=';

	$out = '<meta name="api-base" content="'.forum_htmlencode($api).'" />'."\n".
		'<meta name="api-csrf" content="'.forum_htmlencode(generate_form_token('api')).'" />';

	// translated labels for the quick reply toolbar, which main.jsx mounts without a server element of its own
	if (defined('FORUM_PAGE') && FORUM_PAGE === 'viewtopic')
		$out .= "\n".'<template id="lo-qr-props" data-props=\''.forum_htmlencode(json_encode(array('labels' => lo_labels()))).'\'></template>';

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
			'bbcode' => 'Formatting', 'bold' => 'Bold', 'italic' => 'Italic', 'underline' => 'Underline', 'quote' => 'Quote', 'code' => 'Code', 'link' => 'Link', 'image' => 'Image', 'list' => 'List'),
		'Kurdish' => array('activity' => 'چالاکی', 'recent' => 'بابەتە نوێیەکان', 'online' => 'ئەندامانی سەرهێڵ', 'guests' => 'میوان', 'updated' => 'نوێکرایەوە',
			'browse' => 'بەشەکان', 'filters' => 'پاڵاوتن', 'recent_week' => 'ئەم هەفتەیە', 'my_posts' => 'پەیامەکانم', 'subscriptions' => 'بەشداربوونەکانم',
			'stats' => 'ئامار', 'members' => 'ئەندامان', 'topics' => 'بابەتەکان', 'posts' => 'پەیامەکان', 'newest' => 'نوێترین ئەندام', 'sidebar' => 'لاتەنیشت',
			'layout' => 'شێوازی پیشاندان', 'layout_sidebar' => 'لەگەڵ لاتەنیشت', 'layout_wide' => 'پان',
			'bbcode' => 'ڕێکخستنی دەق', 'bold' => 'تۆخ', 'italic' => 'لار', 'underline' => 'هێڵ بەژێردا', 'quote' => 'وەرگرتە', 'code' => 'کۆد', 'link' => 'بەستەر', 'image' => 'وێنە', 'list' => 'لیست'),
	);

	return isset($all[$forum_user['language']]) ? $all[$forum_user['language']] : $all['English'];
}

// The whole <aside> (index page only) with a server-rendered list; the island takes over the same markup.
function lo_activity()
{
	global $forum_db, $forum_user, $forum_url, $forum_config;

	if ($forum_user['g_read_board'] != '1')
		return '';

	$limit = 8;
	$l = lo_labels();

	$query = array(
		'SELECT'	=> 't.id, t.subject, t.last_post, t.last_poster, t.forum_id, t.num_replies',
		'FROM'		=> 'topics AS t',
		'JOINS'		=> array(
			array('INNER JOIN' => 'forums AS f', 'ON' => 'f.id=t.forum_id'),
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
		$topics[] = array_intersect_key($t, array_flip(array('id', 'subject', 'last_post', 'last_post_formatted', 'last_poster', 'forum_id', 'num_replies')));
		$items[] = '<li><a href="'.forum_link($forum_url['topic'], $t['id']).'">'.forum_htmlencode($t['subject']).'</a><span>'.forum_htmlencode($t['last_poster']).' · '.$t['last_post_formatted'].'</span></li>';
	}

	$online = null;
	$online_html = '';
	if ($forum_config['o_users_online'] == '1')
	{
		$result = $forum_db->query_build(array('SELECT' => 'o.user_id, o.ident', 'FROM' => 'online AS o', 'WHERE' => 'o.idle=0', 'ORDER BY' => 'o.ident')) or error(__FILE__, __LINE__);
		$online = array('users' => array(), 'guests' => 0);
		while ($o = $forum_db->fetch_assoc($result))
		{
			if ($o['user_id'] > 1)
				$online['users'][] = array('id' => (int)$o['user_id'], 'username' => $o['ident']);
			else
				++$online['guests'];
		}

		$names = array();
		foreach ($online['users'] as $u)
			$names[] = $forum_user['g_view_users'] == '1' ? '<a href="'.forum_link($forum_url['user'], $u['id']).'">'.forum_htmlencode($u['username']).'</a>' : forum_htmlencode($u['username']);
		$online_html = '<h3>'.$l['online'].'</h3><p>'.implode(', ', $names).($online['guests'] ? ($names ? ' · ' : '').$online['guests'].' '.$l['guests'] : '').'</p>';
	}

	$props = array(
		'limit' => $limit, 'refresh' => 60, 'labels' => $l, 'online' => $online, 'topics' => $topics,
		'topicUrl' => forum_link($forum_url['topic'], 987654321), 'userUrl' => $forum_user['g_view_users'] == '1' ? forum_link($forum_url['user'], 987654321) : '',
	);

	return '<aside id="lo-activity"><div class="lo-panel" data-island="activity-panel" data-props=\''.forum_htmlencode(json_encode($props)).'\'>'.
		'<h2>'.$l['activity'].'</h2><h3>'.$l['recent'].'</h3><ul>'.implode('', $items).'</ul>'.$online_html.
		'<p class="lo-updated">'.$l['updated'].' '.format_time(time()).'</p></div></aside>';
}


// 'sidebar' (default) or 'wide', from the lo_layout cookie
function lo_layout()
{
	return (isset($_COOKIE['lo_layout']) && $_COOKIE['lo_layout'] === 'wide') ? 'wide' : 'sidebar';
}

function lo_has_sidebar()
{
	return defined('FORUM_PAGE') && in_array(FORUM_PAGE, array('index', 'viewforum', 'searchtopics', 'searchposts', 'userlist'), true);
}

// This script's URL on the board with the query minus lo_layout (and $value added when given). Built from $base_url and the
// validated SCRIPT_NAME, never from REQUEST_URI, so a crafted path such as /\host or //host cannot turn it into an off-site redirect.
function lo_layout_url($value = null)
{
	global $base_url;

	$origin = preg_replace('#^([a-z][a-z0-9+.-]*://[^/]+).*$#i', '$1', $base_url);
	$script = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
	$url = (preg_match('#^/[A-Za-z0-9_./-]*\.php$#', $script) && strpos($script, '..') === false && strpos($script, '//') === false)
		? $origin.$script : $base_url.'/index.php';

	$query = $_GET;
	unset($query['lo_layout']);
	if ($value !== null)
		$query['lo_layout'] = $value;

	return $url.($query ? '?'.http_build_query($query) : '');
}

function lo_layout_switch()
{
	if (!lo_has_sidebar())
		return '';

	$l = lo_labels();
	$cur = lo_layout();
	$icons = array('sidebar' => 'M4 5h16v14H4zM15 5v14', 'wide' => 'M4 5h16v14H4zM4 10h16');
	$out = array();
	foreach ($icons as $value => $d)
		$out[] = '<a href="'.forum_htmlencode(lo_layout_url($value)).'" aria-pressed="'.($cur === $value ? 'true' : 'false').'" title="'.$l['layout_'.$value].'" rel="nofollow">'.
			'<svg class="lo-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="'.$d.'"/></svg>'.
			'<span class="sr-only">'.$l['layout_'.$value].'</span></a>';

	return '<div class="lo-layout-switch" role="group" aria-label="'.$l['layout'].'">'.implode('', $out).'</div>';
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

// Browse, Filters, Activity, Statistics on the end side; in the wide layout only the activity panel, on the index.
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
				($f['redirect_url'] == '' ? '<small>'.forum_number_format($f['num_topics']).'</small>' : '').'</a></li>';
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

	return '<aside id="lo-sidebar" aria-label="'.$l['sidebar'].'">'.
		'<details class="lo-panel lo-browse" open><summary><h2>'.$l['browse'].'</h2></summary><nav>'.$browse.'</nav></details>'.
		'<section class="lo-panel lo-filters"><h2>'.$l['filters'].'</h2><ul>'.$filter_items.'</ul></section>'.
		lo_activity().lo_stats().
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
		'<div><dt>'.$l['members'].'</dt><dd>'.forum_number_format($forum_stats['total_users']).'</dd></div>'.
		'<div><dt>'.$l['topics'].'</dt><dd>'.forum_number_format($forum_stats['total_topics']).'</dd></div>'.
		'<div><dt>'.$l['posts'].'</dt><dd>'.forum_number_format($forum_stats['total_posts']).'</dd></div>'.
		'<div class="lo-newest"><dt>'.$l['newest'].'</dt><dd>'.$newest.'</dd></div>'.
		'</dl></section>';
}
