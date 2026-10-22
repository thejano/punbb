<?php
$r = api_call('GET', '/forums');
$cats = $r['json']['data'] ?? [];
$forum_ids = [];
foreach ($cats as $c)
	foreach ($c['forums'] ?? [] as $f)
		$forum_ids[] = $f['id'];
check($r['status'] === 200 && isset($cats[0]['id'], $cats[0]['name'], $cats[0]['forums']), 'GET /forums lists categories with forums');
check(in_array(1, $forum_ids, true), 'GET /forums includes forum 1');
$f = $cats[0]['forums'][0] ?? [];
check(array_keys($f) === ['id', 'name', 'desc', 'cat_id', 'num_topics', 'num_posts', 'last_post', 'last_post_formatted', 'last_post_id', 'last_poster', 'redirect_url', 'can_post'], 'forum shape keys');

$r = api_call('GET', '/forums/1');
check($r['status'] === 200 && is_string($r['json']['data']['name'] ?? null), 'GET /forums/1 has name');
check(api_call('GET', '/forums/999999')['status'] === 404, 'GET /forums/999999 is 404');

$r = api_call('GET', '/forums/1/topics?page=1');
check($r['status'] === 200 && ($r['json']['meta']['page'] ?? 0) === 1 && ($r['json']['meta']['pages'] ?? 0) >= 1 && isset($r['json']['meta']['total']), 'topics list meta');
check(is_string($r['json']['data'][0]['subject'] ?? null) && ($r['json']['data'][0]['forum_id'] ?? 0) === 1, 'topics list first item');
$t = $r['json']['data'][0] ?? [];
check(array_keys($t) === ['id', 'subject', 'poster', 'posted', 'posted_formatted', 'last_post', 'last_post_formatted', 'last_post_id', 'last_poster', 'num_replies', 'num_views', 'closed', 'sticky', 'forum_id', 'moved_to'], 'topic shape keys');

$r = api_call('GET', '/topics/1');
check($r['status'] === 200 && ($r['json']['data']['id'] ?? 0) === 1, 'GET /topics/1');
check(api_call('GET', '/topics/999999')['status'] === 404, 'GET /topics/999999 is 404');

$fixture = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/fixture.php');
try
{
	shell_exec($fixture.' hide 1 2');
	check(api_call('GET', '/forums/1')['status'] === 404, 'hidden forum 404 for guest');
	check(api_call('GET', '/forums/1/topics')['status'] === 404, 'hidden forum topics 404 for guest');
	check(api_call('GET', '/topics/1')['status'] === 404, 'topic in hidden forum 404 for guest');
	$ids = [];
	foreach (api_call('GET', '/forums')['json']['data'] ?? [] as $c)
		foreach ($c['forums'] as $f)
			$ids[] = $f['id'];
	check(!in_array(1, $ids, true), 'hidden forum absent from /forums');
}
finally
{
	shell_exec($fixture.' show 1 2');
}
check(api_call('GET', '/forums/1')['status'] === 200, 'forum visible again after fixture show');
