<?php

api_route('GET', '/forums', function () {
	global $forum_db, $forum_user;

	$query = array(
		'SELECT'	=> 'c.id AS cid, c.cat_name, f.id, f.forum_name, f.forum_desc, f.cat_id, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster, fp.post_topics',
		'FROM'		=> 'categories AS c',
		'JOINS'		=> array(
			array(
				'INNER JOIN'	=> 'forums AS f',
				'ON'			=> 'c.id=f.cat_id'
			),
			array(
				'LEFT JOIN'		=> 'forum_perms AS fp',
				'ON'			=> '(fp.forum_id=f.id AND fp.group_id='.$forum_user['g_id'].')'
			)
		),
		'WHERE'		=> 'fp.read_forum IS NULL OR fp.read_forum=1',
		'ORDER BY'	=> 'c.disp_position, c.id, f.disp_position'
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$cats = array();
	while ($row = $forum_db->fetch_assoc($result))
	{
		if (!isset($cats[$row['cid']]))
			$cats[$row['cid']] = ['id' => (int)$row['cid'], 'name' => $row['cat_name'], 'forums' => []];
		$cats[$row['cid']]['forums'][] = api_forum_shape($row);
	}

	api_json(array_values($cats));
});

api_route('GET', '/forums/{id}', function ($p) {
	api_json(api_forum_shape(api_forum_or_404((int)$p['id'])));
});

api_route('GET', '/forums/{id}/topics', function ($p) {
	global $forum_db, $forum_user, $forum_config;

	$forum = api_forum_or_404((int)$p['id']);
	$page = api_page((int)$forum['num_topics'], (int)$forum_config['o_disp_topics_default']);

	$query = array(
		'SELECT'	=> 't.id, t.poster, t.subject, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, t.forum_id',
		'FROM'		=> 'topics AS t',
		'WHERE'		=> 't.forum_id='.(int)$forum['id'],
		'ORDER BY'	=> 't.sticky DESC, '.(($forum['sort_by'] == '1') ? 't.posted' : 't.last_post').' DESC',
		'LIMIT'		=> $page['offset'].', '.(int)$forum_config['o_disp_topics_default']
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	$topics = array();
	while ($row = $forum_db->fetch_assoc($result))
		$topics[] = api_topic_shape($row);

	api_json($topics, 200, ['page' => $page['page'], 'pages' => $page['pages'], 'total' => (int)$forum['num_topics']]);
});
