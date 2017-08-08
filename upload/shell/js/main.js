// the terminal object we'll use for output
var mainTerminal = {};

// the types of lists we can ask for
var ListEnum = { "none": 0, "forum":1, "thread":2, "post":3 };

var System = 
{
    loggedIn: false,
    userid: 0,
    username: "",
    bburl: "",
    
    currentForum: {id: -1},
    currentThread: {id: -1},

    threadNav: { pagenum: 1, perpage: 10 },
    postNav: { idx: 1, pp: 10 },
    currentThreadPageIdx: 0,
    currentPostPageIdx: 0,

    lastList: ListEnum.none,
    forumList:[],
    threadList: [],
    postList: []
}

var commandText = function(text)
{
    return "[[g;#EEEEEE;]" + text + "]";
}

var titleText = function(text)
{
    return "[[u;inherit;]" + text + "]";
}

var App = 
{
    // Welcome: prints the welcome message
    welcome: function(ret)
    {
        if (typeof ret === 'undefined') ret = false;

        var greetText = "";
        if (System.loggedIn)
        {
            greetText += "[[b;#f4f4f4;]Welcome " + System.username + "!]\n";
            greetText += "Type " + commandText('help') + " for a list of commands.\n";
        }
        else
        {
            greetText += "You are not logged in!\n";
            greetText += "Please visit "+System.bburl+" to login before using shell!\n";
        }

        return greetText;
    },

    // Change to Forum: sets the current forum according to the index
    cf : function(command)
    {
        if (command != undefined)
        {
            var idx = parseInt(command);
            if (command == ".." && System.currentForum.id != -1)
            {
                $.soap(
                {
                    url: '/soapservice.php/',
                    method: 'ListParentForums',
                    data: { ForumId: System.currentForum.id },
                    success: function (response) 
                    {
                        var currentEl = response.toXML().documentElement.getElementsByTagName('CurrentForum');
                        if (currentEl.length > 0)
                        {
                            System.currentThread = {};
                            currentPostPageIdx = 1;
                            System.currentForum.title = $(currentEl).find("Title").text();
                            System.currentForum.id = $(currentEl).find("ForumID").text();
                            mainTerminal.exec('lf', true);
                        }
                    },
                    error: function (SOAPResponse) 
                    {
                        mainTerminal.error("Error: " + SOAPResponse.toXML());
                    }
                });
            }
            else if (!isNaN(idx) && (idx-1) <= System.forumList.length)
            {
                System.currentThread = {};
                currentPostPageIdx = 1;
                System.currentForum.id = System.forumList[idx-1].forumid;
                System.currentForum.title = System.forumList[idx-1].title;
                this.exec('lf', true);
            }
            else
            {
                mainTerminal.error("Invalid index");
            }
        }
        else
        {
            mainTerminal.error("Invalid index");
        }
    },

    // List Forums: lists the sub forums of the current forum
    lf: function()
    {
        $.soap(
        {
            url: '/soapservice.php/',
            method: 'ListForums',
            data: { ForumId: System.currentForum.id },
            success: function (response) 
            {
                var items = response.toXML().documentElement.getElementsByTagName('item');
                if (items.length > 0)
                {
                    System.lastList = ListEnum.forum;
                    System.forumList = [];

                    for (var i=0; i < items.length; i++)
                    {
                        var title = $(items[i]).find("Title").text();
                        var forumid = $(items[i]).find("ForumID").text();
                        var hasnew = ($(items[i]).find("IsNew").text() == "true");
                        var iscurrent = ($(items[i]).find("IsCurrent").text() == "true");

                        var obj = {title: title, forumid: forumid, hasnew: hasnew, iscurrent: iscurrent};
                        System.forumList.push(obj);
                        
                        if (hasnew)
                        {
                            mainTerminal.echo("[ " + "[[b;#2c9995;]"+ (i+1) +"] ] [[b;#f4f4f4;]"+title+"]");
                        }
                        else
                        {
                            mainTerminal.echo("[ " + "[[b;#2c9995;]"+ (i+1) +"] ] "+title+"");
                        }
                    }

                    // mainTerminal.echo("\n");
                    // mainTerminal.echo("Use 'cf [idx]' to navigate to that forum");
                    // mainTerminal.echo("Use 'cf ..' to navigate to the parent forum");
                }
            },
            error: function (SOAPResponse) 
            {
                mainTerminal.echo("Error: " + soapResponse.toXML());
            }
        });
    },

    // List Posts: lists the posts in the current thread
    lp: function()
    {
        var pagenum = (arguments[0] != undefined) ? arguments[0] : 1;
        var perpage = (arguments[1] != undefined) ? arguments[1] : 10;

        $.soap(
        {
            url: '/soapservice.php/',
            method: 'ListPosts',
            data: { ThreadID: System.currentThread.id, PageNumber: pagenum, PerPage: perpage },
            success: function (response) 
            {
                var items = response.toXML().documentElement.getElementsByTagName('item');
                if (items.length > 0)
                {
                    System.postList = [];
                    System.lastList = ListEnum.post;

                    for (var i=0; i < items.length; i++)
                    {
                        var obj = 
                        {
                            postid: parseInt($(items[i]).find("PostID").text()),
                            username: $(items[i]).find("Username").text(),
                            pagetext: $(items[i]).find("PageText").text(),
                            title: $(items[i]).find("Title").text(),
                            dateline: parseInt($(items[i]).find("DateLine").text()),
                            datelinetext: $(items[i]).find("DateLineText").text(),
                            ipaddress: $(items[i]).find("IpAddress").text(),
                            isnew: ($(items[i]).find("IsNew").text() == "true"),
                        };

                        System.postList.push(obj);

                        var trimmedString = obj.pagetext.substr(0, 60).replace(/\n|\r/g, "");
                        trimmedString = trimmedString.substr(0, Math.min(trimmedString.length, trimmedString.lastIndexOf(" ")))
                        trimmedString = "\"" + trimmedString + "\"";

                        var index = (i+1) + (perpage * (pagenum - 1));
                        if (obj.isnew)
                        {
                            mainTerminal.echo("[ [[b;#2c9995;]"+ (index) +"] ] [[b;#f4f4f4;]"+trimmedString+"], " + obj.datelinetext + " by " + obj.username);
                        }
                        else
                        {
                            mainTerminal.echo("[ [[b;#2c9995;]"+ (index) +"] ] "+trimmedString+", " + obj.datelinetext + " by " + obj.username);
                        }
                    }                        
                }
            },
            error: function (SOAPResponse) 
            {
                mainTerminal.error("Error: " + SOAPResponse.toXML());
            }
        });
    },

    // Change to Thread: sets the current thread according to the index
    ct : function(command)
    {
        if (command != undefined)
        {
            var idx = parseInt(command);
            if (!isNaN(idx) && (idx-1) <= System.threadList.length)
            {
                var t = System.threadList[idx-1];
                
                currentPostPageIdx = 1;
                System.currentThread = {};
                System.currentThread.id = t.threadid;
                System.currentThread.title = t.title;
                this.exec('lp');
            }
            else
            {
                mainTerminal.error("Invalid index");
            }
        }
    },

    // List Threads: lists the threads in the current forum
    lt : function()
    {
        if (System.currentForum.id != -1) 
        {
            var pagenum = 1;
            var perpage = System.threadNav.perpage;

            // handle the page # if passed in
            if (arguments.length > 0)
            {
                var pnInt = parseInt(arguments[0]);
                if (!isNaN(pnInt) && pnInt > 0)
                {
                    pagenum = pnInt;
                }
            }

            // handle the perpage setting if passed in
            if (arguments.length > 1)
            {
                var ppInt = parseInt(arguments[1]);
                perpage = ppInt;
            }

            console.log("listing thread, pagenum=" + pagenum + " perpage=" + perpage);
            System.threadNav.pagenum = pagenum;
            System.threadNav.perpage = perpage;
            
            $.soap(
            {
                url: '/soapservice.php/',
                method: 'ListThreads',
                data: { ForumId: System.currentForum.id, PageNumber: pagenum, PerPage: perpage },
                success: function (response) 
                {
                    var items = response.toXML().documentElement.getElementsByTagName('item');
                    if (items.length > 0)
                    {
                        System.threadList = [];
                        System.lastList = ListEnum.thread;

                        mainTerminal.echo("Page Number: [[b;#2c9995;]" + pagenum + "] Per Page: [[b;#2c9995;]" + perpage + "]");

                        for (var i=0; i < items.length; i++)
                        {
                            var obj = 
                            {
                                threadid: $(items[i]).find("ThreadID").text(),
                                title: $(items[i]).find("ThreadTitle").text(),
                                lastpost: $(items[i]).find("LastPoster").text(),
                                lastposter: $(items[i]).find("LastPoster").text(),
                                replycount: parseInt($(items[i]).find("ReplyCount").text()),
                                hasnew: ($(items[i]).find("IsNew").text() == "true"),
                                datelinetext: $(items[i]).find("DateLineText").text()
                            };

                            System.threadList.push(obj);
                            if (obj.hasnew)
                            {
                                mainTerminal.echo("[ [[b;#2c9995;]"+ (i+1) +"] ] [[b;#f4f4f4;]"+obj.title+"], " + obj.replycount + " replies, " + obj.datelinetext + " by " + obj.lastposter);
                            }
                            else
                            {
                                mainTerminal.echo("[ [[b;#2c9995;]"+ (i+1) +"] ] "+obj.title+", " + obj.replycount + " replies, " + obj.datelinetext + " by " + obj.lastposter);
                            }
                        }
                    }
                },
                error: function (response) 
                {
                    mainTerminal.echo("Error: " + response.toXML());
                }
            });
        }
        else 
        {
            mainTerminal.error("Please select a forum first. Use 'lf' to list forums.");
        }
    },

    sp: function(command)
    {
        if (command != undefined)
        {
            var idx = parseInt(command);
            if (!isNaN(idx) && (idx-1) >= 0)
            {
                $.soap(
                {
                    url: '/soapservice.php/',
                    method: 'GetPostByIndex',
                    data: { ThreadID: System.currentThread.id, Index: idx, ShowBBCode: false },
                    success: function (response) 
                    {
                        var post = response.toXML().documentElement.getElementsByTagName('Post');
                        var obj = 
                        {
                            postid: parseInt($(post[0]).find("PostID").text()),
                            username: $(post[0]).find("Username").text(),
                            pagetext: $(post[0]).find("PageText").text(),
                            title: $(post[0]).find("Title").text(),
                            dateline: parseInt($(post[0]).find("DateLine").text()),
                            datelinetext: $(post[0]).find("DateLineText").text(),
                            ipaddress: $(post[0]).find("IpAddress").text(),
                        };

                        currentPostPageIdx = idx;
                        mainTerminal.echo("[[b;#f4f4f4;]#" + idx + " " + obj.username + " at " + obj.datelinetext + "]");
                        mainTerminal.echo(obj.pagetext);
                    },
                    error: function (SOAPResponse) 
                    {
                        mainTerminal.error("Error: " + SOAPResponse.toXML());
                    }
                });

            }
        }
        else
        {

        }
    },

    // WhoAmI: prints the current username anr userid
    whoami: function()
    {
        $.soap(
        {
            url: '/soapservice.php/',
            method: 'WhoAmI',
            data: { },

            success: function (soapResponse) 
            {
                var xmlResponse = soapResponse.toXML().documentElement;
                var userIdArr = xmlResponse.getElementsByTagName("UserID");
                var usernameArr = xmlResponse.getElementsByTagName("Username");
                if (userIdArr.length > 0 && usernameArr.length > 0)
                {
                    var userid = userIdArr[0].textContent;
                    var username = usernameArr[0].textContent;
                    mainTerminal.echo("You are " + commandText(username) + "[" + userid + "]");
                    mainTerminal.echo("\n");
                }
            },
            error: function (SOAPResponse) 
            {
                mainTerminal.error("Error: " + soapResponse.toXML());
            }            
        });
    },

    // WhereAmI: prints the current forum, thread and post
    whereami : function()
    {
        this.echo("Current Board  : " + commandText(System.bburl));
        this.echo("Current Forum  : " + commandText(System.currentForum.title) + " [" + System.currentForum.id + "]");

        if (System.currentThread != undefined && System.currentThread.id != -1)
        {
            this.echo("Current Thread : " + commandText(System.currentThread.title) + " [" + System.currentThread.id + "]");
        }

        // if (System.currentPost != undefined && System.currentPost.id != -1)
        // {
        //     this.echo("Current Post : " + commandText(System.currentPost.title) + " [" + System.currentPost.id + "]");
        // }
        
        this.echo("\n"); 
    },

    // Help: prints the system help
    help: function()
    {
        if (System.loggedIn)
        {    
            this.echo("\n");
            this.echo("|  " + commandText("logout") + "             - Logout");
            this.echo("\n");
            this.echo("|  " + commandText("whoami") + "             - Print current user name");
            this.echo("|  " + commandText("whereami") + "           - Print the current forum, thread and post");
            this.echo("\n");
            this.echo("|  " + commandText("lf") + "                 - List the subforums of the current forum");
            this.echo("|  " + commandText("cf") + "                 - Navigate to a forum by index (example 'cf 1')");
            this.echo("\n");
        }
    },
}

var Options = 
{
    greetings: function(cb)
    {
        cb(App.welcome(true));
    },

    onBlur: function() 
    {
        // prevent loosing focus
        return false;
    },

    completion: true,
    checkArity: false,
    convertLinks: true,
    onInit: function(terminal) 
    {
        terminal.set_prompt("[ [[b;#4ef021;]" + System.bbtitle + "] ]$ ")
    },

    onAfterCommand: function(terminal)
    {
        if (System.currentForum.id == -1)
        {
            terminal.set_prompt("[ [[b;#4ef021;]" + System.bbtitle + "] ]$ ")
        }
        else
        {
            mainTerminal.set_prompt("[ [[b;#4ef021;]" + System.bbtitle + "]:[[;#00ffff;]{0}] ]$ ".format(System.currentForum.title));
        }
    },

    onBeforeCommand: function(terminal, command)
    {
        var passAlong = true;
        var idx = parseInt(command);
        if (!isNaN(idx))
        {
            switch (System.lastList)
            {
                case ListEnum.forum:
                {
                    passAlong = false;
                    terminal.exec('cf '+idx, false);
                    break;
                }

                case ListEnum.thread:
                {
                    passAlong = false;
                    terminal.exec('ct '+idx, false);
                    break;
                }

                case ListEnum.post:
                {
                    passAlone = false;
                    terminal.exec('sp ' + idx, false);
                    break;
                }

                default: break;                
            }
            return false;
        }
        else if (command == "..")
        {
            passAlong = false;
            terminal.exec('cf ..', false);
        }
        else if (command == "n")
        {
            switch (System.lastList)
            {
                case ListEnum.thread:
                {
                    var newidx = System.threadNav.pagenum + 1;
                    terminal.exec('lt ' + newidx + ' ' + System.threadNav.perpage);
                    passAlong = false;
                    break;
                }

                case ListEnum.post:
                {
                    var newidx = currentPostPageIdx + 1;
                    terminal.exec('sp ' + newidx, false);
                    passAlong = false;
                    break;
                }

                default: break;
            }
        }
        else if (command == "p")
        {
            // if (System.currentPost.idx != undefined)
            // {
            //     var newidx = System.currentPost.idx - 1;
            //     terminal.exec('sp ' + newidx, false);
            //     passAlong = false;
            // }
        }

        return passAlong;
    }
}

jQuery(document).ready(function($) 
{
    // add a string formatter
    if (!String.prototype.format) 
    {
        String.prototype.format = function () 
        {
            var args = arguments;
            return this.replace(/{(\d+)}/g, function (match, number) 
            {
                return typeof args[number] != 'undefined'
                    ? args[number]
                    : match
                    ;
            });
        };
    }

    if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) 
    {
        window.location.href = System.bburl;
    } 
    else 
    {
        mainTerminal = $('body').terminal(App, Options);
    }
});
