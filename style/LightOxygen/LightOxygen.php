<?php

require_once FORUM_ROOT.'style/LightOxygen/functions.php';

$forum_loader->add_css('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&family=Inter:wght@400;500;600&display=swap', array('type' => 'url', 'group' => FORUM_CSS_GROUP_SYSTEM, 'media' => 'screen'));

echo lo_asset_tags();

if (isset($tpl_main))
{
	// lo_activity is filled by a later task
	$tpl_main = str_replace(
		array('{{BASE_URL}}', '{{THEME_URL}}', '<!-- lo_year -->', '<!-- lo_nav -->', '<!-- lo_auth -->', '<!-- lo_search -->', '<!-- lo_meta -->', '<!-- lo_activity -->'),
		array($base_url, lo_theme_url(), date('Y'), lo_nav(), lo_auth(), lo_search(), lo_meta(), ''),
		$tpl_main
	);
}
