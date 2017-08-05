<?php
//-----------------------------------------------------------------------------
// $RCSFile: vbotservice.php $ $Revision: 1.19 $
// $Date: 2010/01/05 06:14:58 $
//-----------------------------------------------------------------------------

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'vbotservice.php');
define('CSRF_PROTECTION', false); 
define('DIE_QUIETLY', 1);

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/class_dm.php');
require_once(DIR . '/includes/class_dm_threadpost.php');
require_once(DIR . '/includes/class_xml.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_forumlist.php');
require_once(DIR . '/includes/functions_vbot.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// make sure the service is on
if (!$vbulletin->options['vbb_serviceonoff'])
{
	print_error_xml('vbb_service_turned_off');
}

$postdata = file_get_contents("php://input"); 
$xmlobj = new XMLparser($postdata, '');
$xmlarray = $xmlobj->parse();

// make sure the xml request is valid
if ($xmlobj->error_code != 0)
{
	print_error_xml('invalid_request_xml');
}

// make sure the bot credentials are valid
if (empty($xmlarray['botcredentials']))
{
	print_error_xml('no_botcredentials_defined');	
}
else if (empty($xmlarray['botcredentials']['servicepw']))
{
	print_error_xml('no_servicepw_defined');	
}
else if ($xmlarray['botcredentials']['servicepw'] != $vbulletin->options['vbb_servicepw'])
{
	print_error_xml('invalid_servicepw');		
}

// make sure we have a command
if (empty($xmlarray['command']))
{
	print_error_xml('no_command_defined');
}

$botonlycommands = array('getpostnotifications');
if (in_array($xmlarray['command'],$botonlycommands))
{
	switch ($xmlarray['command'])
	{
		case 'getpostnotifications':
			$dodelete = $xmlarray['delete'] == 'true' ? true : false;
		
			header('Content-Type: text/xml;');
			print fetch_post_notifications($dodelete);
			exit;			
		break;
		
		default:
			print_error_xml('unknown_command');
		break;		
	}	
}
else
{
	if (empty($xmlarray['usercredentials']))
	{
		print_error_xml('no_usercredentials_defined');
	}
	else if (empty($xmlarray['usercredentials']['type']))
	{
		print_error_xml('no_usercredentials_type_defined');
	}
	else
	{
		switch($xmlarray['usercredentials']['type'])
		{
			case 'userid':
				$userid =  $xmlarray['usercredentials']['userid'];
			break;
			
			case 'service':
				$screenname =  $xmlarray['usercredentials']['screenname'];
				$service = $xmlarray['usercredentials']['service'];
				$userid = fetch_userid_by_service($service,$screenname);
			break;
			
			default:
				print_error_xml('invalid_usercredentials_type');
				$userid = null;
			break;
		}
	}
	
	if (empty($userid) || $userid <= 0)
	{
		print_error_xml('invalid_user');
		exit;
	}
	
	unset($vbulletin->userinfo);
	$vbulletin->userinfo = fetch_userinfo($userid);
	$permissions = cache_permissions($vbulletin->userinfo);
	
	if (!($vbulletin->userinfo['userid'] > 0))
	{
		print_error_xml('invalid_userid');
		exit;
	}
	
	switch ($xmlarray['command'])
	{
		case 'ft':
			$threadid = $xmlarray['threadid'];
			if (intval($threadid) > 0)
			{
				header('Content-Type: text/xml;');
				print fetch_threadxml($threadid);
				exit;				
			}
			else
			{
				print_error_xml('invalid_threadid_ft');			
			}			
		break;
		
		case 'lf':
			$forumid = $xmlarray['forumid'];
			if ($forumid > 0 || $forumid == -1)
			{
				header('Content-Type: text/xml;');
				print fetch_subsxml($forumid);
				exit;
			}
			else
			{
				print_error_xml('invalid_forumid_lf');			
			}
		break;
		
		case 'lpf': 
			$forumid = $xmlarray['forumid'];
			if ($forumid > 0)
			{
				header('Content-Type: text/xml;');
				print fetch_parent_subsxml($forumid);
				exit;
			}
			else if ($forumid == -1)
			{
				print_error_xml('no_parent_forum_lpf');		
			}
			else
			{
				print_error_xml('invalid_forumid_lpf');			
			}		
		break;
		
		case 'lt':
			$forumid = $xmlarray['forumid'];
			if ($forumid > 0 || $forumid == -1)
			{
				$pagenumber = ($xmlarray['pagenumber'] == null) ? PAGE_NUMBER_DEFAULT : $xmlarray['pagenumber'];
				$perpage = ($xmlarray['perpage'] == null) ? PERPAGE_DEFAULT : $xmlarray['perpage'];
				
				header('Content-Type: text/xml;');
				print fetch_threadsxml($forumid,$pagenumber,$perpage);
				exit;
			}
			else
			{
				print_error_xml('invalid_forumid_lt');			
			}
		break;	
			
		case 'lp':
			$threadid = $xmlarray['threadid'];
			if ($threadid > 0)
			{
				$pagenumber = ($xmlarray['pagenumber'] == null) ? PAGE_NUMBER_DEFAULT : $xmlarray['pagenumber'];
				$perpage = ($xmlarray['perpage'] == null) ? PERPAGE_DEFAULT : $xmlarray['perpage'];
				
				header('Content-Type: text/xml;');
				print fetch_postsxml($threadid,$pagenumber,$perpage);
				exit;
			}
			else
			{
				print_error_xml('invalid_threadid_lp');			
			}
		break;			
		
		case 'gp':
			$postid = $xmlarray['postid'];
			if ($postid > 0)
			{
				header('Content-Type: text/xml;');
				print fetch_postxml($postid);
				exit;
			}
			else
			{
				print_error_xml('invalid_postid_gp');			
			}
		break;
		
		case 'gpi':
			$threadid = $xmlarray['threadid'];
			$index = $xmlarray['index'];
			
			if ($threadid > 0)
			{
				header('Content-Type: text/xml;');
				print fetch_postxmlbyindex($threadid,$index);
				exit;			
			}
			else
			{
				print_error_xml('invalid_threadid_gpi');
			}
		break;
		
		case 'imon':
				header('Content-Type: text/xml;');
				print set_imnotification(true);
				exit;				
		break;
		
		case 'imoff':
				header('Content-Type: text/xml;');
				print set_imnotification(false);
				exit;				
		break;
		
		case 'mfr':
			$forumid = $xmlarray['forumid'];
			if ($forumid > 0)
			{
				$foruminfo = fetch_foruminfo($forumid);
				mark_forum_read($foruminfo,$vbulletin->userinfo['userid'],TIMENOW);
				
				$xml = new XMLexporter($vbulletin);
				$xml->add_group('response');
				
				$xml->add_group('forum');
				foreach($foruminfo as $key => $val)
				{
					if (!is_array($val))
					{
						$xml->add_tag($key,$val);
					}
				}
				$xml->close_group();			
				
				$xml->add_tag('success','true');
				$xml->close_group();
				
				header('Content-Type: text/xml;');
				print $xml->output();				
				exit;
			}
			else
			{
				print_error_xml('invalid_threadid_mtr');
			}
		break;	
		
		case 'mtr':
			$threadid = $xmlarray['threadid'];
			if ($threadid > 0)
			{
				$threadinfo = fetch_threadinfo($threadid);
				$foruminfo = fetch_foruminfo($threadinfo['forumid']);
				
				mark_thread_read($threadinfo,$foruminfo,$vbulletin->userinfo['userid'],TIMENOW);
				
				$xml = new XMLexporter($vbulletin);
				$xml->add_group('response');
				
				$xml->add_group('thread');
				foreach($threadinfo as $key => $val)
				{
					$xml->add_tag($key,$val);
				}
				$xml->close_group();			
				
				$xml->add_tag('success','true');
				$xml->close_group();
				
				header('Content-Type: text/xml;');
				print $xml->output();				
				exit;
			}
			else
			{
				print_error_xml('invalid_threadid_mtr');
			}
		break;
		
		
		case 'reply':
			$threadid = $xmlarray['threadid'];
			$pagetext = $xmlarray['pagetext'];
			
			if ($threadid == 0)
			{
				print_error_xml('invalid_threadsid_reply');
			}
			else if (strlen($pagetext) == 0)
			{
				print_error_xml('invalid_pagetext_reply');
			}
			
			header('Content-Type: text/xml;');
			print thread_reply($threadid,$pagetext);
			exit;
		break;
		
		case 'sub': 
			$threadid = $xmlarray['threadid'];
			
			if ($threadid > 0)
			{
				header('Content-Type: text/xml;');
				print subscribe_thread($threadid); 
				exit;	
			}
		break;
		
		case 'unsub':
			$threadid = $xmlarray['threadid'];
			
			header('Content-Type: text/xml;');
			print unsubscribe_thread($threadid);
			exit;	
		break;	
		
		case 'whoami':
			print fetch_userinfoxml();		
			exit;
		break;	
			
		default:
			print_error_xml('unknown_command');
		break;
	}
}

print_error_xml('null_process');

?>