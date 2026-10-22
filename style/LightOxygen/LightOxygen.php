<?php

require_once FORUM_ROOT.'style/LightOxygen/functions.php';

// ?lo_layout=sidebar|wide and ?lo_skin=calm|meko store the choice and reload the same URL without the parameter
foreach (array('lo_layout' => array('sidebar', 'wide'), 'lo_skin' => array('calm', 'meko')) as $lo_name => $lo_values)
{
	if (!isset($_GET[$lo_name]))
		continue;

	if (in_array($_GET[$lo_name], $lo_values, true))
		forum_setcookie($lo_name, $_GET[$lo_name], time() + 31536000);

	$lo_target = lo_pref_url($lo_name);
	if (!headers_sent())
	{
		header('Location: '.$lo_target, true, 302);
		exit;
	}
	echo '<meta http-equiv="refresh" content="0;url='.forum_htmlencode($lo_target).'" />';
	break;
}

if (lo_font() === 'default')
	$forum_loader->add_css('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&family=Inter:wght@400;500;600&display=swap', array('type' => 'url', 'group' => FORUM_CSS_GROUP_SYSTEM, 'media' => 'screen'));

echo lo_asset_tags();
echo lo_head_style();

if (isset($tpl_main))
{
	$lo_sidebar = lo_sidebar();
	$tpl_main = str_replace(
		array('{{BASE_URL}}', '{{THEME_URL}}', '<!-- lo_year -->', '<!-- lo_nav -->', '<!-- lo_auth -->', '<!-- lo_search -->', '<!-- lo_meta -->', '<!-- lo_sidebar -->', '<!-- lo_view_menu -->', '<!-- lo_body_class -->', '<!-- lo_hero -->', '<!-- lo_main_attr -->', '<!-- lo_html_attr -->', '{{LOGO_URL}}', '<!-- lo_footer -->'),
		array($base_url, lo_theme_url(), date('Y'), lo_nav(), lo_auth(), lo_search(), lo_meta(), $lo_sidebar, lo_view_menu(), 'lo-layout-'.lo_layout().' lo-skin-'.lo_skin().($lo_sidebar !== '' ? ' lo-has-sidebar' : '').lo_body_class(), lo_hero(), lo_main_attr(), lo_html_attr(), forum_htmlencode(lo_logo_url()), lo_footer()),
		$tpl_main
	);
}
