<?

$server->register
(
    // method name:
    'WhoAmI', 		 
    // parameter list:
    array(), 
    // return value(s):
    array('return'=>'tns:RequestResult'),
    // namespace:
    $namespace,
    // soapaction: (use default)
    false,
    // style: rpc or document
    'rpc',
    // use: encoded or literal
    'encoded',
    // description: documentation for the method
    'Returns the user information of the associated user credentials'
); 
                
$server->register
(
    'ListForums',
    array('ForumID'=>'xsd:int'),
    array('return'=>'tns:ForumListResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Returns a list of forums of the associated forumid'
);
          
$server->register(
    'ListParentForums',
    array('ForumID'=>'xsd:int'),
    array('return'=>'tns:ForumListResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Returns a list of forums of the associated forumid'
); 

$server->register(
    'ListThreads',
    array('UserCredentials'=>'tns:UserCredentials','ForumID'=>'xsd:int','PageNumber'=>'xsd:int','PerPage'=>'xsd:int'),
    array('return'=>'tns:ThreadListResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Returns a list of forums of the associated forumid'
);

$server->register(
    'ListPosts',
    array('UserCredentials'=>'tns:UserCredentials','ThreadID'=>'xsd:int','PageNumber'=>'xsd:int','PerPage'=>'xsd:int'),
    array('return'=>'tns:PostListResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Returns a list of posts of the associated threadid'
);

$server->register(
    'GetPostByIndex',
    array('UserCredentials'=>'tns:UserCredentials','ThreadID'=>'xsd:int','Index'=>'xsd:int','ShowBBCode'=>'xsd:boolean'),
    array('return'=>'tns:GetPostResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Resturns post information by index in the thread'
);

$server->register(
    'GetThread',
    array('UserCredentials'=>'tns:UserCredentials','ThreadID'=>'xsd:int'),
    array('return'=>'tns:GetThreadResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Resturns thread information of the given threadid'
);

$server->register(
    'SubscribeThread',
    array('UserCredentials'=>'tns:UserCredentials','ThreadID'=>'xsd:int'),
    array('return'=>'tns:GetThreadResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Subscribes user to threadid and returns the thread info'
);

$server->register(
    'UnSubscribeThread',
    array('UserCredentials'=>'tns:UserCredentials','ThreadID'=>'xsd:int'),
    array('return'=>'tns:RequestResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Subscribes user to threadid and returns the thread info'
);
   
$server->register(
    'MarkForumRead',
    array('UserCredentials'=>'tns:UserCredentials','ForumID'=>'xsd:int'),
    array('return'=>'tns:RequestResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Subscribes user to threadid and returns the thread info'
); 

$server->register(
    'MarkThreadRead',
    array('UserCredentials'=>'tns:UserCredentials','ThreadID'=>'xsd:int'),
    array('return'=>'tns:RequestResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Subscribes user to threadid and returns the thread info'
);  

$server->register(
    'SetIMNotification',
    array('UserCredentials'=>'tns:UserCredentials','On'=>'xsd:boolean'),
    array('return'=>'tns:RequestResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Turns IM notifications on and off'
);  
            
$server->register(
    'PostReply',
    array('UserCredentials'=>'tns:UserCredentials','ThreadID'=>'xsd:int','PageText'=>'xsd:string','QuotePostID'=>'xsd:int'),
    array('return'=>'tns:PostReplyResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Posts reply to threadid'
);  

$server->register(
    'PostNewThread',
    array('UserCredentials'=>'tns:UserCredentials','ForumID'=>'xsd:int','Title'=>'xsd:string','PageText'=>'xsd:string'),
    array('return'=>'tns:PostReplyResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Posts a new thread to ForumID'
);  

$server->register(
    'GetPostNotifications',   
    array('DoDelete'=>'xsd:boolean'),
    array('return'=>'tns:PostNotificationsResult'),
    $namespace,
    false,
    'rpc',
    'encoded',
    'Get Post notifications to be sent to subscribed users'
);        
            
?>