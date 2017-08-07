<?php

// hack for vbulletin 4.0 and CSRF Protection
$_POST["vb4"] = "";

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'soapservice.php');
define('CSRF_PROTECTION', false); 
define('DIE_QUIETLY', 1);

// ########################## REQUIRE BACK-END ############################
require_once("nusoap/nusoap.php");

require_once('./global.php');
require_once(DIR . '/includes/class_dm.php');
require_once(DIR . '/includes/class_dm_threadpost.php');
require_once(DIR . '/includes/class_xml.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/functions_forumlist.php');
require_once(DIR . '/includes/functions_newpost.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

function correct_forum_counters($threadid, $forumid) 
{
    // select lastpostid from thread where threadid =  $threadid
    // select dateline from post where postid = $postid
    // update thread set lastpost =  $time where threadid = $threadid

    global $db;
    $lastpostid = $db->query_first("SELECT lastpostid FROM " . TABLE_PREFIX . "thread WHERE threadid = '".$threadid."'");
    $dateline = $db->query_first("SELECT dateline FROM " . TABLE_PREFIX . "post WHERE postid = '".$lastpostid['lastpostid']."'");

    // Update thread table and threadread table to reflect new post
    $db->query_write("UPDATE " . TABLE_PREFIX . "thread SET lastpost = '".$dateline['dateline']."' WHERE threadid = '".$threadid."'");
    $db->query_write("UPDATE " . TABLE_PREFIX . "threadread SET readtime = '".($dateline['dateline']-1)."' WHERE threadid = '".$threadid."' AND readtime >= '".($dateline['dateline']-1)."'");

    // Update forum table and forumread to reflect new post
    $db->query_write("UPDATE " . TABLE_PREFIX . "forum SET lastpost = '".$dateline['dateline']."' WHERE forumid = '".$forumid."'");
    $db->query_write("UPDATE " . TABLE_PREFIX . "forumread SET readtime = '".($dateline['dateline']-1)."' WHERE forumid = '".$forumid."' AND readtime >= '".($dateline['dateline']-1)."'");
} 

function fetch_userid_by_service($service,$username)
{
    global $db,$vbulletin;
    
    $userinfo = $db->query_first(sprintf("SELECT * 
                                            FROM " . TABLE_PREFIX . "user 
                                            WHERE 
                                                (instantimservice = '%s')
                                                AND 
                                                (instantimscreenname = '%s');
                                            ",
                                            mysql_real_escape_string($service),
                                            mysql_real_escape_string($username)
                                            ));
    
    return $userinfo['userid'];
}

function ErrorResult($text)
{
    global $vbulletin,$structtypes;
    
    $result['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']);  
    $result['Code'] = 1;
    $result['Text'] = $text;

    return $result;    
}

function RegisterService($who)
{
    $result = array();
    $result['Code'] = 0;
    return $result;
	// global $db,$vbulletin,$server;
	// $result = array();
	
	// if (!$vbulletin->options['vbb_serviceonoff'])
	// {
	// 	$result['Code'] = 1;
	// 	$result['Text'] = 'vbb_service_turned_off';
	// }	
	// else if ($vbulletin->options['vbb_servicepw'] != $_SERVER['PHP_AUTH_PW'])
	// {
	// 	$result['Code'] = 1;
	// 	$result['Text'] = 'vbb_invalid_servicepw';
	// }
	// else
	// {
    //     $userid = fetch_userid_by_service($who['ServiceName'],$who['Username']);

    //     if (empty($userid) || $userid <= 0)
    //     {
    //         $result['Code'] = 1;
    //         $result['Text'] = 'invalid_user';
    //     }
    //     else
    //     {
    //         unset($vbulletin->userinfo);

    //         $vbulletin->userinfo =& fetch_userinfo($userid);
    //         $permissions = cache_permissions($vbulletin->userinfo);        
            
    //         $vbulletin->options['hourdiff'] = (date('Z', TIMENOW) / 3600 - $vbulletin->userinfo['timezoneoffset']) * 3600;
    //         fetch_options_overrides($vbulletin->userinfo);    
    //         fetch_time_data();
            
	// 	    // everything is ok
	// 	    $result['Code'] = 0;
    //     }
	// }
	
	// return $result;
}

function GetPostByIndex($who,$threadid,$index,$showbbcode = false)
{
    global $db,$vbulletin,$server,$structtypes,$lastpostarray;
    
    $result = RegisterService($who);
    if ($result['Code'] != 0)
    {
        $retval['Result'] = $result;
        return $retval;
    }    
    
    if ($index > 0)
    {
        $index -= 1;
        $postinfo = $db->query_first("SELECT * FROM ". TABLE_PREFIX ."post as post WHERE (threadid = $threadid) ORDER BY dateline ASC LIMIT $index,1");
        
        if (is_array($postinfo))
        {
            if (!$showbbcode)
            {
                $postinfo['pagetext'] = strip_bbcode($postinfo['pagetext'],true,false,false);  
            }
            $postinfo['datelinetext'] = vbdate($vbulletin->options['dateformat'],$postinfo['dateline'],true)." ".vbdate($vbulletin->options['timeformat'],$postinfo['dateline'],true);
            
            
            $retval['Post'] = ConsumeArray($postinfo,$structtypes['Post']);    
            
        }
    
        if ($postinfo['postid'] > 0)
        {
            $threadinfo = fetch_threadinfo($postinfo['threadid']);
            $foruminfo = fetch_foruminfo($threadinfo['forumid'],false);
            mark_thread_read($threadinfo, $foruminfo, $vbulletin->userinfo['userid'], $postinfo['dateline']);
        }
    }    
    
    $result['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']); 
    $retval['Result'] = $result;
   
    return $retval;    
    
}

function GetPostNotifications($dodelete)
{
    global $db,$vbulletin,$server,$structtypes,$lastpostarray;
    
    $result = array();
    if (!$vbulletin->options['vbb_serviceonoff'])
    {
        $result['Code'] = 1;
        $result['Text'] = 'vbb_service_turned_off';
        return array('Result'=>$result);
    }    
    else if ($vbulletin->options['vbb_servicepw'] != $_SERVER['PHP_AUTH_PW'])
    {
        $result['Code'] = 1;
        $result['Text'] = 'vbb_invalid_servicepw';
        return array('Result'=>$result);
    }   
    
    $query = "
        SELECT 
            vbotnotification.*,                         
            post.*,                                   
            thread.*,                                 
            forum.*,                                  
            post.dateline as postdateline,            
            thread.title as threadtitle,              
            user.username AS newpostusername,         
            user.instantimnotification AS instantimnotification,  
            user.instantimscreenname AS instantimscreenname,  
            user.instantimservice AS instantimservice 
        FROM ".TABLE_PREFIX."vbotnotification AS vbotnotification
        LEFT JOIN " . TABLE_PREFIX . "user AS user ON (vbotnotification.userid = user.userid)  
        LEFT JOIN " . TABLE_PREFIX . "post AS post ON (vbotnotification.datumid = post.postid)
        LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
        LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON (thread.forumid = forum.forumid)
        WHERE (notificationtype = 'newpost')
        ORDER By vbotnotification.dateline ASC  
    ";
    
    $notificationlist = array();
    
    $postnotifications = $db->query_read_slave($query);        
    while ($notification = $db->fetch_array($postnotifications))
    {    
        if ($notification['postid'] > 0 && $notification['threadid'] > 0)
        {
            $indexq = "
                SELECT COUNT(postid) as postindex
                FROM ".TABLE_PREFIX."post AS post
                WHERE (threadid = $notification[threadid] AND postid <= $notification[postid]);";
                
            $index = $db->query_first($indexq);             
            $notification['postindex'] = $index['postindex'];
        }
        
        $notification['pagetext'] = strip_bbcode($notification['pagetext'],true,false,false);
        $notification['datelinetext'] = vbdate($vbulletin->options['dateformat'],$notification['postdateline'],true)." ".vbdate($vbulletin->options['timeformat'],$notification['postdateline'],true); 
        
        $temp['IMNotificationInfo'] = ConsumeArray($notification,$structtypes['IMNotificationInfo']);          
        $temp['Thread'] = ConsumeArray($notification,$structtypes['Thread']);           
        $temp['Post'] = ConsumeArray($notification,$structtypes['Post']);           
        $temp['Forum'] = ConsumeArray($notification,$structtypes['Forum']);           
        
        // delete the notification if we tell it to or no corresponding post exists (happens if a mod deletes a post)
        if ($dodelete || $notification['postid'] == 0 || $notification['threadid'] == 0)
        {
            $db->query_write("DELETE FROM " . TABLE_PREFIX . "vbotnotification WHERE (vbotnotificationid = $notification[vbotnotificationid]);");
        }
        
        if ($notification['postid'] > 0)
        {
            array_push($notificationlist,$temp);
        }
        
        unset($temp);
    }    
                                  
    $result['Code'] = 0;
    $retval['Result'] = $result;
    $retval['PostNotificationList'] = $notificationlist;
    
    return $retval;
}

function GetThread($who,$threadid)
{
    global $db,$vbulletin,$server,$structtypes,$lastpostarray;
    
    $result = RegisterService($who);
    if ($result['Code'] != 0)
    {
        $retval['Result'] = $result;
        return $retval;
    }  
    
    $threadinfo = $thread = fetch_threadinfo($threadid);    
    $forum = fetch_foruminfo($thread['forumid']);
    $foruminfo =& $forum;    
    
    // check forum permissions
    $forumperms = fetch_permissions($thread['forumid']);
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
    {
        print_error_xml('no_permission_fetch_threadxml');
    }
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($thread['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
    {
        print_error_xml('no_permission_fetch_threadxml');
    }    
    
    // *********************************************************************************
    // check if there is a forum password and if so, ensure the user has it set
    verify_forum_password($foruminfo['forumid'], $foruminfo['password']);        
    
    $userid = $vbulletin->userinfo['userid'];
    $threadssql = "
        SELECT 
            thread.*,
            threadread.readtime AS threadread,
            forumread.readtime as forumread,
            subscribethread.subscribethreadid AS subscribethreadid
        FROM " . TABLE_PREFIX . "thread AS thread    
        LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = $userid)         
        LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (thread.forumid = forumread.forumid AND forumread.userid = $userid)         
        LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread ON (thread.threadid = subscribethread.threadid AND subscribethread.userid = $userid)        
        WHERE thread.threadid IN (0$threadid)
        ORDER BY lastpost DESC
    ";
        
    $thread = $db->query_first($threadssql);     
    
    // TODO: Remove this HACK!
    $thread['threadtitle'] = $thread['title'] = unhtmlspecialchars($threadinfo['title'],true);
    
    $retval['Thread'] = ConsumeArray($thread,$structtypes['Thread']);                 
    
    $result['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']); 
    $retval['Result'] = $result;
   
    return $retval;     
}

function ListForums($forumid)
{
    global $db,$vbulletin,$server,$structtypes,$lastpostarray;

    $userid = $vbulletin->userinfo['userid'];
    
    // ### GET FORUMS & MODERATOR iCACHES ########################
    cache_ordered_forums(1,1);
    if (empty($vbulletin->iforumcache))
    {
        $forums = $vbulletin->db->query_read_slave("
            SELECT forumid, title, link, parentid, displayorder, title_clean, description, description_clean,
            (options & " . $vbulletin->bf_misc_forumoptions['cancontainthreads'] . ") AS cancontainthreads
            FROM " . TABLE_PREFIX . "forum AS forum
            WHERE displayorder <> 0 AND
            password = '' AND
            (options & " . $vbulletin->bf_misc_forumoptions['active'] . ")
            ORDER BY displayorder
        ");
        
        $vbulletin->iforumcache = array();
        while ($forum = $vbulletin->db->fetch_array($forums))
        {
            $vbulletin->iforumcache["$forum[parentid]"]["$forum[displayorder]"]["$forum[forumid]"] = $forum;
        }
        unset($forum);
        $vbulletin->db->free_result($forums);
    }    

    // define max depth for forums display based on $vbulletin->options[forumhomedepth]
    define('MAXFORUMDEPTH', 1);
    
    if (is_array($vbulletin->iforumcache["$forumid"]))
    {
        $childarray = $vbulletin->iforumcache["$forumid"];
    }
    else
    {
        $childarray = array($vbulletin->iforumcache["$forumid"]);
    }
    
    if (!is_array($lastpostarray))
    {
        fetch_last_post_array();
    }    
    
    // add the current forum info
    // get the current location title
    $current = $db->query_first("SELECT title FROM " . TABLE_PREFIX . "forum AS forum WHERE (forumid = $forumid)");
    if (strlen($current['title']) == 0)
    {
        $current['title'] = 'INDEX';
    }

    $forum = fetch_foruminfo($forumid);
    $lastpostinfo = $vbulletin->forumcache["$lastpostarray[$forumid]"];    
    $isnew = fetch_forum_lightbulb($forumid, $lastpostinfo, $forum);
    
    $curforum['ForumID'] = $forumid;
    $curforum['Title'] = $current['title'];
    $curforum['IsNew'] = $isnew == "new";
    $curforum['IsCurrent'] = true;
    
    $forumlist = array();
    
    foreach ($childarray as $subforumid)
    {
        // hack out the forum id
        $forum = fetch_foruminfo($subforumid);
        if (!$forum['displayorder'] OR !($forum['options'] & $vbulletin->bf_misc_forumoptions['active']))
        {
            continue;
        }    

        $forumperms = $vbulletin->userinfo['forumpermissions']["$subforumid"];
        if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($vbulletin->forumcache["$subforumid"]['showprivate'] == 1 OR (!$vbulletin->forumcache["$subforumid"]['showprivate'] AND !$vbulletin->options['showprivateforums'])))
        { // no permission to view current forum
            continue;
        }    
        
        $lastpostinfo = $vbulletin->forumcache["$lastpostarray[$subforumid]"];    
        $isnew = fetch_forum_lightbulb($forumid, $lastpostinfo, $forum);            
        
        $tempforum['ForumID'] = $forum['forumid'];
        $tempforum['Title'] = $forum['title'];
        $tempforum['IsNew'] = $isnew == "new";
        $tempforum['IsCurrent'] = false;
        array_push($forumlist,$tempforum);
        unset($tempforum);
    }        
    
    $result['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']); 
    
    $retval['Result'] = $result;
    $retval['CurrentForum'] = $curforum;
    $retval['ForumList'] = $forumlist;
    
    return $retval;
}

function ListParentForums($forumid)
{
    global $db,$vbulletin,$server,$structtypes,$lastpostarray;
    
    $tempinfo = fetch_foruminfo($forumid);
    
    if ($forumid == -1)    
    {
        return ListForums($who,-1);
    }
    
    if ($tempinfo['parentid'] != -1)
    {
        $info = fetch_foruminfo($tempinfo['parentid']);
        return ListForums($info['forumid']);        
    }
    else
    {
        return ListForums(-1);        
    }      
}

function ListPosts($who,$threadid,$pagenumber,$perpage)
{
    global $db,$vbulletin,$server,$structtypes,$lastpostarray;
    
    $result = RegisterService($who);
    if ($result['Code'] != 0)
    {
        $retval['Result'] = $result;
        return $retval;
    }    
    
    // *********************************************************************************
    // get thread info
    $threadinfo = $thread = fetch_threadinfo($threadid);
    
    if (!($thread['threadid'] > 0))
    {
        print_error_xml('invalid_threadid_fetch_postsxml');            
    }
    
    // *********************************************************************************
    // get forum info
    $forum = fetch_foruminfo($thread['forumid']);
    $foruminfo =& $forum;

    // *********************************************************************************
    // check forum permissions
    $forumperms = fetch_permissions($thread['forumid']);
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
    {
        print_error_xml('no_permission_fetch_postsxml');
    }
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($thread['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
    {
        print_error_xml('no_permission_fetch_postsxml');
    }
    
    // *********************************************************************************
    // check if there is a forum password and if so, ensure the user has it set
    verify_forum_password($foruminfo['forumid'], $foruminfo['password']);    
    
    
    // TODO: the client expects 'threadtitle', remove this HACK
    $threadinfo['title'] = $threadinfo['threadtitle'] = unhtmlspecialchars($threadinfo['title'],true);
    
    $retval['Thread'] = ConsumeArray($threadinfo,$structtypes['Thread']);
    
    $limitlower = ($pagenumber - 1) * $perpage;
    $userid = $vbulletin->userinfo['userid'];
    
    $postssql = "
        SELECT 
            *,
            post.dateline as dateline,
            threadread.readtime as threadread,
            forumread.readtime as forumread
        FROM " . TABLE_PREFIX . "post as post 
        LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
        LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = post.threadid AND threadread.userid = $userid) 
        LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (thread.forumid = forumread.forumid AND forumread.userid = $userid) 
        WHERE 
            post.threadid = $threadid 
            AND post.visible = 1 
        ORDER By post.dateline ASC 
        LIMIT $limitlower, $perpage        
    ";
    
    $postlist = array();
    $posts = $db->query_read_slave($postssql);        
    while ($post = $db->fetch_array($posts))
    {    
        $post['isnew'] = true;        
        if ($post['threadread'] >= $post['dateline'] || (TIMENOW - ($vbulletin->options['markinglimit'] * 86400)) >= $post['dateline'] )
        {
            $post['isnew'] = false;
        }        
        
        $post['datelinetext'] = vbdate($vbulletin->options['dateformat'],$post['dateline'],true, true, false)." ".vbdate($vbulletin->options['timeformat'],$post['dateline'],true);
        $post['pagetext'] = strip_bbcode($post['pagetext'],true,false,false); 
        $post['pagetext']  = unhtmlspecialchars($post['pagetext'] ,true);   
        
        array_push($postlist,ConsumeArray($post,$structtypes['Post']));
    }    
    
    $retval['PostList'] = $postlist;  
    
    $result['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']);   
    $retval['Result'] = $result;    
    return $retval;
}

function ListThreads($forumid,$pagenumber,$perpage)
{
    global $db,$vbulletin,$server,$structtypes,$lastpostarray;
    
    // get the total threads count    
    $threadcount = $db->query_first("SELECT threadcount FROM " . TABLE_PREFIX . "forum WHERE (forumid = $forumid);");
    
    if ($threadcount > 0)
    {
        $forumperms = fetch_permissions($forumid);
        if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
        {
            // TODO: handle this properly
            print_error_xml('no_permission_fetch_threadsxml');
        }            
        
        $userid = $vbulletin->userinfo['userid'];
        $limitlower = ($pagenumber - 1) * $perpage;
        
        $getthreadidssql = ("
            SELECT 
                thread.threadid, 
                thread.lastpost, 
                thread.lastposter, 
                thread.lastpostid, 
                thread.replycount, 
                IF(thread.views<=thread.replycount, thread.replycount+1, thread.views) AS views
            FROM " . TABLE_PREFIX . "thread AS thread
            WHERE forumid = $forumid
                AND sticky = 0
                AND visible = 1
            ORDER BY 
                lastpost DESC         
            LIMIT $limitlower, $perpage
        ");    
    
        $getthreadids = $db->query_read_slave($getthreadidssql);
        
        $ids = '';
        while ($thread = $db->fetch_array($getthreadids))
        {
            $ids .= ',' . $thread['threadid'];
        }
    
            $threadssql = "
                SELECT 
                    thread.threadid, 
                    thread.title AS threadtitle, 
                    thread.forumid, 
                    thread.lastpost, 
                    thread.lastposter, 
                    thread.lastpostid, 
                    thread.replycount,
                    threadread.readtime AS threadread,
                    forumread.readtime as forumread,
                    subscribethread.subscribethreadid AS subscribethreadid
                FROM " . TABLE_PREFIX . "thread AS thread    
                LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = $userid)         
                LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (thread.forumid = forumread.forumid AND forumread.userid = $userid)         
                LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread ON (thread.threadid = subscribethread.threadid AND subscribethread.userid = $userid)        
                WHERE thread.threadid IN (0$ids)
                ORDER BY lastpost DESC
            ";
            
        $threads = $db->query_read_slave($threadssql);        
        $threadlist = array();
        
        while ($thread = $db->fetch_array($threads))
        {   
            $thread['issubscribed'] = $thread['subscribethreadid'] > 0;
            
            $thread['isnew'] = true;        
            if ($thread['forumread'] >= $thread['lastpost'] || $thread['threadread'] >= $thread['lastpost'] || (TIMENOW - ($vbulletin->options['markinglimit'] * 86400)) > $thread['lastpost'] )
            {
                $thread['isnew'] = false;
            }
            
            $thread['threadtitle'] = unhtmlspecialchars($thread['threadtitle'],true);   
            $thread['title'] = unhtmlspecialchars($thread['threadtitle'],true);
            
            $thread['datelinetext'] = vbdate($vbulletin->options['dateformat'],$thread['lastpost'],true,true,false)." ".vbdate($vbulletin->options['timeformat'],$thread['lastpost']);
            
            $thread = ConsumeArray($thread,$structtypes['Thread']);            
            array_push($threadlist,$thread);            
        }        
    }   
    
    $result['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']);   
    $retval['Result'] = $result;
    $retval['ThreadList'] = $threadlist;
    $retval['ThreadCount'] = $threadcount['threadcount'];

    return $retval;
}

function MarkForumRead($who,$forumid)
{
    global $db,$vbulletin,$server,$structtypes,$lastpostarray;
    
    $result = RegisterService($who);
    if ($result['Code'] != 0)
    {
        $retval['Result'] = $result;
        return $retval;
    }  

    $foruminfo = fetch_foruminfo($forumid);
    mark_forum_read($foruminfo,$vbulletin->userinfo['userid'],TIMENOW);

    $result['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']); 
    $retval['Result'] = $result;
   
    return $retval;      
}

function MarkThreadRead($who,$threadid)
{
    global $db,$vbulletin,$server,$structtypes,$lastpostarray;
    
    $result = RegisterService($who);
    if ($result['Code'] != 0)
    {
        $retval['Result'] = $result;
        return $retval;
    }

    $threadinfo = fetch_threadinfo($threadid);
    $foruminfo = fetch_foruminfo($threadinfo['forumid']);

    mark_thread_read($threadinfo,$foruminfo,$vbulletin->userinfo['userid'],TIMENOW);    
    
    $result['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']); 
    $retval['Result'] = $result;
   
    return $retval;      
}

function PostReply($who,$threadid,$pagetext,$quotepostid = 0)
{
    global $db,$vbulletin,$server,$structtypes,$lastpostarray;

    $result = RegisterService($who);
    if ($result['Code'] != 0)
    {
        $retval['Result'] = $result;
        return $retval;
    } 

    $threadinfo = fetch_threadinfo($threadid);
    $foruminfo = fetch_foruminfo($threadinfo['forumid'],false);

    $postdm = new vB_DataManager_Post($vbulletin, ERRTYPE_STANDARD);
    $postdm->set_info('skip_maximagescheck', true);
    $postdm->set_info('forum', $foruminfo);
    $postdm->set_info('thread', $threadinfo);  
    $postdm->set('threadid', $threadid);    
    $postdm->set('userid', $vbulletin->userinfo['userid']);    
    $postdm->set('allowsmilie', 1);
    $postdm->set('visible', 1);
    $postdm->set('dateline', TIMENOW);        
    
    if ($quotepostid > 0)
    {
        $quote_postids[] = $quotepostid;
        $quotetxt = fetch_quotable_posts($quote_postids,$threadinfo['threadid'],$unquoted_post_count, $quoted_post_ids, 'only');
        $pagetext = "$quotetxt$pagetext";
    }
    
    $postdm->set('pagetext', "$pagetext");                                 
    
    
    $postdm->pre_save();
    $postid = 0;
    
    if (count($postdm->errors) > 0)
    { // pre_save failed
        return ErrorResult('pre_save_failed_thread_reply');
    }    
    else
    {
        $postid = $postdm->save();
        
        require_once('./includes/functions_databuild.php'); 
        build_thread_counters($threadinfo['threadid']); 
        build_forum_counters($foruminfo['forumid']);                    
        correct_forum_counters($threadinfo['threadid'], $foruminfo['forumid']);        
        
        mark_thread_read($threadinfo, $foruminfo, $vbulletin->userinfo['userid'], TIMENOW);
    }    
    
    $retval['PostID'] = $postid;

    $result['Code'] = 1;
    $result['Text'] = "QuotePostID: $quotepostid";
    $result['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']); 
                                                     
    $retval['Result'] = $result;

    return $retval;      
}

function PostNewThread($who,$forumid,$title,$pagetext)
{
    global $db,$vbulletin,$server,$structtypes,$lastpostarray;

    $result = RegisterService($who);
    if ($result['Code'] != 0)
    {
        $retval['Result'] = $result;
        return $retval;
    }
    
    $insertid = 0;
    $foruminfo = fetch_foruminfo($forumid,false);
    if ($foruminfo['forumid'] > 0)
    {
        $userid = 0;             // such is the case for network posts
        $postuserid = 0;     // same as above
        $forumid = $foruminfo['forumid'];
        $pagetext = fetch_censored_text($pagetext);
        //$title = $title;
        $allowsmilie = '1';
        $visible = '1';
        $dateline = TIMENOW;
        
        $threaddm = new vB_DataManager_Thread_FirstPost($vbulletin, ERRTYPE_STANDARD);
        
        // there is no (easy) way to parse out an excessive amount of smilies when dong the image check
        // so we check for [IMG] tags only and then disable the check for smilies
        #$threaddm->set_info('skip_maximagescheck', true);
        $threaddm->do_set('userid', $vbulletin->userinfo['userid']);    
        $threaddm->do_set('username', $vbulletin->userinfo['username']); 
        $threaddm->do_set('postuserid', $postuserid);
        $threaddm->do_set('forumid', $forumid);
        $threaddm->do_set('pagetext', $pagetext);
        $threaddm->do_set('title', $title);
        $threaddm->do_set('allowsmilie', $allowsmilie);
        $threaddm->do_set('visible', $visible);
        $threaddm->do_set('dateline', $dateline);

        $threaddm->pre_save();        
        if (count($threaddm->errors) > 0)
        {
            return ErrorResult('pre_save_failed_new_thread'); 
        }
        else
        {
            // save the thread
            $insertid = $threaddm->save();
            
            require_once('./includes/functions_databuild.php'); 
            build_forum_counters($forumid);
        }        
    }
    
    if ($insertid > 0)
    {
        $retval['PostID'] = $insertid;
        $retval['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']); 
        
        $result['Code'] = 1;
        $retval['Result'] = $result;
    }
    else
    {
        return ErrorResult('save_failed_thread_reply');  
    }
    
    return $retval;              
}

function SetIMNotification($who,$on)
{
    global $db,$vbulletin,$server,$structtypes,$lastpostarray;
    
    $result = RegisterService($who);
    if ($result['Code'] != 0)
    {
        $retval['Result'] = $result;
        return $retval;
    } 

    $userid = $vbulletin->userinfo['userid'];
    $onoff = 0;
    if ($on)
    {
        $onoff = 1;
    }
    
    $db->query_write("
        UPDATE " . TABLE_PREFIX . "user 
        SET instantimnotification=$onoff
        WHERE (userid = $userid);
    ");   
    
    $result['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']); 
    $retval['Result'] = $result;
   
    return $retval;      
}

function SubscribeThread($who,$threadid)
{
    global $db,$vbulletin,$server,$structtypes,$lastpostarray;
    
    $result = RegisterService($who);
    if ($result['Code'] != 0)
    {
        $retval['Result'] = $result;
        return $retval;
    }
    
    $threadinfo = fetch_threadinfo($threadid);
    $foruminfo = fetch_foruminfo($threadinfo['forumid'],false);
    
    if (!$foruminfo['forumid'])
    {
        return ErrorResult("invalid_forumid_subscribe_thread");
    }
    
    $forumperms = fetch_permissions($foruminfo['forumid']);
    if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
    {
        return ErrorResult("no_forum_permission_subscribe_thread");
    }    
    
    if (!$foruminfo['allowposting'] OR $foruminfo['link'] OR !$foruminfo['cancontainthreads'])
    {
        return ErrorResult("forum_closed_subscribe_thread");
    }    
    
    if (!verify_forum_password($foruminfo['forumid'], $foruminfo['password'], false))
    {
        return ErrorResult("invalid_forum_password_subscribe_thread");
    }
    
    if ($threadinfo['threadid'] > 0)
    {
        if ((!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts')) OR ($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid'], 'candeleteposts')))
        {
            return ErrorResult('cannot_view_thread_subscribe_thread');    
        }        
        
        if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR (($vbulletin->userinfo['userid'] != $threadinfo['postuserid'] OR !$vbulletin->userinfo['userid']) AND !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers'])))
        {
            return ErrorResult("no_thread_permission_subscribe_thread");
        }        
        
        $emailupdate = 1; // Instant notification by email
        $folderid = 0; // Delfault folder
        
        /*insert query*/
        $db->query_write("
            REPLACE INTO " . TABLE_PREFIX . "subscribethread (userid, threadid, emailupdate, folderid, canview)
            VALUES (" . $vbulletin->userinfo['userid'] . ", $threadinfo[threadid], $emailupdate, $folderid, 1)
        ");        

        // TODO: remove this HACK!
        $threadinfo['threadtitle'] = $threadinfo['title'];
        $retval['Thread'] = ConsumeArray($threadinfo,$structtypes['Thread']);
    }
    else
    {
        return ErrorResult("invalid_threadid_subscribe_thread");        
    } 
    
    $result['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']); 
    $retval['Result'] = $result;
   
    return $retval;  
}

function UnSubscribeThread($who,$threadid) 
{
     global $db,$vbulletin,$server,$structtypes,$lastpostarray;

    $result = RegisterService($who);
    if ($result['Code'] != 0)
    {
        $retval['Result'] = $result;
        return $retval;
    }  
    
    if (is_numeric($threadid))
    { // delete this specific thread subscription
    
        $userid = $vbulletin->userinfo['userid'];
        if ($threadid > 0)
        {
            $db->query_write("
                DELETE FROM " . TABLE_PREFIX . "subscribethread 
                WHERE (threadid = $threadid AND userid = $userid);
            ");            
        }
        else if ($threadid == -1)
        {
            $db->query_write("
                DELETE FROM " . TABLE_PREFIX . "subscribethread 
                WHERE (userid = $userid);
            ");            
        }
    }
    else
    {
        return ErrorResult('invalid_threadid_unsubscribe_thread');        
    }    
    
    $retval['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']); 
   
    return $retval;       
}

function WhoAmI()
{
	global $db,$vbulletin,$server,$structtypes;

    $result['Code'] = 0;
	$result['Text'] = ''; 
	$result['RemoteUser'] = ConsumeArray($vbulletin->userinfo,$structtypes['RemoteUser']); 
	return $result;
}

$namespace = $vbulletin->options['bburl'];

// create a new soap server
$server = new soap_server();

// configure our WSDL
$server->configureWSDL("VBotService","urn:VBotService");

// set our namespace
$server->wsdl->schemaTargetNamespace = $namespace;

// include service types and functions           
include (DIR . '/shell/shelltypes.php');
include (DIR . '/shell/shellservices.php');

// Get our posted data if the service is being consumed
// otherwise leave this data blank.                
$HTTP_RAW_POST_DATA = isset($GLOBALS['HTTP_RAW_POST_DATA']) 
                ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';

// temporarily override the board's yestoday setting to show "Today"/"Yesterday"
if ($vbulletin->options['yestoday'] == 2)
{
    $vbulletin->options['yestoday'] = 1;
}                
                
// pass our posted data (or nothing) to the soap service    
//$server->debug_flag = true;
$server->service($HTTP_RAW_POST_DATA);                
exit();
?>