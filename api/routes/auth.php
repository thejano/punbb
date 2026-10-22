<?php

api_route('POST', '/auth/login', function () {
	global $forum_db, $forum_config, $forum_user, $db_type, $cookie_name;

	api_check_json_or_csrf();
	$in = api_input();
	$username = forum_trim(api_str($in, 'username'));
	$password = forum_trim(api_str($in, 'password'));

	$query = array(
		'SELECT'	=> 'u.id, u.group_id, u.password, u.salt',
		'FROM'		=> 'users AS u'
	);

	if (in_array($db_type, array('mysql', 'mysqli', 'mysql_innodb', 'mysqli_innodb')))
		$query['WHERE'] = 'username=\''.$forum_db->escape($username).'\'';
	else
		$query['WHERE'] = 'LOWER(username)=LOWER(\''.$forum_db->escape($username).'\')';

	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	$row = $forum_db->fetch_row($result);
	list($user_id, $group_id, $db_hash, $salt) = $row ?: array(0, 0, '', '');

	// reserves this attempt as a failure, shared with login.php (forum_login_throttle() in include/functions.php)
	if (!forum_login_throttle((int)$user_id, $username, $retry_after))
	{
		header('Retry-After: '.$retry_after);
		api_error('too_many_attempts', 'Too many attempts, try again later', 429);
	}

	$authorized = false;
	$hash = '';
	if ($username !== '' && !empty($db_hash))
	{
		$sha1_in_db = strlen($db_hash) == 40;
		$hash = forum_hash($password, $salt);

		if ($sha1_in_db && hash_equals($db_hash, $hash))
			$authorized = true;
		else if ((!$sha1_in_db && hash_equals($db_hash, md5($password))) || ($sha1_in_db && hash_equals($db_hash, sha1($password))))
		{
			$authorized = true;

			// Upgrade an old MD5 or unsalted SHA1 hash, as login.php does
			$salt = random_key(12);
			$hash = forum_hash($password, $salt);
			$query = array(
				'UPDATE'	=> 'users',
				'SET'		=> 'password=\''.$hash.'\', salt=\''.$forum_db->escape($salt).'\'',
				'WHERE'		=> 'id='.$user_id
			);
			$forum_db->query_build($query) or error(__FILE__, __LINE__);
		}
	}
	else
		forum_hash($password, random_key(12)); // an unknown username takes as long as a wrong password

	if (!$authorized)
		api_error('invalid_credentials', 'Wrong username or password', 401);

	forum_login_throttle_clear((int)$user_id, $username);
	forum_cancel_reset_key((int)$user_id);

	// First login of a verified registration promotes the user, as login.php does.
	if ($group_id == FORUM_UNVERIFIED)
	{
		$query = array(
			'UPDATE'	=> 'users',
			'SET'		=> 'group_id='.(int)$forum_config['o_default_user_group'],
			'WHERE'		=> 'id='.(int)$user_id
		);
		$forum_db->query_build($query) or error(__FILE__, __LINE__);

		if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
			require FORUM_ROOT.'include/cache.php';
		clean_stats_cache();
	}

	// Remove this user's guest entry from the online list
	$query = array(
		'DELETE'	=> 'online',
		'WHERE'		=> 'ident=\''.$forum_db->escape(get_remote_address()).'\''
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	$expire = !empty($in['remember']) ? time() + 1209600 : time() + $forum_config['o_timeout_visit'];
	$token = api_token((int)$user_id, (string)$salt, $expire);
	forum_setcookie($cookie_name, $token, $expire);

	$query = array(
		'SELECT'	=> 'u.*, g.g_id, g.g_user_title',
		'FROM'		=> 'users AS u',
		'JOINS'		=> array(array('INNER JOIN' => 'groups AS g', 'ON' => 'g.g_id=u.group_id')),
		'WHERE'		=> 'u.id='.(int)$user_id
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);

	api_json(['token' => $token, 'expires' => $expire, 'user' => api_user_public($forum_db->fetch_assoc($result))]);
});

api_route('POST', '/auth/logout', function () {
	global $cookie_name;

	$user = api_require_user();
	api_check_csrf();

	// a new login token ends every session and API token of this user, the bearer used here included
	forum_login_token((int)$user['id'], true);

	$expire = time() + 31536000;
	forum_setcookie($cookie_name, base64_encode('1|Guest|0|Guest'), $expire);
	api_json(['ok' => true]);
});

api_route('POST', '/auth/register', function () {
	global $forum_db, $forum_config, $forum_user, $lang_profile;

	if (!$forum_user['is_guest'])
		api_error('forbidden', 'You are already logged in', 403);
	if ($forum_config['o_regs_allow'] != '1')
		api_error('registration_closed', 'Registration is closed', 403);

	api_check_json_or_csrf();
	$in = api_input();
	$username = forum_trim(api_str($in, 'username'));
	$email = strtolower(forum_trim(api_str($in, 'email')));
	$verify = $forum_config['o_regs_verify'] == '1';
	$password = $verify ? random_key(8, true) : forum_trim(api_str($in, 'password'));

	require FORUM_ROOT.'lang/'.$forum_user['language'].'/profile.php';
	require_once FORUM_ROOT.'include/email.php';

	$fields = array();
	$errs = validate_username($username);
	if ($errs)
		$fields['username'] = implode(' ', $errs);
	if (utf8_strlen($password) < 4)
		$fields['password'] = 'Password is too short';
	if (!is_valid_email($email))
		$fields['email'] = 'Invalid email address';
	else
	{
		$banned_email = is_banned_email($email);
		if ($banned_email && $forum_config['p_allow_banned_email'] == '0')
			$fields['email'] = 'Banned email address';
	}

	// Clean old unverified registrations, as register.php does
	$query = array(
		'DELETE'	=> 'users',
		'WHERE'		=> 'group_id='.FORUM_UNVERIFIED.' AND activate_key IS NOT NULL AND registered < '.(time() - 259200)
	);
	$forum_db->query_build($query) or error(__FILE__, __LINE__);

	if (!isset($fields['email']) && $forum_config['p_allow_dupe_email'] == '0')
	{
		$query = array(
			'SELECT'	=> 'u.id',
			'FROM'		=> 'users AS u',
			'WHERE'		=> 'u.email=\''.$forum_db->escape($email).'\''
		);
		$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
		if ($forum_db->num_rows($result))
			$fields['email'] = 'Email address is already registered';
	}

	if ($fields)
		api_error('validation', 'Validation failed', 422, $fields);

	// Checked after validation so invalid requests get field errors rather than a flood error
	$query = array(
		'SELECT'	=> 'COUNT(u.id)',
		'FROM'		=> 'users AS u',
		'WHERE'		=> 'u.registration_ip=\''.$forum_db->escape(get_remote_address()).'\' AND u.registered>'.(time() - 3600)
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	if ($forum_db->result($result) > 0)
		api_error('registration_flood', 'Too many registrations from this address, try again later', 429);

	$language = $forum_config['o_default_lang'];
	if (api_str($in, 'language') !== '')
	{
		$lang = preg_replace('#[\.\\\/]#', '', api_str($in, 'language'));
		if (file_exists(FORUM_ROOT.'lang/'.$lang.'/common.php'))
			$language = $lang;
	}

	$timezone = isset($in['timezone']) ? floatval($in['timezone']) : $forum_config['o_default_timezone'];
	if ($timezone > 14.0 || $timezone < -12.0)
		$timezone = $forum_config['o_default_timezone'];
	$dst = (isset($in['dst']) && intval($in['dst']) === 1) ? 1 : $forum_config['o_default_dst'];

	$salt = random_key(12);
	$user_info = array(
		'username'				=> $username,
		'group_id'				=> $verify ? FORUM_UNVERIFIED : $forum_config['o_default_user_group'],
		'salt'					=> $salt,
		'password'				=> $password,
		'password_hash'			=> forum_hash($password, $salt),
		'email'					=> $email,
		'email_setting'			=> $forum_config['o_default_email_setting'],
		'timezone'				=> $timezone,
		'dst'					=> $dst,
		'language'				=> $language,
		'style'					=> $forum_config['o_default_style'],
		'registered'			=> time(),
		'registration_ip'		=> get_remote_address(),
		'activate_key'			=> $verify ? '\''.random_key(8, true).'\'' : 'NULL',
		'require_verification'	=> $verify,
		'notify_admins'			=> $forum_config['o_regs_report'] == '1'
	);
	add_user($user_info, $new_uid);

	if ($verify)
		api_json(['verify_email' => true], 202);

	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';
	clean_stats_cache();

	$query = array(
		'SELECT'	=> 'u.*, g.g_id, g.g_user_title',
		'FROM'		=> 'users AS u',
		'JOINS'		=> array(array('INNER JOIN' => 'groups AS g', 'ON' => 'g.g_id=u.group_id')),
		'WHERE'		=> 'u.id='.(int)$new_uid
	);
	$result = $forum_db->query_build($query) or error(__FILE__, __LINE__);
	api_json(['user' => api_user_public($forum_db->fetch_assoc($result))], 201);
});
