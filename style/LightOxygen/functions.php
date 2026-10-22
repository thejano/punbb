<?php

function lo_theme_url()
{
	global $base_url;

	return $base_url.'/style/LightOxygen';
}

// Dev server tags when dist/hot exists, otherwise the built assets from the Vite manifest.
function lo_asset_tags()
{
	$dir = FORUM_ROOT.'style/LightOxygen/dist/';
	$url = lo_theme_url().'/dist/';

	if (is_file($dir.'hot'))
	{
		$origin = rtrim(forum_trim(file_get_contents($dir.'hot')), '/');
		return '<script type="module" src="'.forum_htmlencode($origin).'/@vite/client"></script>'."\n".
			'<script type="module" src="'.forum_htmlencode($origin).'/src/main.jsx"></script>';
	}

	$manifest = is_file($dir.'.vite/manifest.json') ? json_decode(file_get_contents($dir.'.vite/manifest.json'), true) : null;
	if (!is_array($manifest) || !isset($manifest['src/main.jsx']))
		return '<!-- LightOxygen: run npm run build in style/LightOxygen -->';

	$entry = $manifest['src/main.jsx'];
	$tags = array();
	foreach (isset($entry['css']) ? $entry['css'] : array() as $css)
		$tags[] = '<link rel="stylesheet" href="'.forum_htmlencode($url.$css).'" />';
	$tags[] = '<script type="module" src="'.forum_htmlencode($url.$entry['file']).'"></script>';

	return implode("\n", $tags);
}
