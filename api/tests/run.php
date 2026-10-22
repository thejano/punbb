<?php
require __DIR__.'/lib.php';

$files = glob(__DIR__.'/test_*.php');
sort($files);
foreach ($files as $file)
{
	echo '== '.basename($file)."\n";
	require $file;
}

echo $GLOBALS['api_failures'] ? "\n{$GLOBALS['api_failures']} failure(s)\n" : "\nall passed\n";
exit($GLOBALS['api_failures'] ? 1 : 0);
