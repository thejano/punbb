<?php
$token = api_call('POST', '/auth/login', ['username' => 'admin', 'password' => 'secret123'])['json']['data']['token'] ?? '';
$auth = ['Authorization: Bearer '.$token];
$utoken = api_call('POST', '/auth/login', ['username' => 'apitest', 'password' => 'pass1234'])['json']['data']['token'] ?? '';
$uauth = ['Authorization: Bearer '.$utoken];

$subject = 'API topic '.time();
$r = api_call('POST', '/forums/1/topics', ['subject' => $subject, 'message' => 'Body'], $auth);
$tid = $r['json']['data']['topic_id'] ?? 0;
check($r['status'] === 201 && $tid > 0 && ($r['json']['data']['post_id'] ?? 0) > 0, 'create topic 201');
check((api_call('GET', '/topics/'.$tid)['json']['data']['subject'] ?? '') === $subject, 'new topic readable');

$r = api_call('POST', '/topics/'.$tid.'/posts', ['message' => 'Reply'], $auth);
check($r['status'] === 201 && ($r['json']['data']['post_id'] ?? 0) > 0, 'reply 201');
check((api_call('GET', '/topics/'.$tid)['json']['data']['num_replies'] ?? -1) === 1, 'num_replies is 1');

$r = api_call('POST', '/forums/1/topics', ['subject' => '', 'message' => 'x'], $auth);
check($r['status'] === 422 && isset($r['json']['error']['fields']['subject']), 'empty subject 422');
$r = api_call('POST', '/forums/1/topics', ['subject' => 'Big', 'message' => str_repeat('a', 65536)], $auth);
check($r['status'] === 422 && isset($r['json']['error']['fields']['message']), 'oversize message 422');
$r = api_call('POST', '/topics/'.$tid.'/posts', ['message' => '[b]unclosed'], $auth);
check($r['status'] === 422 && isset($r['json']['error']['fields']['message']), 'bbcode error 422');

check(api_call('POST', '/topics/1/posts', ['message' => 'x'])['status'] === 401, 'guest reply 401');
check(api_call('POST', '/forums/1/topics', ['subject' => 'x', 'message' => 'x'])['status'] === 401, 'guest topic 401');
check(api_call('POST', '/forums/999999/topics', ['subject' => 'x', 'message' => 'x'], $auth)['status'] === 404, 'topic in missing forum 404');

if ($utoken === '')
	echo "skip apitest checks (no apitest user)\n";
else
{
	api_call('POST', '/topics/'.$tid.'/posts', ['message' => 'First by user'], $uauth); // 201, or 429 if a previous run was under a minute ago
	$r = api_call('POST', '/topics/'.$tid.'/posts', ['message' => 'Second by user'], $uauth);
	check($r['status'] === 429 && ($r['json']['error']['code'] ?? '') === 'flood' && isset($r['json']['error']['fields']['retry_after']), 'second reply is flood 429');

	$fixture = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/fixture.php');
	try
	{
		shell_exec($fixture.' close '.$tid);
		check(api_call('POST', '/topics/'.$tid.'/posts', ['message' => 'Closed'], $uauth)['status'] === 403, 'member reply to closed topic 403');
		check(api_call('POST', '/topics/'.$tid.'/posts', ['message' => 'Closed mod'], $auth)['status'] === 201, 'admin reply to closed topic 201');
	}
	finally
	{
		shell_exec($fixture.' open '.$tid);
	}
}
