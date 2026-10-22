<?php
$r = api_call('GET', '/users/2');
check($r['status'] === 200 && ($r['json']['data']['username'] ?? '') === 'admin', 'user 2 is admin');
$leak = array_intersect(['email', 'password', 'salt', 'registration_ip', 'admin_note'], array_keys($r['json']['data'] ?? []));
check(!$leak && isset($r['json']['data']['group_id'], $r['json']['data']['signature_html'], $r['json']['data']['last_visit']), 'user has no private fields');
check(api_call('GET', '/users/1')['status'] === 404, 'guest user 404');
check(api_call('GET', '/users/999999')['status'] === 404, 'missing user 404');

$names = array_column(api_call('GET', '/users?q=ad')['json']['data'] ?? [], 'username');
check(in_array('admin', $names, true), 'users?q=ad lists admin');
check(api_call('GET', '/users?q=a')['status'] === 400, 'users?q=a is 400');
check((api_call('GET', '/users?q=a%25')['json']['data'] ?? null) === [], 'users?q=a% does not widen to admin');
check((api_call('GET', '/users?q=%25%25')['json']['data'] ?? null) === [], 'users?q=%% matches nothing');

$ids = array_column(api_call('GET', '/search?q=body')['json']['data'] ?? [], 'id');
check($ids && api_call('GET', '/search?q=body')['status'] === 200, 'search body finds topics');
check(api_call('GET', '/search?q=')['status'] === 400, 'search empty is 400');
check(api_call('GET', '/search?q=a')['status'] === 400, 'search short word is 400');
check((api_call('GET', '/search?q=body+zzqxyznomatch')['json']['data'] ?? null) === [], 'search needs every word');

$r = api_call('GET', '/online');
check($r['status'] === 200 && ($r['json']['data']['guests'] ?? -1) >= 0 && is_array($r['json']['data']['users'] ?? null), 'online shape');

$r = api_call('GET', '/topics/recent?limit=3');
$d = $r['json']['data'] ?? [];
check($r['status'] === 200 && count($d) >= 1 && count($d) <= 3, 'recent limit 3');
$lp = array_column($d, 'last_post');
$sorted = $lp; rsort($sorted);
check($lp === $sorted, 'recent ordered by last_post desc');
check(count(api_call('GET', '/topics/recent?limit=999')['json']['data'] ?? []) <= 50, 'recent limit capped');
