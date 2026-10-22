<?php
if (PHP_SAPI !== 'cli') exit;
// Renders BBCode samples through preparse_bbcode() + parse_message() and compares with tests/fixtures/parser_expected.json.
//   php tests/parser_test.php            compare
//   php tests/parser_test.php --capture  rewrite the fixture from the current parser

define('FORUM_ROOT', dirname(__DIR__).'/');
require FORUM_ROOT.'include/essentials.php';
require FORUM_ROOT.'lang/English/common.php';
require FORUM_ROOT.'include/parser.php';

// pin the settings the renderer reads so the run does not depend on the board's options
$forum_config = array_merge($forum_config, array('p_message_bbcode' => '1', 'p_message_img_tag' => '1', 'p_sig_bbcode' => '1',
	'p_sig_img_tag' => '1', 'o_make_links' => '1', 'o_censoring' => '0', 'o_smilies' => '1', 'o_quote_depth' => '3'));
$forum_user = array('show_img' => '1', 'show_img_sig' => '1', 'show_smilies' => '1');
$base_url = 'http://forum.test';

$samples = array(
	'plain'          => "Plain <text> & \"quotes\" 'single'",
	'biu'            => '[b]bold[/b] [i]italic[/i] [u]under[/u] [b][i]both[/i][/b]',
	's_unknown'      => '[s]strike[/s]',
	'color'          => '[color=red]red[/color] [colour=#00ff00]green[/colour] [color=#abc]short[/color]',
	'heading'        => "[h]Heading[/h]\nafter",
	'quote'          => '[quote]plain quote[/quote]',
	'quote_named'    => '[quote=Alice]hi there[/quote]',
	'quote_nested'   => '[quote=A][quote="B"]inner[/quote]outer[/quote]',
	'code'           => "[code]<b>[b]not bold[/b]</b>\n\tindent[/code] after",
	'url_plain'      => '[url]http://example.com/path[/url]',
	'url_named'      => '[url=http://example.com/a?b=1&c=2]a link[/url]',
	'url_www'        => '[url]www.example.com[/url]',
	'url_javascript' => '[url=javascript:alert(1)]x[/url]',
	'url_scheme'     => '[url=file:///etc/x]y[/url] [url=data://x]z[/url] [url]ftp://files.example.com/a[/url]',
	'img'            => '[img]http://example.com/a.png[/img]',
	'img_alt'        => '[img=alt "text"]https://example.com/a.png[/img]',
	'img_data'       => '[img]data:image/png;base64,AAAA[/img]',
	'email'          => '[email]a@example.com[/email] [email=b@example.com]mail me[/email]',
	'list'           => '[list][*]one[/*][*]two[/*][/list][list=1][*]a[/*][/list][list=a][*]b[/*][/list]',
	'autolink'       => 'see http://example.com/x?y=1 and www.example.org now',
	'unclosed'       => '[b]bold without end',
	'php_syntax'     => '[b]$x {$y} ${z} \\" \\\\ "end"[/b]',
	'custom_eng'     => '[eng]english "text"[/eng]',
	'custom_misc'    => '[note]n[/note][warning]w[/warning][helle]h[/helle][articlelist]a[/articlelist][youtube]abc[/youtube][hellwasraw]x[/hellwasraw]',
	'smilies_ws'     => "hi :) there\n\nnew\tpara  spaced",
);

// samples whose "before" output was a fatal error; afterwards they must render without a fatal and without raw markup from the input
$malformed = array('custom_eng', 'custom_misc');

function render_sample(string $text): string
{
	$errors = array();
	try
	{
		$pre = preparse_bbcode($text, $errors);
		return empty($errors) ? parse_message($pre, '0') : 'ERRORS: '.implode(' | ', $errors);
	}
	catch (Throwable $e)
	{
		return 'FATAL: '.get_class($e);
	}
}

$file = __DIR__.'/fixtures/parser_expected.json';
$out = array();
foreach ($samples as $name => $text)
	$out[$name] = render_sample($text);

if (in_array('--capture', $_SERVER['argv'], true))
{
	file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
	echo 'captured '.count($out)." samples\n";
	exit(0);
}

$expected = json_decode((string)file_get_contents($file), true);
$fail = 0;
foreach ($out as $name => $html)
{
	if (in_array($name, $malformed, true))
		$ok = strpos($html, 'FATAL') !== 0 && strpos($html, '"text"') === false && strpos($html, '$1') === false;
	else
		$ok = isset($expected[$name]) && $expected[$name] === $html;
	if (!$ok)
	{
		$fail++;
		echo "FAIL $name\n  expected: ".($expected[$name] ?? '(none)')."\n  got:      $html\n";
	}
	else
		echo "ok   $name\n";
}
// links must never carry a script scheme
foreach ($out as $name => $html)
	if (preg_match('#(href|src)="\s*(javascript|data|vbscript|jscript):#i', $html))
	{
		$fail++;
		echo "FAIL $name carries a script scheme\n";
	}

echo $fail ? "\n$fail failure(s)\n" : "\nall passed\n";
exit($fail ? 1 : 0);
