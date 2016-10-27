<?php
/**
 * Copyright (C) 2014 StrongholdNation (http://www.strongholdnation.co.uk)
 * based on code by FluxBB copyright (C) 2008-2012 FluxBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Tell header.php to use the admin template
define('PUN_ADMIN_CONSOLE', 1);

define('PUN_ROOT', dirname(__FILE__).'/');
require PUN_ROOT.'include/common.php';
require PUN_ROOT.'include/parser.php';
require PUN_ROOT.'include/search_idx.php';
require PUN_ROOT.'include/common_admin.php';

if (!$pun_user['is_admmod'] && $pun_user['g_id'] != PUN_ADMIN)
	message($lang_common['No permission'], false, '403 Forbidden');

// Load the admin_reports.php language file
require PUN_ROOT.'lang/English/admin_posts.php';

if (isset($_POST['post_id']))
{
	confirm_referrer('admin_posts.php');
	$post_id = intval(key($_POST['post_id']));
	$action = isset($_POST['action']) && is_array($_POST['action']) ? intval($_POST['action'][$post_id]) : '1';

	$result = $db->query('SELECT p.posted, p.message, p.poster, p.poster_id, p.topic_id, p.poster_email, p.poster_ip, t.forum_id, t.subject, t.first_post_id, f.forum_name, u.num_posts, g.g_promote_next_group, g.g_promote_min_posts FROM '.$db->prefix.'posts AS p LEFT JOIN '.$db->prefix.'topics AS t ON p.topic_id = t.id LEFT JOIN '.$db->prefix.'forums AS f ON t.forum_id = f.id LEFT JOIN '.$db->prefix.'users AS u ON p.poster_id = u.id LEFT JOIN '.$db->prefix.'groups AS g ON u.group_id = g.g_id WHERE p.id = '.$post_id) or error('Unable to fetch topic list', __FILE__, __LINE__, $db->error());
	$post = $db->fetch_assoc($result);

	$is_topic_post = ($post_id == $post['first_post_id']) ? true : false;
	if ($action == '1')
	{
		$db->query('UPDATE '.$db->prefix.'posts SET approved=1 WHERE id='.$post_id) or error('Unable to update post', __FILE__, __LINE__, $db->error());
		if ($is_topic_post)
		{
			$db->query('UPDATE '.$db->prefix.'topics SET approved=1 WHERE id='.$post['topic_id']) or error('Unable to update post', __FILE__, __LINE__, $db->error());

			update_search_index('post', $post_id, $post['message'], $post['subject']);
			if ($pun_config['o_forum_subscriptions'] == '1')
			{
				// Get any subscribed users that should be notified (banned users are excluded)
				$result = $db->query('SELECT u.id, u.email, u.notify_with_post, u.language FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'forum_subscriptions AS s ON u.id=s.user_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id='.$post['forum_id'].' AND fp.group_id=u.group_id) LEFT JOIN '.$db->prefix.'bans AS b ON u.username=b.username WHERE b.username IS NULL AND (fp.read_forum IS NULL OR fp.read_forum=1) AND s.forum_id='.$post['forum_id'].' AND u.id!='.$post['poster_id']) or error('Unable to fetch subscription info', __FILE__, __LINE__, $db->error());		
				if ($db->num_rows($result))
				{
					require_once PUN_ROOT.'include/email.php';
		
					$notification_emails = array();
					if ($pun_config['o_censoring'] == '1')
						$cleaned_message = bbcode2email(censor_words($post['message']), -1);
					else
						$cleaned_message = bbcode2email($post['message'], -1);

					// Loop through subscribed users and send emails
					while ($cur_subscriber = $db->fetch_assoc($result))
					{
						// Is the subscription email for $cur_subscriber['language'] cached or not?
						if (!isset($notification_emails[$cur_subscriber['language']]))
						{
							if (file_exists(PUN_ROOT.'lang/'.$cur_subscriber['language'].'/mail_templates/new_topic.tpl'))
							{
								// Load the "new topic" template
								$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$cur_subscriber['language'].'/mail_templates/new_topic.tpl'));

								// Load the "new topic full" template (with post included)
								$mail_tpl_full = trim(file_get_contents(PUN_ROOT.'lang/'.$cur_subscriber['language'].'/mail_templates/new_topic_full.tpl'));

								// The first row contains the subject (it also starts with "Subject:")
								$first_crlf = strpos($mail_tpl, "\n");
								$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
								$mail_message = trim(substr($mail_tpl, $first_crlf));

								$first_crlf = strpos($mail_tpl_full, "\n");
								$mail_subject_full = trim(substr($mail_tpl_full, 8, $first_crlf-8));
								$mail_message_full = trim(substr($mail_tpl_full, $first_crlf));

								$mail_subject = str_replace('<forum_name>', $post['forum_name'], $mail_subject);
								$mail_message = str_replace('<topic_subject>', $pun_config['o_censoring'] == '1' ? censor_words($post['subject']) : $post['subject'], $mail_message);
								$mail_message = str_replace('<forum_name>', $post['forum_name'], $mail_message);
								$mail_message = str_replace('<poster>', $post['poster'], $mail_message);
								$mail_message = str_replace('<topic_url>', get_base_url().'/viewtopic.php?id='.$post['topic_id'], $mail_message);
								$mail_message = str_replace('<unsubscribe_url>', get_base_url().'/misc.php?action=unsubscribe&fid='.$post['forum_id'], $mail_message);
								$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

								$mail_subject_full = str_replace('<forum_name>', $post['forum_name'], $mail_subject_full);
								$mail_message_full = str_replace('<topic_subject>', $pun_config['o_censoring'] == '1' ? censor_words($post['subject']) : $post['subject'], $mail_message_full);
								$mail_message_full = str_replace('<forum_name>', $post['forum_name'], $mail_message_full);
								$mail_message_full = str_replace('<poster>', $post['poster'], $mail_message_full);
								$mail_message_full = str_replace('<message>', $cleaned_message, $mail_message_full);
								$mail_message_full = str_replace('<topic_url>', get_base_url().'/viewtopic.php?id='.$post['topic_id'], $mail_message_full);
								$mail_message_full = str_replace('<unsubscribe_url>', get_base_url().'/misc.php?action=unsubscribe&fid='.$post['forum_id'], $mail_message_full);
								$mail_message_full = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message_full);

								$notification_emails[$cur_subscriber['language']][0] = $mail_subject;
								$notification_emails[$cur_subscriber['language']][1] = $mail_message;
								$notification_emails[$cur_subscriber['language']][2] = $mail_subject_full;
								$notification_emails[$cur_subscriber['language']][3] = $mail_message_full;

								$mail_subject = $mail_message = $mail_subject_full = $mail_message_full = null;
							}
						}

						// We have to double check here because the templates could be missing
						if (isset($notification_emails[$cur_subscriber['language']]))
						{
							if ($cur_subscriber['notify_with_post'] == '0')
								pun_mail($cur_subscriber['email'], $notification_emails[$cur_subscriber['language']][0], $notification_emails[$cur_subscriber['language']][1]);
							else
								pun_mail($cur_subscriber['email'], $notification_emails[$cur_subscriber['language']][2], $notification_emails[$cur_subscriber['language']][3]);
						}
					}
		
					unset($cleaned_message);
				}
			}
		}
		else
		{
				// Just to be safe in case there has been another reply made since...		
			$result = $db->query('SELECT id, poster, posted FROM '.$db->prefix.'posts WHERE topic_id='.$post['topic_id'].' AND approved=1 ORDER BY id DESC LIMIT 1') or error('Unable to fetch last post info', __FILE__, __LINE__, $db->error());
			list($last_id, $poster, $posted) = $db->fetch_row($result);
	
			$db->query('UPDATE '.$db->prefix.'topics SET num_replies = num_replies +1, last_post='.$posted.', last_post_id='.$last_id.', last_poster = \''.$db->escape($poster).'\' WHERE id='.$post['topic_id'])  or error('Unable to update topic', __FILE__, __LINE__, $db->error());
			update_search_index('post', $post_id, $post['message'], $post['subject']);
				
			if ($pun_config['o_forum_subscriptions'] == '1')
			{
				// Get any subscribed users that should be notified (banned users are excluded)
				$result = $db->query('SELECT u.id, u.email, u.notify_with_post, u.language FROM '.$db->prefix.'users AS u INNER JOIN '.$db->prefix.'topic_subscriptions AS s ON u.id=s.user_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id='.$post['forum_id'].' AND fp.group_id=u.group_id) LEFT JOIN '.$db->prefix.'online AS o ON u.id=o.user_id LEFT JOIN '.$db->prefix.'bans AS b ON u.username=b.username WHERE b.username IS NULL AND COALESCE(o.logged, u.last_visit)>'.$posted.' AND (fp.read_forum IS NULL OR fp.read_forum=1) AND s.topic_id='.$post['topic_id'].' AND u.id!='.$post['poster_id']) or error('Unable to fetch subscription info', __FILE__, __LINE__, $db->error());
				if ($db->num_rows($result))
				{
					require_once PUN_ROOT.'include/email.php';
					$notification_emails = array();
					if ($pun_config['o_censoring'] == '1')
						$cleaned_message = bbcode2email(censor_words($post['message']), -1);
					else
						$cleaned_message = bbcode2email($post['message'], -1);
		
					// Loop through subscribed users and send emails
					while ($cur_subscriber = $db->fetch_assoc($result))
					{
						// Is the subscription email for $cur_subscriber['language'] cached or not?
						if (!isset($notification_emails[$cur_subscriber['language']]))
						{
							if (file_exists(PUN_ROOT.'lang/'.$cur_subscriber['language'].'/mail_templates/new_reply.tpl'))
							{
								// Load the "new reply" template
								$mail_tpl = trim(file_get_contents(PUN_ROOT.'lang/'.$cur_subscriber['language'].'/mail_templates/new_reply.tpl'));
	
								// Load the "new reply full" template (with post included)
								$mail_tpl_full = trim(file_get_contents(PUN_ROOT.'lang/'.$cur_subscriber['language'].'/mail_templates/new_reply_full.tpl'));
		
								// The first row contains the subject (it also starts with "Subject:")
								$first_crlf = strpos($mail_tpl, "\n");
								$mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
								$mail_message = trim(substr($mail_tpl, $first_crlf));
		
								$first_crlf = strpos($mail_tpl_full, "\n");
								$mail_subject_full = trim(substr($mail_tpl_full, 8, $first_crlf-8));
								$mail_message_full = trim(substr($mail_tpl_full, $first_crlf));
	
								$mail_subject = str_replace('<topic_subject>', $post['subject'], $mail_subject);
								$mail_message = str_replace('<topic_subject>', $post['subject'], $mail_message);
								$mail_message = str_replace('<replier>', $post['poster'], $mail_message);
								$mail_message = str_replace('<post_url>', get_base_url().'/viewtopic.php?pid='.$post_id.'#p'.$post_id, $mail_message);
								$mail_message = str_replace('<unsubscribe_url>', get_base_url().'/misc.php?action=unsubscribe&tid='.$post['topic_id'], $mail_message);
								$mail_message = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message);

								$mail_subject_full = str_replace('<topic_subject>', $post['subject'], $mail_subject_full);
								$mail_message_full = str_replace('<topic_subject>', $post['subject'], $mail_message_full);
								$mail_message_full = str_replace('<replier>', $post['poster'], $mail_message_full);
								$mail_message_full = str_replace('<message>', $cleaned_message, $mail_message_full);
								$mail_message_full = str_replace('<post_url>', get_base_url().'/viewtopic.php?pid='.$post_id.'#p'.$post_id, $mail_message_full);
								$mail_message_full = str_replace('<unsubscribe_url>', get_base_url().'/misc.php?action=unsubscribe&tid='.$post['topic_id'], $mail_message_full);
								$mail_message_full = str_replace('<board_mailer>', $pun_config['o_board_title'], $mail_message_full);
		
								$notification_emails[$cur_subscriber['language']][0] = $mail_subject;
								$notification_emails[$cur_subscriber['language']][1] = $mail_message;
								$notification_emails[$cur_subscriber['language']][2] = $mail_subject_full;
								$notification_emails[$cur_subscriber['language']][3] = $mail_message_full;
		
								$mail_subject = $mail_message = $mail_subject_full = $mail_message_full = null;
							}
						}
		
						// We have to double check here because the templates could be missing
						if (isset($notification_emails[$cur_subscriber['language']]))
						{
							if ($cur_subscriber['notify_with_post'] == '0')
								pun_mail($cur_subscriber['email'], $notification_emails[$cur_subscriber['language']][0], $notification_emails[$cur_subscriber['language']][1]);
							else
								pun_mail($cur_subscriber['email'], $notification_emails[$cur_subscriber['language']][2], $notification_emails[$cur_subscriber['language']][3]);
						}
					}
		
					unset($cleaned_message);
				}
			}
		}

		$db->query('UPDATE '.$db->prefix.'users SET num_posts = num_posts + 1 WHERE id='.$post['poster_id']) or error('Unable to update post count', __FILE__, __LINE__, $db->error());
	
		// Promote this user to a new group if enabled
		if ($post['g_promote_next_group'] != 0 && $post['num_posts'] >= $post['g_promote_min_posts'])
			$db->query('UPDATE '.$db->prefix.'users SET group_id='.$post['g_promote_next_group'].' WHERE id='.$post['poster_id']) or error('Unable to update user group', __FILE__, __LINE__, $db->error());

		update_forum($post['forum_id']);
		redirect('admin_posts.php', $lang_admin_posts['Post approved redirect']);
	}
	else
	{
		if ($is_topic_post)
		{
			delete_topic($post['topic_id']);
			update_forum($post['forum_id']);		
		}
		else
		{
			delete_post($post_id, $post['topic_id']);
			update_forum($post['forum_id']);
		}

		redirect('admin_posts.php', $lang_admin_posts['Post deleted redirect']);
	}
}

$page_title = array(pun_htmlspecialchars($pun_config['o_board_title']), $lang_admin_common['Admin'], $lang_admin_common['Posts']);
define('PUN_ACTIVE_PAGE', 'admin');
require PUN_ROOT.'header.php';

generate_admin_menu('posts');
?>
	<div class="blockform">
		<h2><span><?php echo $lang_admin_posts['New posts head'] ?></span></h2>
		<div class="box">
			<form method="post" action="admin_posts.php">
<?php
$result = $db->query('SELECT t.id AS topic_id, t.forum_id, p.poster, p.poster_id, p.posted, p.message, p.id AS pid, p.hide_smilies, t.subject, f.forum_name FROM '.$db->prefix.'posts AS p LEFT JOIN '.$db->prefix.'topics AS t ON p.topic_id = t.id LEFT JOIN '.$db->prefix.'forums AS f ON t.forum_id = f.id WHERE p.approved=0 ORDER BY p.posted DESC') or error('Unable to fetch unapproved posts', __FILE__, __LINE__, $db->error());
if ($db->num_rows($result))
{
	while($cur_post = $db->fetch_assoc($result))
	{
		$reporter = ($cur_post['poster'] != '') ? '<a href="profile.php?id='.$cur_post['poster_id'].'">'.pun_htmlspecialchars($cur_post['poster']).'</a>' : $lang_admin_posts['Deleted user'];
		$forum = ($cur_post['forum_name'] != '') ? '<span><a href="viewforum.php?id='.$cur_post['forum_id'].'">'.pun_htmlspecialchars($cur_post['forum_name']).'</a></span>' : '<span>'.$lang_admin_posts['Deleted'].'</span>';
		$topic = ($cur_post['subject'] != '') ? '<span>»&#160;<a href="viewtopic.php?id='.$cur_post['topic_id'].'">'.pun_htmlspecialchars($cur_post['subject']).'</a></span>' : '<span>»&#160;'.$lang_admin_posts['Deleted'].'</span>';

		$post_id = ($cur_post['pid'] != '') ? '<span>»&#160;<a href="viewtopic.php?pid='.$cur_post['pid'].'#p'.$cur_post['pid'].'">'.sprintf($lang_admin_posts['Post ID'], $cur_post['pid']).'</a></span>' : '<span>»&#160;'.$lang_admin_posts['Deleted'].'</span>';
		$post_location = array($forum, $topic, $post_id);
?>
				<div class="inform">
					<fieldset>
						<legend><?php printf($lang_admin_posts['Post subhead'], format_time($cur_post['posted'])) ?></legend>
						<div class="infldset">
							<table class="aligntop" cellspacing="0">
								<tr>
									<th scope="row"><?php printf($lang_admin_posts['Posted by'], $reporter) ?></th>
									<td class="location"><?php echo implode(' ', $post_location) ?></td>
								</tr>
								<tr>
									<th scope="row"><?php echo $lang_admin_posts['Message'] ?><div><select name="action[<?php echo $cur_post['pid']; ?>]"><option value="1"><?php echo $lang_admin_posts['Approve']; ?></option><option value="2"><?php echo $lang_admin_posts['Delete']; ?></option></select> </form><input type="submit" name="post_id[<?php echo $cur_post['pid'] ?>]" value="<?php echo $lang_common['Submit'] ?>" /></div></th>
									<td><?php echo parse_message($cur_post['message'], $cur_post['hide_smilies']); if ($attach_output != '') echo "\t\t\t\t\t".'<div class="postsignature"><hr />'.$attach_output.'</div>'."\n"; ?></td>
								</tr>
							</table>
						</div>
					</fieldset>
				</div>
<?php
	}
}
else
{
?>
				<div class="inform">
					<fieldset>
						<legend><?php echo $lang_admin_common['None'] ?></legend>
						<div class="infldset">
							<p><?php echo $lang_admin_posts['No new posts'] ?></p>
						</div>
					</fieldset>
				</div>
<?php
}
?>
			</form>
		</div>
	</div>
	<div class="clearer"></div>
</div>
<?php
require PUN_ROOT.'footer.php';