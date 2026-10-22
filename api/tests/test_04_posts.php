<?php
$token = api_call('POST', '/auth/login', ['username' => 'admin', 'password' => 'secret123'])['json']['data']['token'] ?? '';
$auth = ['Authorization: Bearer '.$token];

$r = api_call('GET', '/topics/1/posts');
check($r['status'] === 200 && str_contains($r['json']['data'][0]['message_html'] ?? '', '<p'), 'topic posts have message_html');
check(($r['json']['meta']['total'] ?? 0) >= 1 && ($r['json']['meta']['page'] ?? 0) === 1 && isset($r['json']['meta']['pages']), 'topic posts meta');
$p = $r['json']['data'][0] ?? [];
check(array_keys($p) === ['id', 'topic_id', 'poster', 'message', 'message_html', 'hide_smilies', 'posted', 'posted_formatted', 'edited', 'edited_by', 'is_online', 'can_edit', 'can_delete'], 'post shape keys');
check(is_int($p['poster']['id'] ?? null) && isset($p['poster']['username']) && !isset($p['poster']['email']) && !isset($p['poster']['admin_note']), 'post poster is public');
check(is_bool($p['hide_smilies'] ?? null) && is_bool($p['is_online'] ?? null) && $p['can_edit'] === false && $p['can_delete'] === false, 'guest cannot edit or delete');

$r = api_call('GET', '/topics/1/posts', null, $auth);
check(($r['json']['data'][0]['can_edit'] ?? null) === true && ($r['json']['data'][0]['can_delete'] ?? null) === true, 'admin can edit and delete');

$r = api_call('GET', '/posts/1');
check($r['status'] === 200 && ($r['json']['data']['topic_id'] ?? 0) === 1, 'GET /posts/1');
check(api_call('GET', '/posts/999999')['status'] === 404, 'GET /posts/999999 is 404');
check(api_call('GET', '/topics/999999/posts')['status'] === 404, 'posts of missing topic 404');

$r = api_call('POST', '/preview', ['message' => 'Hello [b]x[/b]'], $auth);
check($r['status'] === 200 && str_contains($r['json']['data']['html'] ?? '', '<strong>x</strong>'), 'preview renders bbcode');
$r = api_call('POST', '/preview', ['message' => '[b]unclosed'], $auth);
check($r['status'] === 422 && isset($r['json']['error']['fields']['message']), 'preview parser error 422');
check(api_call('POST', '/preview', ['message' => 'hi'])['status'] === 403, 'guest preview without csrf 403');

$fixture = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/fixture.php');
try
{
	shell_exec($fixture.' hide 1 2');
	check(api_call('GET', '/topics/1/posts')['status'] === 404, 'posts in hidden forum 404 for guest');
	check(api_call('GET', '/posts/1')['status'] === 404, 'single post in hidden forum 404 for guest');
}
finally
{
	shell_exec($fixture.' show 1 2');
}
check(api_call('GET', '/topics/1/posts')['status'] === 200, 'posts visible again after fixture show');
