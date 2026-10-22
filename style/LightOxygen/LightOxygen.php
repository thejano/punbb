<?php

require_once FORUM_ROOT.'style/LightOxygen/functions.php';

// ?lo_layout=sidebar|wide, ?lo_skin=calm|meko and ?lo_scheme=auto|light|dark store the choice and reload the same URL without the parameter
// (redirect() includes this file from inside a function)
global $forum_db, $forum_user;
foreach (array('lo_layout' => array('sidebar', 'wide'), 'lo_skin' => array('calm', 'meko'), 'lo_scheme' => array('auto', 'light', 'dark')) as $lo_name => $lo_values)
{
	if (!isset($_GET[$lo_name]))
		continue;

	if (in_array($_GET[$lo_name], $lo_values, true))
	{
		forum_setcookie($lo_name, $_GET[$lo_name], time() + 31536000);

		// a member's choice goes into their own users row too (the column comes with lightoxygen_settings), so it follows them;
		// only with the switch link's token, so a cross-site request changes no more than the cookie
		if (defined('LO_SETTINGS') && empty($forum_user['is_guest']) && array_key_exists($lo_name, $forum_user) &&
			isset($_GET['lo_t']) && is_string($_GET['lo_t']) && hash_equals(generate_form_token('lo_pref'), $_GET['lo_t']))
			$forum_db->query_build(array('UPDATE' => 'users', 'SET' => $lo_name.'=\''.$forum_db->escape($_GET[$lo_name]).'\'', 'WHERE' => 'id='.(int)$forum_user['id'])) or error(__FILE__, __LINE__);
	}

	$lo_target = lo_pref_url($lo_name);
	if (!headers_sent())
	{
		// commit the UPDATE above, as footer.php would (exit skips it)
		$forum_db->end_transaction();
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

// redirect() requires this file after loading redirect.tpl into its local $tpl_redir
if (isset($tpl_redir))
	$tpl_redir = str_replace(array('{{BASE_URL}}', '{{THEME_URL}}', '<!-- lo_meta -->', '<!-- lo_html_attr -->'), array($base_url, lo_theme_url(), lo_meta(), lo_html_attr()), $tpl_redir);
