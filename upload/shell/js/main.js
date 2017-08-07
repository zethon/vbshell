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
    
    currentForum: {title: "", id: -1},

    currentThread: "",
    currentThreadID: 0,
    currentPost: "",
    currentPostID: 0,
    lastList: ListEnum.none,
    forumList:[],
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

    help: function()
    {
        if (System.loggedIn)
        {    
            this.echo();
            this.echo("|  " + commandText("logout") + "             - Logout");
            this.echo();
            this.echo("|  " + commandText("whoami") + "             - Print current user name");
            this.echo("|  " + commandText("whereami") + "           - Print the current forum, thread and post");
            this.echo();
            this.echo("|  " + commandText("lf") + "                 - List the subforums of the current forum");
            this.echo("|  " + commandText("cf") + "                 - Navigate to a forum by index (example 'cf 1')");
            this.echo();
        }
    },

    whoami: function()
    {
        console.log("whoami() issued");

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
                    console.log(userIdArr);
                    console.log(usernameArr);
                    var userid = userIdArr[0].textContent;
                    var username = usernameArr[0].textContent;
                    mainTerminal.echo("You are " + commandText(username) + "[" + userid + "]");
                    mainTerminal.echo();
                }
            },
            error: function (SOAPResponse) 
            {
                mainTerminal.error("Error: " + soapResponse.toXML());
            }            
        });
    },

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
                        console.log(response.toXML());
                        var currentEl = response.toXML().documentElement.getElementsByTagName('CurrentForum');
                        if (currentEl.length > 0)
                        {
                            System.currentForum.title = $(currentEl).find("Title").text();
                            System.currentForum.id = $(currentEl).find("ForumID").text();
                            mainTerminal.echo("Current forum: " + commandText(System.currentForum.title));
                            mainTerminal.echo();
                            mainTerminal.set_prompt("[[;#00ffff;]{0}]> ".format(System.currentForum.title));
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
                System.currentForum.id = System.forumList[idx-1].forumid;
                System.currentForum.title = System.forumList[idx-1].title;
                mainTerminal.echo("Current forum: " + commandText(System.currentForum.title));
                mainTerminal.echo();
                mainTerminal.set_prompt("[[;#00ffff;]{0}]> ".format(System.currentForum.title));
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

    lf : function()
    {
        if (arguments.length > 0)
        {
            console.log(arguments);
            for(var i=0; i< arguments.length; i++) 
            {
                console.log("'lf' options: " + arguments[i]);
            }     
        }
        else
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

                            var newmarker = hasnew ? "[[b;#D050D0;]*]" : "";
                            mainTerminal.echo("[[[b;#f4f4f4;]"+ (i+1) +"]] "+newmarker+"[[b;#00F400;]"+title+"]");

                            var obj = {title: title, forumid: forumid, hasnew: hasnew, iscurrent: iscurrent};
                            System.forumList.push(obj);
                        }

                        mainTerminal.echo();
                        mainTerminal.echo("Use 'cf [idx]' to navigate to that forum");
                        mainTerminal.echo("Use 'cf ..' to navigate to the parent forum");
                    }
                },
                error: function (SOAPResponse) 
                {
                    mainTerminal.echo("Error: " + soapResponse.toXML());
                }
            });
        }
    },

    whereami : function()
    {
        this.echo();
        this.echo(("Current Forum  : ") + commandText(System.currentForum.title) + "[" + System.currentForum.id + "]");
        // this.echo(commandText("Current Thread : ") + System.currentThread);
        // this.echo(commandText("Current Post   : ") + System.currentPost);
        this.echo(); 
    },

    menu: function()
    {
        this.exec('help');
    },
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
        mainTerminal = $('body').terminal
        (
            App, 
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
                convertLinks: true
            }
        );
    }
});
