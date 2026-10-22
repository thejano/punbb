<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// lo_pref_url() behind friendly URLs (SCRIPT_NAME is rewrite.php); run: php test/pref_url_test.php
require __DIR__.'/../functions.php';

$base_url = 'http://example.com/board';
$fail = 0;
function check($label, $got, $want)
{
	global $fail;
	echo ($got === $want ? 'ok   ' : 'FAIL ').$label.($got === $want ? '' : " (got $got)")."\n";
	$fail |= $got !== $want;
}

$_SERVER['SCRIPT_NAME'] = '/board/rewrite.php';
$_GET = array('id' => '2', 'x' => '1', 'lo_layout' => 'sidebar');
$_SERVER['REQUEST_URI'] = '/board/forum/2/wide-test/?x=1&lo_layout=sidebar';
check('rewrite: keeps the friendly path and its query', lo_pref_url('lo_layout', 'wide'), 'http://example.com/board/forum/2/wide-test/?x=1&lo_layout=wide');
check('rewrite: drops the switch parameter', lo_pref_url('lo_layout'), 'http://example.com/board/forum/2/wide-test/?x=1');
foreach (array('/board/forum/2/a\\b/', '/board/forum/../../x/', '/board//evil.com/', '/other/forum/2/', '//evil.com/board/') as $p)
{
	$_SERVER['REQUEST_URI'] = $p;
	check('rewrite: unsafe path '.$p.' falls back to the index', lo_pref_url('lo_layout'), 'http://example.com/board/index.php');
}

$base_url = 'http://127.0.0.1:8085';
$_SERVER['SCRIPT_NAME'] = '/rewrite.php';
$_SERVER['REQUEST_URI'] = '/forum/2/wide-test/?x=1';
check('rewrite at the web root', lo_pref_url('lo_layout', 'wide'), 'http://127.0.0.1:8085/forum/2/wide-test/?x=1&lo_layout=wide');

$base_url = 'http://example.com/board';
$_SERVER['SCRIPT_NAME'] = '/board/viewforum.php';
$_GET = array('id' => '1', 'lo_skin' => 'meko');
check('plain script keeps SCRIPT_NAME', lo_pref_url('lo_skin'), 'http://example.com/board/viewforum.php?id=1');

// a logged-in member's switch link carries the profile-save token; the redirect target drops it again
function generate_form_token($target) { return sha1($target.'secret'); }
$forum_user = array('is_guest' => false, 'csrf_token' => 'secret');
$_GET = array('id' => '1');
check('member switch link carries lo_t', lo_pref_url('lo_skin', 'meko'), 'http://example.com/board/viewforum.php?id=1&lo_skin=meko&lo_t='.sha1('lo_prefsecret'));
$_GET = array('id' => '1', 'lo_skin' => 'meko', 'lo_t' => 'x');
check('redirect target drops lo_t', lo_pref_url('lo_skin'), 'http://example.com/board/viewforum.php?id=1');
$forum_user['is_guest'] = true;
$_GET = array('id' => '1');
check('guest switch link has no lo_t', lo_pref_url('lo_skin', 'meko'), 'http://example.com/board/viewforum.php?id=1&lo_skin=meko');

exit($fail);
