<?php

api_route('GET', '/search', function () {
	global $forum_db, $forum_user, $forum_config;

	require_once FORUM_ROOT.'include/search_idx.php';

	// split_words keeps only valid, unique words
	$words = array_values(split_words(utf8_strtolower(forum_trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''))));
	if (!$words)
		api_error('bad_request', 'q needs at least one searchable word', 400);

	$in = array_map(fn($w) => '\''.$forum_db->escape($w).'\'', $words);
	$query = array(
		'SELECT'	=> 't.id, t.poster, t.subject, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, t.forum_id',
		'FROM'		=> 'search_words AS w',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'search_matches AS m',
				'ON'			=> 'm.word_id=w.id'
			),
			array(
				'INNER JOIN'	=> 'posts AS p',
				'ON'			=> 'p.id=m.post_id'
			),
			array(
				'INNER JOIN'	=> 'topics AS t',
				'ON'			=> 't.id=p.topic_id'
			),
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> '(fp.forum_id=t.forum_id AND fp.group_id='.$forum_user['g_id'].')'
			)
		),
		'WHERE'		=> 'w.word IN ('.implode(',', $in).') AND (fp.read_forum IS NULL OR fp.read_forum=1)',
		'GROUP BY'	=> 't.id, t.poster, t.subject, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, t.forum_id',
		'HAVING'	=> 'COUNT(DISTINCT w.id)='.count($words),
		'ORDER BY'	=> 't.last_post DESC'
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	// shortcut: all matches are loaded then sliced, upgrade to a counted subquery if result sets get large
	$rows = array();
	while ($row = $forum_db->fetch_assoc($result))
		$rows[] = $row;

	$per_page = (int)$forum_config['o_disp_topics_default'];
	$page = api_page(count($rows), $per_page);
	api_json(array_map('api_topic_shape', array_slice($rows, $page['offset'], $per_page)), 200, ['page' => $page['page'], 'pages' => $page['pages'], 'total' => count($rows)]);
});
