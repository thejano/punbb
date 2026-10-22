<?php

api_route('GET', '/search', function () {
	global $forum_db, $forum_user, $forum_config, $db_type;

	require_once FORUM_ROOT.'include/search_idx.php';

	// split_words keeps only valid, unique words
	$q = utf8_strtolower(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
	$words = array_values(split_words(forum_trim($q)));
	if (!$words)
		api_error('bad_request', 'q needs at least one searchable word', 400);

	// while the last word is still being typed (no trailing space) it is a prefix of 3+ characters: it matches any of up to
	// 50 indexed words starting with it; the other words match exactly
	$prefix_ids = array();
	$tail = array_values(split_words(preg_replace('/^.*\s/su', '', $q)));
	if (!preg_match('/\s$/u', $q) && $tail && utf8_strlen($prefix = end($tail)) >= 3)
	{
		$like = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $prefix);
		$result = $forum_db->query_build(array(
			'SELECT'	=> 'w.id',
			'FROM'		=> 'search_words AS w',
			'WHERE'		=> 'w.word '.($db_type == 'pgsql' ? 'ILIKE' : 'LIKE').' \''.$forum_db->escape($like).'%\' ESCAPE \''.(in_array($db_type, array('mysql', 'mysqli', 'mysql_innodb', 'mysqli_innodb')) ? '\\\\' : '\\').'\'',
			'ORDER BY'	=> 'w.word',
			'LIMIT'		=> '50'
		)) or error(__FILE__, __LINE__);
		while ($row = $forum_db->fetch_row($result))
			$prefix_ids[] = (int)$row[0];
		if (!$prefix_ids)
			api_json(array(), 200, ['page' => 1, 'pages' => 1, 'total' => 0]);
		$words = array_values(array_diff($words, array($prefix)));
	}

	$in = array_map(fn($w) => '\''.$forum_db->escape($w).'\'', $words);
	// a topic must hold every exact word and, with a prefix, at least one of its words
	$where = $in ? 'w.word IN ('.implode(',', $in).')' : '1=0';
	$having = 'COUNT(DISTINCT CASE WHEN '.$where.' THEN w.id END)='.count($words);
	if ($prefix_ids)
	{
		$where = '('.$where.' OR w.id IN ('.implode(',', $prefix_ids).'))';
		$having .= ' AND SUM(CASE WHEN w.id IN ('.implode(',', $prefix_ids).') THEN 1 ELSE 0 END)>0';
	}
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
		'WHERE'		=> $where.' AND (fp.read_forum IS NULL OR fp.read_forum=1)',
		'GROUP BY'	=> 't.id, t.poster, t.subject, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, t.forum_id',
		'HAVING'	=> $having,
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
