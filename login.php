<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if (isset($_GET['action']))
	define('PUN_QUIET_VISIT', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';

// OpenStreetMap specific addition
function URLopen($url)
{
        // Fake the browser type
        ini_set('user_agent','MSIE 4\.0b2;');
		$result = "";
		try {
			$dh = @fopen("$url",'r');
			if ($dh) {
				$result = fread($dh,8192);
			}
		} catch (Exception $e) {
			$result = $e->getMessage();
		}
		//echo $result;
        return $result;
}

// Load the login.php language file
require PUN_ROOT.'lang/'.$pun_user['language'].'/login.php';

$action = isset($_GET['action']) ? $_GET['action'] : null;
$errors = array();

/*
if (isset($_POST['form_sent']) && $action == 'in')
{
	flux_hook('login_before_validation');

	$form_username = pun_trim($_POST['req_username']);
	$form_password = pun_trim($_POST['req_password']);
	$save_pass = isset($_POST['save_pass']);

	$username_sql = ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb') ? 'username=\''.$db->escape($form_username).'\'' : 'LOWER(username)=LOWER(\''.$db->escape($form_username).'\')';

	$result = $db->query('SELECT * FROM '.$db->prefix.'users WHERE '.$username_sql) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
	$cur_user = $db->fetch_assoc($result);

	$authorized = false;

	if (!empty($cur_user['password']))
	{
		$form_password_hash = pun_hash($form_password); // Will result in a SHA-1 hash

		// If there is a salt in the database we have upgraded from 1.3-legacy though haven't yet logged in
		if (!empty($cur_user['salt']))
		{
			$is_salt_authorized = pun_hash_equals(sha1($cur_user['salt'].sha1($form_password)), $cur_user['password']);
			if ($is_salt_authorized) // 1.3 used sha1(salt.sha1(pass))
 			{
				$authorized = true;

				$db->query('UPDATE '.$db->prefix.'users SET password=\''.$form_password_hash.'\', salt=NULL WHERE id='.$cur_user['id']) or error('Unable to update user password', __FILE__, __LINE__, $db->error());
			}
		}
		// If the length isn't 40 then the password isn't using sha1, so it must be md5 from 1.2
		else if (strlen($cur_user['password']) != 40)
		{
			$is_md5_authorized = pun_hash_equals(md5($form_password), $cur_user['password']);
			if ($is_md5_authorized)
			{
				$authorized = true;

				$db->query('UPDATE '.$db->prefix.'users SET password=\''.$form_password_hash.'\' WHERE id='.$cur_user['id']) or error('Unable to update user password', __FILE__, __LINE__, $db->error());
			}
		}
		// Otherwise we should have a normal sha1 password
		else
			$authorized = pun_hash_equals($cur_user['password'], $form_password_hash);
	}

	if (!$authorized)
		$errors[] = $lang_login['Wrong user/pass'];

	flux_hook('login_after_validation');

	// Did everything go according to plan?
	if (empty($errors))
	{
		// Update the status if this is the first time the user logged in
		if ($cur_user['group_id'] == PUN_UNVERIFIED)
		{
			$db->query('UPDATE '.$db->prefix.'users SET group_id='.$pun_config['o_default_user_group'].' WHERE id='.$cur_user['id']) or error('Unable to update user status', __FILE__, __LINE__, $db->error());

			// Regenerate the users info cache
			if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
				require PUN_ROOT.'include/cache.php';

			generate_users_info_cache();
		}

		// Remove this user's guest entry from the online list
		$db->query('DELETE FROM '.$db->prefix.'online WHERE ident=\''.$db->escape(get_remote_address()).'\'') or error('Unable to delete from online list', __FILE__, __LINE__, $db->error());

		$expire = ($save_pass == '1') ? time() + 1209600 : time() + $pun_config['o_timeout_visit'];
		pun_setcookie($cur_user['id'], $form_password_hash, $expire);

		// Reset tracked topics
		set_tracked_topics(null);

		// Try to determine if the data in redirect_url is valid (if not, we redirect to index.php after login)
		$redirect_url = validate_redirect($_POST['redirect_url'], 'index.php');

		redirect(pun_htmlspecialchars($redirect_url), $lang_login['Login redirect']);
	}
}*/

if (isset($_POST['form_sent']) && $action == 'in')
{
	$form_username = pun_trim($_POST['req_username']);
	$form_password = pun_trim($_POST['req_password']);
	$save_pass = isset($_POST['save_pass']);

	$authorized = false;
	$sha1_in_db = (strlen($cur_user['password']) == 40) ? true : false;
	$sha1_available = (function_exists('sha1') || function_exists('mhash')) ? true : false;
	
	$form_password_hash = pun_hash($form_password); // Will result in a SHA-1 hash
	
	$authURL=sprintf("http://%s:%s@www.openstreetmap.org/api/0.6/user/details",urlencode($form_username),urlencode($form_password));
	$result = URLopen($authURL);

        //if user and passwort is found on osm.org
	if (strlen($result) > 0) {
            
		$sc_pos = strpos($result, "display_name=");
                
		if ($sc_pos > 0) {
                    
                        //find and extract display_name
			if ($sc_pos > 0) {
                            
				$sc_pos += 14;
				$sc_end = strpos($result, '"', $sc_pos);
				$username =html_entity_decode(substr($result, $sc_pos, $sc_end - $sc_pos), ENT_COMPAT, "UTF-8");
				
				if (strlen($username) == 0)
					message('A problem was encountered: The display name cannot be empty. Please setup your <a href="http://forum.openstreetmap.org/viewtopic.php?pid=1087">\'Display name\'</a> which you can find on the \'My Settings\' page on the OpenStreetMap main page.');
			}
			else
				message('A problem was encountered: Could not lookup the Display name in the authentication response. Please try again');

                        //find and extract osm_id
			$id_pos = strpos($result, "id=");
			if ($id_pos > 0) {
				$id_pos += 4;
				$id_end = strpos($result, '"', $id_pos);
				$osm_id = substr($result, $id_pos, $id_end - $id_pos);
			}
			else {
				message('A problem was encountered: Could not lookup the OSM ID in the authentication response. Please try again');
			}

			// Does the user exist already?
			$osmid_sql = ($db_type == 'mysql' || $db_type == 'mysqli' || $db_type == 'mysql_innodb' || $db_type == 'mysqli_innodb') ? 'osm_id=\''.$db->escape($osm_id).'\'' : 'osm_id=\''.$db->escape($osm_id).'\'';
			
			$result = $db->query('SELECT * FROM '.$db->prefix.'users WHERE '.$osmid_sql) or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());
			$cur_user = $db->fetch_assoc($result);

                        //if no account in osmfoum DB found
			if (!$cur_user['id']) {
                            
				//Create the user
				$intial_group_id = ($pun_config['o_regs_verify'] == '0') ? $pun_config['o_default_user_group'] : PUN_UNVERIFIED;
				$email1 = "";
				$email_setting = 1;
				$timezone = 0;
				$language = $pun_config['o_default_lang'];
				$now = time();

				$sql = 'INSERT INTO '.$db->prefix.'users (username, group_id, password, email, email_setting, timezone, language, style, registered, registration_ip, last_visit, osm_id) VALUES(\''.$db->escape($username).'\', '.$intial_group_id.', \''.$form_password_hash.'\', \''.$email1.'\', '.$email_setting.', '.$timezone.' , \''.$db->escape($language).'\', \''.$pun_config['o_default_style'].'\', '.$now.', \''.get_remote_address().'\', '.$now.', '.$osm_id.')';

				$db->query($sql) or error('Unable to create user', __FILE__, __LINE__, $db->error());
				$cur_user['id'] = $db->insert_id();
			}
                        
                        //account found in osmforum DB
			else
			{
				// Login successful. Update the user.
				if ($sha1_available)	 {
                                        
                                    // There's an MD5 hash in the database, but SHA1 hashing is available, so we update the DB
                                    $db->query('UPDATE '.$db->prefix.'users SET password=\''.$form_password_hash.'\' WHERE id='.$cur_user['id']) or error('Unable to update user password', __FILE__, __LINE__, $db->error());                                
                                }
			}
                        
                        //user changed his name on osm.org
                        if ($username != $cur_user['username']) {
                            
                            //update the username in osmforum DB
                            $sql = 'UPDATE '.$db->prefix.'users SET username = \''.$db->escape($username).'\' WHERE osm_id='.(int) $osm_id.''; 
                            $db->query($sql) or error('Unable to update username, please contact the Administrators and tell us your old and new OSM Name', __FILE__, __LINE__, $db->error());
                        }
			
			// Update email address from login email address
			if(filter_var($form_username, FILTER_VALIDATE_EMAIL) && $cur_user['id'] > 0) {
                            
				// valid address
				$db->query('UPDATE '.$db->prefix.'users SET email=\''.$db->escape($form_username).'\' WHERE id='.$cur_user['id']);
			}

			$authorized = true;
		}
		else
		{// remote auth failed.
			if ($header['status'] == 'HTTP/1.0 500 Internal Server Error') {
				message('A problem was encountered: The OpenStreetMap authentication API is currently not working, so unfortunately you cannot login. Please try again or come back later.');
			}
			else {
				//message('A problem was encountered: The OpenStreetMap authentication API is currently not working, so unfortunately you cannot login. Please try again or come back later.');
				print("<p>Authentication failed. The OpenStreetMap API returns: ".$header['body']."</p>");
			}
			$authorized = false;
		
		}
	}
	else
	{
		if ($header['status'] == 'HTTP/1.0 500 Internal Server Error') {
			message('A problem was encountered: The OpenStreetMap authentication API is currently not working, so unfortunately you cannot login. Please try again or come back later.');
		}
		else {
			//message('A problem was encountered: The OpenStreetMap authentication API is currently not working, so unfortunately you cannot login. Please try again or come back later.');
			message("Authentication failed. Please check if login works on <a href='http://www.openstreetmap.org'>www.openstreetmap.org</a>. If you can't login there, then there's no point in trying here....");
		}
		$authorized = false;
	}

	if (!$authorized) {
		message(  "test3");
		message($lang_login['Wrong user/pass'].' <a href="login.php?action=forget">'.$lang_login['Forgotten pass'].'</a>');
	}
	// Update the status if this is the first time the user logged in
	if ($cur_user['group_id'] == PUN_UNVERIFIED)
		$db->query('UPDATE '.$db->prefix.'users SET group_id='.$pun_config['o_default_user_group'].' WHERE id='.$cur_user['id']) or error('Unable to update user status', __FILE__, __LINE__, $db->error());

	// Remove this users guest entry from the online list
	$db->query('DELETE FROM '.$db->prefix.'online WHERE ident=\''.$db->escape(get_remote_address()).'\'') or error('Unable to delete from online list', __FILE__, __LINE__, $db->error());

	$expire = ($save_pass == '1') ? time() + 1209600 : time() + $pun_config['o_timeout_visit'];
	pun_setcookie($cur_user['id'], $form_password_hash, $expire);

	// Reset tracked topics
	set_tracked_topics(null);

	redirect(htmlspecialchars($_POST['redirect_url']), $lang_login['Login redirect']);
}


else if ($action == 'out')
{
	if ($pun_user['is_guest'] || !isset($_GET['id']) || $_GET['id'] != $pun_user['id'])
	{
		header('Location: index.php');
		exit;
	}

	check_csrf($_GET['csrf_token']);

	// Remove user from "users online" list
	$db->query('DELETE FROM '.$db->prefix.'online WHERE user_id='.$pun_user['id']) or error('Unable to delete from online list', __FILE__, __LINE__, $db->error());

	// Update last_visit (make sure there's something to update it with)
	if (isset($pun_user['logged']))
		$db->query('UPDATE '.$db->prefix.'users SET last_visit='.$pun_user['logged'].' WHERE id='.$pun_user['id']) or error('Unable to update user visit data', __FILE__, __LINE__, $db->error());

	pun_setcookie(1, pun_hash(uniqid(rand(), true)), time() + 31536000);

	redirect('index.php', $lang_login['Logout redirect']);
}


else if ($action == 'forget' || $action == 'forget_2')
{
	if (!$pun_user['is_guest'])
	{
		header('Location: index.php');
		exit;
	}

	if (isset($_POST['form_sent']))
	{
		flux_hook('forget_password_before_validation');

		require PUN_ROOT.'include/email.php';

		// Validate the email address
		$email = strtolower(pun_trim($_POST['req_email']));
		if (!is_valid_email($email))
			$errors[] = $lang_common['Invalid email'];

		flux_hook('forget_password_after_validation');

		// Did everything go according to plan?
		if (empty($errors))
		{
			$result = $db->query('SELECT id, username, last_email_sent FROM '.$db->prefix.'users WHERE email=\''.$db->escape($email).'\'') or error('Unable to fetch user info', __FILE__, __LINE__, $db->error());

			if ($db->num_rows($result))
			{
				// Load the "activate password" template
				$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$pun_user['language'].'/mail_templates/activate_password.tpl'));

				// The first row contains the subject
				$first_crlf = strpos($mail_tpl, "\n");
				$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
				$mail_message = trim(substr($mail_tpl, $first_crlf));

				// Do the generic replacements first (they apply to all emails sent out here)
				$mail_message = str_replace('<base_url>', get_base_url().'/', $mail_message);
				$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

				// Loop through users we found
				while ($cur_hit = $db->fetch_assoc($result))
				{
					if ($cur_hit['last_email_sent'] != '' && (time() - $cur_hit['last_email_sent']) < 3600 && (time() - $cur_hit['last_email_sent']) >= 0)
						message(sprintf($lang_login['Email flood'], intval((3600 - (time() - $cur_hit['last_email_sent'])) / 60)), true);

					// Generate a new password and a new password activation code
					$new_password = random_pass(12);
					$new_password_key = random_pass(8);

					$db->query('UPDATE '.$db->prefix.'users SET activate_string=\''.pun_hash($new_password).'\', activate_key=\''.$new_password_key.'\', last_email_sent = '.time().' WHERE id='.$cur_hit['id']) or error('Unable to update activation data', __FILE__, __LINE__, $db->error());

					// Do the user specific replacements to the template
					$cur_mail_message = str_replace('<username>', $cur_hit['username'], $mail_message);
					$cur_mail_message = str_replace('<activation_url>', get_base_url().'/profile.php?id='.$cur_hit['id'].'&action=change_pass&key='.$new_password_key, $cur_mail_message);
					$cur_mail_message = str_replace('<new_password>', $new_password, $cur_mail_message);

					pun_mail($email, $mail_subject, $cur_mail_message);
				}

				message($lang_login['Forget mail'].' <a href="mailto:'.pun_htmlspecialchars($pun_config['o_admin_email']).'">'.pun_htmlspecialchars($pun_config['o_admin_email']).'</a>.', true);
			}
			else
				$errors[] = $lang_login['No email match'].' '.pun_htmlspecialchars($email).'.';
			}
		}

	$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_login['Request pass']);
	$required_fields = array('req_email' => $lang_common['Email']);
	$focus_element = array('request_pass', 'req_email');

	flux_hook('forget_password_before_header');

	define ('PUN_ACTIVE_PAGE', 'login');
	require PUN_ROOT.'header.php';

// If there are errors, we display them
if (!empty($errors))
{

?>
<div id="posterror" class="block">
	<h2><span><?php echo $lang_login['New password errors'] ?></span></h2>
	<div class="box">
		<div class="inbox error-info">
			<p><?php echo $lang_login['New passworderrors info'] ?></p>
			<ul class="error-list">
<?php

	foreach ($errors as $cur_error)
		echo "\t\t\t\t".'<li><strong>'.$cur_error.'</strong></li>'."\n";
?>
			</ul>
		</div>
	</div>
</div>

<?php

}
?>
<div class="blockform">
	<h2><span><?php echo $lang_login['Request pass'] ?></span></h2>
	<div class="box">
		<form id="request_pass" method="post" action="login.php?action=forget_2" onsubmit="this.request_pass.disabled=true;if(process_form(this)){return true;}else{this.request_pass.disabled=false;return false;}">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_login['Request pass legend'] ?></legend>
					<div class="infldset">
						<input type="hidden" name="form_sent" value="1" />
						<label class="required"><strong><?php echo $lang_common['Email'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input id="req_email" type="text" name="req_email" value="<?php if (isset($_POST['req_email'])) echo pun_htmlspecialchars($_POST['req_email']); ?>" size="50" maxlength="80" /><br /></label>
						<p><?php echo $lang_login['Request pass info'] ?></p>
					</div>
				</fieldset>
			</div>
<?php flux_hook('forget_password_before_submit') ?>
			<p class="buttons"><input type="submit" name="request_pass" value="<?php echo $lang_common['Submit'] ?>" /><?php if (empty($errors)): ?> <a href="javascript:history.go(-1)"><?php echo $lang_common['Go back'] ?></a><?php endif; ?></p>
		</form>
	</div>
</div>
<?php

	require PUN_ROOT.'footer.php';
}


if (!$pun_user['is_guest'])
{
	header('Location: index.php');
	exit;
}

// Try to determine if the data in HTTP_REFERER is valid (if not, we redirect to index.php after login)
if (!empty($_SERVER['HTTP_REFERER']))
	$redirect_url = validate_redirect($_SERVER['HTTP_REFERER'], null);

if (!isset($redirect_url))
	$redirect_url = get_base_url(true).'/index.php';
else if (preg_match('%viewtopic\.php\?pid=(\d+)$%', $redirect_url, $matches))
	$redirect_url .= '#p'.$matches[1];

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_common['Login']);
$required_fields = array('req_username' => $lang_common['Username'], 'req_password' => $lang_common['Password']);
$focus_element = array('login', 'req_username');

flux_hook('login_before_header');

define('PUN_ACTIVE_PAGE', 'login');
require PUN_ROOT.'header.php';

// If there are errors, we display them
if (!empty($errors))
{

?>
<div id="posterror" class="block">
	<h2><span><?php echo $lang_login['Login errors'] ?></span></h2>
	<div class="box">
		<div class="inbox error-info">
			<p><?php echo $lang_login['Login errors info'] ?></p>
			<ul class="error-list">
<?php

	foreach ($errors as $cur_error)
		echo "\t\t\t\t".'<li><strong>'.$cur_error.'</strong></li>'."\n";
?>
			</ul>
		</div>
	</div>
</div>

<?php

}
?>
<div class="blockform">
	<h2><span><?php echo $lang_common['Login'] ?></span></h2>
	<div class="box">
		<form id="login" method="post" action="login.php?action=in" onsubmit="return process_form(this)">
			<div class="inform">
				<fieldset>
					<legend><?php echo $lang_login['Login legend'] ?></legend>
					<div class="infldset">
						<input type="hidden" name="form_sent" value="1" />
						<input type="hidden" name="redirect_url" value="<?php echo pun_htmlspecialchars($redirect_url) ?>" />
						<label class="conl required"><strong><?php echo $lang_common['Username'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input type="text" name="req_username" value="<?php if (isset($_POST['req_username'])) echo pun_htmlspecialchars($_POST['req_username']); ?>" size="35" maxlength="50" tabindex="1" /><br /></label>
						<label class="conl required"><strong><?php echo $lang_common['Password'] ?> <span><?php echo $lang_common['Required'] ?></span></strong><br /><input type="password" name="req_password" size="35" tabindex="2" /><br /></label>

						<div class="rbox clearb">
							<label><input type="checkbox" name="save_pass" value="1"<?php if (isset($_POST['save_pass'])) echo ' checked="checked"'; ?> tabindex="3" /><?php echo $lang_login['Remember me'] ?><br /></label>
						</div>

                                                <!--
						<p class="clearb"><?php echo $lang_login['Login info'] ?></p>
						<p class="actions"><span><a href="register.php" tabindex="5"><?php echo $lang_login['Not registered'] ?></a></span> <span><a href="login.php?action=forget" tabindex="6"><?php echo $lang_login['Forgotten pass'] ?></a></span></p>
                                                -->
					</div>
				</fieldset>
			</div>
<?php flux_hook('login_before_submit') ?>
			<p class="buttons"><input type="submit" name="login" value="<?php echo $lang_common['Login'] ?>" tabindex="4" /></p>
		</form>
	</div>
</div>
<?php

require PUN_ROOT.'footer.php';
