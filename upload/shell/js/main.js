var mainTerminal = {};

var System = 
{
    loggedIn: false,
    userid: 0,
    username: "",
    bburl: "",
    currentForum: "",
    currentForumID: -1,
    currentThread: "",
    currentThreadID: 0,
    currentPost: "",
    currentPostID: 0,
}

var commandText = function(text)
{
    return "[[g;#EEEEEE;]" + text + "]";
}

var titleText = function(text)
{
    return "[[u;inherit;]" + text + "]";
}

var errorText = function(text)
{
    return "[[g;#FF0000;]" + text + "]";
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
        }
    },

    logout: function()
    {
        // console.log("command: " + command);
        // console.log("term   : " + term);
        console.log("logout command issued");
        // var test = $.post('/index.php', {command: 'hi!'}).then(
        //     function(response)
        //     {
        //         this.echo("THIS IS A RESPONSE!");
        //     });
        var test = $.post('/soapservice.php');

        this.echo("RESPONSE: " + test);
        console.log(test);
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
                mainTerminal.echo(errorText("Error: ") + soapResponse.toXML());
            }            
        });
    },

    lf : function(arrayP)
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
            console.log("lf() issues");
            $.soap(
            {
                url: '/soapservice.php/',
                method: 'ListForums',
                // data: { ForumId: System.currentForumID },
                data: { ForumId: 13 },
                success: function (response) 
                {
                    // var json = $.xml2json(response.toJSON());
                    // console.log(json);
                //     json.find('item').each(function()
                // {
                //     var titleText = $(this).find('Title').text();
                //     console.log("titleText: " + titleText);
                // });

                    // console.log(soapResponse.toJSON());
                    // var obj = soapResponse.toJSON();

                //     soapResponse.toJSON().find('item').each(function()
                // {
                //     var titleText = $(this).find('Title').text();
                //     console.log("titleText: " + titleText);
                // });
                    // // console.log(soapResponse.toJSON());
                    // $.each(, function()
                    // {
                    //     var titleText = $(this).find('Title').text();
                    //     console.log("titleText: " + titleText);
                    // });
                
                
                // ).find('item').each(function()
                // {
                //     var titleText = $(this).find('Title').text();
                //     console.log("titleText: " + titleText);
                // });
                    // var xmlResponse = soapResponse.toXML().documentElement;
                    // var forumEl = xmlResponse.getElementsByTagName("ForumList");
                    // console.log(forumEl);
                    // forumEl[0].childNodes.forEach(function(item, index)
                    // {
                    //     item.
                    //     console.log(index);
                    //     console.log(item);
                    // });
                    // if (forumEl.length > 0)
                    // {
                    //     var list = forumEl.getElementsByTagName("item");
                    //     for (var i=0; i < list.length; i++)
                    //     {
                    //         console.log(list[i]);
                    //     }
                    // }
                },
                error: function (SOAPResponse) 
                {
                    mainTerminal.echo(errorText("Error: ") + soapResponse.toXML());
                }
            });
        }
    },

    whereami : function()
    {
        this.echo();
        this.echo(commandText("Current Forum  : ") + System.currentForum);
        this.echo(commandText("Current Thread : ") + System.currentThread);
        this.echo(commandText("Current Post   : ") + System.currentPost);
        this.echo(); 
    },

    menu: function()
    {
        this.exec('help');
    },
}

jQuery(document).ready(function($) 
{
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
