<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/lib.php';

// the suite logs in with wrong passwords on purpose; start each run with an empty login throttle (a local board only)
@unlink(dirname(__DIR__, 2).'/cache/throttle.json');

$files = glob(__DIR__.'/test_*.php');
sort($files);
foreach ($files as $file)
{
	echo '== '.basename($file)."\n";
	require $file;
}

echo $GLOBALS['api_failures'] ? "\n{$GLOBALS['api_failures']} failure(s)\n" : "\nall passed\n";
exit($GLOBALS['api_failures'] ? 1 : 0);
