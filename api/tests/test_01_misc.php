<?php
$r = api_call('GET', '/config');
check($r['status'] === 200, 'GET /config 200');
check(is_string($r['json']['data']['board_title'] ?? null), 'config board_title is string');
check(isset($r['json']['data']['default_lang'], $r['json']['data']['default_style']), 'config default_lang/default_style');
check(isset($r['json']['data']['time_format']), 'config time_format');

$r = api_call('GET', '/me');
check($r['status'] === 200, 'GET /me 200');
check(($r['json']['data']['is_guest'] ?? null) === true, 'me is_guest true');
check(($r['json']['data']['id'] ?? null) === 1, 'me id 1');

check(api_call('GET', '/forums/')['status'] === 200, 'GET /forums/ trailing slash 200');

$r = api_call('GET', '/nope');
check($r['status'] === 404 && ($r['json']['error']['code'] ?? '') === 'not_found', 'GET /nope 404 not_found');

$r = api_call('DELETE', '/config');
check($r['status'] === 405 && ($r['json']['error']['code'] ?? '') === 'method_not_allowed', 'DELETE /config 405');
