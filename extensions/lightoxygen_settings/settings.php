<?php
/**
 * LightOxygen settings section of admin/settings.php (section=lightoxygen).
 * Included from the aop_new_section hook, and from aop_new_section_validation to show the form again with errors.
 *
 * @package lightoxygen_settings
 */

if (!defined('FORUM')) die();

require_once $ext_info['path'].'/functions.php';

$forum_page['group_count'] = $forum_page['item_count'] = $forum_page['fld_count'] = 0;
$forum_page['form_action'] = forum_link($forum_url['admin_settings_lightoxygen']);

$forum_page['crumbs'] = array(
	array($forum_config['o_board_title'], forum_link($forum_url['index'])),
	array($lang_admin_common['Forum administration'], forum_link($forum_url['admin_index'])),
	array($lang_admin_common['Extensions'], forum_link($forum_url['admin_extensions_manage'])),
	array($lang_lo_settings['LightOxygen'], $forum_page['form_action'])
);

define('FORUM_PAGE_SECTION', 'extensions');
define('FORUM_PAGE', 'admin-settings-lightoxygen');
require FORUM_ROOT.'header.php';

// START SUBST - <!-- forum_main -->
ob_start();

// current values; after a rejected save the submitted ones
$lo_val = function ($key) use ($forum_config)
{
	global $lo_posted;
	if (isset($lo_posted) && array_key_exists($key, $lo_posted))
		return (string)$lo_posted[$key];
	return isset($forum_config['o_'.$key]) ? (string)$forum_config['o_'.$key] : lo_ext_settings()[$key][1];
};

$lo_select = function ($key, $label, $options, $help = '') use ($lo_val, &$forum_page)
{
	$out = '';
	foreach ($options as $value => $text)
	{
		$off = is_array($text);
		$out .= '<option value="'.$value.'"'.($lo_val($key) === (string)$value ? ' selected="selected"' : '').($off ? ' disabled="disabled"' : '').'>'.($off ? $text[0] : $text).'</option>';
	}
	echo "\t\t\t\t".'<div class="sf-set set'.++$forum_page['item_count'].'"><div class="sf-box select">'.
		'<label for="fld'.++$forum_page['fld_count'].'"><span>'.$label.'</span>'.($help != '' ? ' <small>'.$help.'</small>' : '').'</label><br />'.
		'<span class="fld-input"><select id="fld'.$forum_page['fld_count'].'" name="form['.$key.']">'.$out.'</select></span></div></div>'."\n";
};

$lo_check = function ($key, $label, $text) use ($lo_val, &$forum_page)
{
	echo "\t\t\t\t".'<div class="sf-set set'.++$forum_page['item_count'].'"><div class="sf-box checkbox">'.
		'<span class="fld-input"><input type="checkbox" id="fld'.++$forum_page['fld_count'].'" name="form['.$key.']" value="1"'.($lo_val($key) === '1' ? ' checked="checked"' : '').' /></span>'.
		'<label for="fld'.$forum_page['fld_count'].'"><span>'.$label.'</span> '.$text.'</label></div></div>'."\n";
};

$lo_text = function ($key, $label, $help = '', $attrs = 'size="50" maxlength="255"', $type = 'text') use ($lo_val, &$forum_page)
{
	echo "\t\t\t\t".'<div class="sf-set set'.++$forum_page['item_count'].'"><div class="sf-box text">'.
		'<label for="fld'.++$forum_page['fld_count'].'"><span>'.$label.'</span>'.($help != '' ? ' <small>'.$help.'</small>' : '').'</label><br />'.
		'<span class="fld-input"><input type="'.$type.'" id="fld'.$forum_page['fld_count'].'" name="form['.$key.']" '.$attrs.' value="'.forum_htmlencode($key === 'lo_hero_bg' && lo_ext_is_own_file($lo_val($key), 'hero') ? '' : $lo_val($key)).'" dir="auto" /></span></div></div>'."\n";
};

$lo_area = function ($key, $label, $help, $rows, $ltr = false) use ($lo_val, &$forum_page)
{
	echo "\t\t\t\t".'<div class="txt-set set'.++$forum_page['item_count'].'"><div class="txt-box textarea">'.
		'<label for="fld'.++$forum_page['fld_count'].'"><span>'.$label.'</span> <small>'.$help.'</small></label><br />'.
		'<div class="txt-input"><span class="fld-input"><textarea id="fld'.$forum_page['fld_count'].'" name="form['.$key.']" rows="'.$rows.'" cols="50"'.($ltr ? ' dir="ltr"' : ' dir="auto"').'>'.forum_htmlencode($lo_val($key)).'</textarea></span></div></div></div>'."\n";
};

// a file field; with a stored file, a preview and a remove button (a submit of the same form, so the CSRF token goes with it)
$lo_file = function ($name, $key, $label, $help, $accept) use ($lo_val, &$forum_page, $base_url, $lang_lo_settings)
{
	$cur = $lo_val($key);
	echo "\t\t\t\t".'<div class="sf-set set'.++$forum_page['item_count'].'"><div class="sf-box text">'.
		'<label for="fld'.++$forum_page['fld_count'].'"><span>'.$label.'</span> <small>'.$help.'</small></label><br />'.
		'<span class="fld-input"><input type="file" id="fld'.$forum_page['fld_count'].'" name="lo_'.$name.'_file" accept="'.$accept.'" /></span>';
	if (lo_ext_is_own_file($cur, $name))
		echo '<p class="lo-ext-current"><span>'.$lang_lo_settings['Current image'].': <bdi dir="ltr">'.forum_htmlencode(basename(strtok($cur, '?'))).'</bdi></span> <img src="'.forum_htmlencode($base_url.'/'.$cur).'" alt="" style="max-height:4rem;max-width:12rem" /> '.
			'<span class="submit"><input type="submit" name="lo_remove['.$name.']" value="'.$lang_lo_settings['Remove'].'" aria-label="'.$lang_lo_settings['Remove'].': '.$label.'" /></span></p>';
	echo '</div></div>'."\n";
};

$lo_group = function ($legend) use (&$forum_page)
{
	echo "\t\t\t".'<fieldset class="frm-group group'.++$forum_page['group_count'].'">'."\n\t\t\t\t".'<legend class="group-legend"><strong>'.$legend.'</strong></legend>'."\n";
};

$L = $lang_lo_settings;

?>
	<div class="main-content main-frm">
<?php if (!empty($lo_errors)): ?>
		<div class="ct-box error-box">
			<h2 class="warn hn"><?php echo $L['Errors'] ?></h2>
			<ul class="error-list">
				<?php foreach ($lo_errors as $lo_error) echo '<li class="warn"><span>'.forum_htmlencode($lo_error).'</span></li>' ?>

			</ul>
		</div>
<?php endif; ?>
		<form class="frm-form" method="post" accept-charset="utf-8" enctype="multipart/form-data" action="<?php echo $forum_page['form_action'] ?>">
			<div class="hidden">
				<input type="hidden" name="csrf_token" value="<?php echo generate_form_token($forum_page['form_action']) ?>" />
				<input type="hidden" name="form_sent" value="1" />
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo LO_EXT_MAX_UPLOAD ?>" />
			</div>
			<div class="content-head">
				<h2 class="hn"><span><?php echo $L['LightOxygen'] ?></span></h2>
			</div>
			<?php /* Enter in a field submits the first submit button: make that Save, not a Remove button */ ?>
			<input type="submit" name="save" value="<?php echo $lang_admin_common['Save changes'] ?>" tabindex="-1" aria-hidden="true" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);border:0;padding:0" />
			<div class="ct-box info-box"><p><?php echo $L['Intro'] ?></p></div>
<?php

$lo_group($L['Layout legend']);
$lo_select('lo_default_layout', $L['Default layout'], array('sidebar' => $L['Layout sidebar'], 'wide' => $L['Layout wide']), $L['Default layout help']);
$lo_select('lo_default_skin', $L['Default skin'], array('calm' => $L['Skin calm'], 'meko' => $L['Skin meko']));
$lo_check('lo_allow_switch', $L['Allow switch'], $L['Allow switch label']);
$lo_select('lo_color_scheme', $L['Color scheme'], array('auto' => $L['Scheme auto'], 'light' => $L['Scheme light'], 'dark' => $L['Scheme dark']));
$lo_text('lo_accent', $L['Accent'], $L['Accent help'], 'size="8" maxlength="7" pattern="#[0-9a-fA-F]{6}" placeholder="#2f5f8a"');
$lo_select('lo_font', $L['Font'], array('default' => $L['Font default'], 'system' => $L['Font system'], 'bahij' => lo_ext_bahij_available() ? $L['Font bahij'] : array($L['Font bahij'].' '.$L['Font unavailable'])));
echo "\t\t\t".'</fieldset>'."\n";

$lo_group($L['Hero legend']);
$lo_select('lo_hero_mode', $L['Hero mode'], array('guests' => $L['Hero guests'], 'all' => $L['Hero all'], 'none' => $L['Hero none']), $L['Hero mode help']);
$lo_text('lo_hero_title', $L['Hero title'], $L['Hero title help']);
$lo_text('lo_hero_text', $L['Hero text'], $L['Hero text help']);
$lo_text('lo_hero_bg', $L['Hero bg'], $L['Hero bg help'], 'size="50" maxlength="2000"');
$lo_file('hero', 'lo_hero_bg', $L['Hero upload'], $L['Hero upload help'], 'image/png,image/jpeg,image/webp');
$lo_check('lo_show_member_avatars', $L['Member avatars'], $L['Member avatars label']);
echo "\t\t\t".'</fieldset>'."\n";

$lo_group($L['Branding legend']);
$lo_file('logo', 'lo_logo', $L['Logo upload'], $L['Logo upload help'], 'image/png,image/webp,image/svg+xml');
$lo_file('favicon', 'lo_favicon', $L['Favicon upload'], $L['Favicon upload help'], 'image/png,image/x-icon,.ico');
echo "\t\t\t".'</fieldset>'."\n";

$lo_group($L['Board legend']);
$lo_check('lo_category_colors', $L['Category colors'], $L['Category colors label']);
$lo_check('lo_show_stats', $L['Show stats'], $L['Show stats label']);
$lo_text('lo_activity_refresh', $L['Activity refresh'], $L['Activity refresh help'], 'size="5" maxlength="3" min="0" max="600"', 'number');
echo "\t\t\t".'</fieldset>'."\n";

$lo_group($L['Footer legend']);
$lo_text('lo_footer_text', $L['Footer text'], $L['Footer text help']);
$lo_area('lo_social_links', $L['Social links'], $L['Social links help'], 5, true);
$lo_area('lo_custom_css', $L['Custom css'], $L['Custom css help'], 8, true);
echo "\t\t\t".'</fieldset>'."\n";

?>
			<div class="frm-buttons">
				<span class="submit primary"><input type="submit" name="save" value="<?php echo $lang_admin_common['Save changes'] ?>" /></span>
			</div>
		</form>
	</div>
<?php
