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
	$props = array('action' => $action, 'placeholder' => $lang_common['Search']);

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

	return '<meta name="api-base" content="'.forum_htmlencode($api).'" />'."\n".
		'<meta name="api-csrf" content="'.forum_htmlencode(generate_form_token('api')).'" />';
}

function lo_labels()
{
	global $forum_user;

	$all = array(
		'English' => array('activity' => 'Activity', 'recent' => 'Recent topics', 'online' => 'Online members', 'guests' => 'guests', 'updated' => 'updated'),
		'Kurdish' => array('activity' => 'چالاکی', 'recent' => 'بابەتە نوێیەکان', 'online' => 'ئەندامانی سەرهێڵ', 'guests' => 'میوان', 'updated' => 'نوێکرایەوە'),
	);

	return isset($all[$forum_user['language']]) ? $all[$forum_user['language']] : $all['English'];
}

// The whole <aside> (index page only) with a server-rendered list; the island takes over the same markup.
function lo_activity()
{
	global $forum_db, $forum_user, $forum_url, $forum_config;

	if (!defined('FORUM_PAGE') || FORUM_PAGE !== 'index' || $forum_user['g_read_board'] != '1')
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
