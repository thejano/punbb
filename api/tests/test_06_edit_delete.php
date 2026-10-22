<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$token = api_call('POST', '/auth/login', ['username' => 'admin', 'password' => 'secret123'])['json']['data']['token'] ?? '';
$auth = ['Authorization: Bearer '.$token];
$utoken = api_call('POST', '/auth/login', ['username' => 'apitest', 'password' => 'pass1234'])['json']['data']['token'] ?? '';
$uauth = ['Authorization: Bearer '.$utoken];

$r = api_call('POST', '/forums/1/topics', ['subject' => 'Edit me '.time(), 'message' => 'First body'], $auth);
$tid = $r['json']['data']['topic_id'] ?? 0;
$first = $r['json']['data']['post_id'] ?? 0;
$reply = api_call('POST', '/topics/'.$tid.'/posts', ['message' => 'Reply body'], $auth)['json']['data']['post_id'] ?? 0;
check($tid > 0 && $first > 0 && $reply > 0, 'edit fixtures created');

$r = api_call('PUT', '/posts/'.$reply, ['message' => 'Changed reply'], $auth);
check($r['status'] === 200 && ($r['json']['data']['message'] ?? '') === 'Changed reply' && ($r['json']['data']['edited'] ?? 0) > 0, 'edit reply 200 with edited set');

$r = api_call('PUT', '/posts/'.$reply, ['message' => 'Silent change', 'silent' => true], $auth);
check($r['status'] === 200 && ($r['json']['data']['message'] ?? '') === 'Silent change', 'silent edit 200');

$r = api_call('PUT', '/posts/'.$first, ['message' => 'New first', 'subject' => 'Renamed topic'], $auth);
check($r['status'] === 200 && (api_call('GET', '/topics/'.$tid)['json']['data']['subject'] ?? '') === 'Renamed topic', 'first post edit renames topic');

$r = api_call('PUT', '/posts/'.$first, ['message' => 'x', 'subject' => ''], $auth);
check($r['status'] === 422 && isset($r['json']['error']['fields']['subject']), 'empty subject 422');
$r = api_call('PUT', '/posts/'.$reply, ['message' => ''], $auth);
check($r['status'] === 422 && isset($r['json']['error']['fields']['message']), 'empty message 422');

check(api_call('PUT', '/posts/'.$reply, ['message' => 'x'])['status'] === 401, 'guest edit 401');
check(api_call('DELETE', '/posts/'.$reply)['status'] === 401, 'guest delete 401');
check(api_call('PUT', '/posts/999999', ['message' => 'x'], $auth)['status'] === 404, 'edit missing post 404');
check(api_call('DELETE', '/posts/999999', null, $auth)['status'] === 404, 'delete missing post 404');

if ($utoken !== '')
{
	check(api_call('PUT', '/posts/'.$reply, ['message' => 'Hijack'], $uauth)['status'] === 403, 'other user edit 403');
	check(api_call('DELETE', '/posts/'.$reply, null, $uauth)['status'] === 403, 'other user delete 403');
}

$r = api_call('DELETE', '/posts/'.$reply, null, $auth);
check($r['status'] === 200 && ($r['json']['data']['deleted'] ?? '') === 'post' && ($r['json']['data']['topic_id'] ?? 0) === $tid, 'delete reply 200');
check((api_call('GET', '/topics/'.$tid)['json']['data']['num_replies'] ?? -1) === 0, 'num_replies back to 0');

$r = api_call('DELETE', '/posts/'.$first, null, $auth);
check($r['status'] === 200 && ($r['json']['data']['deleted'] ?? '') === 'topic' && ($r['json']['data']['forum_id'] ?? 0) === 1, 'delete first post deletes topic');
check(api_call('GET', '/topics/'.$tid)['status'] === 404, 'deleted topic 404');
