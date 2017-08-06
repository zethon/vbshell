var System = 
{
    loggedIn: false,
    userid: 0,
    username: "",
    bburl: "",
    currentForum: "",
    currentForumID: 0,
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
    loggedIn: false,
    username: "",
    userid: 0,

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
        // this.echo();
        // this.echo(commandText("UserID  : ") + System.userid);
        // this.echo(commandText("Username: ") + System.username);
        // this.echo();

        console.log("whoami() issued");
        
        $.soap({
            url: '/soapservice.php',
            method: 'WhoAmI',
        
            // data: {
            //     name: 'Remy Blom',
            //     msg: 'Hi!'
            // },

            success: function (soapResponse) 
            {
                console.log("YAY!!");
                // do stuff with soapResponse
                // if you want to have the response as JSON use soapResponse.toJSON();
                // or soapResponse.toString() to get XML string
                // or soapResponse.toXML() to get XML DOM
            },
            error: function (SOAPResponse) 
            {
                console.log("OH SNAP ERROR!!!");
            }
        });


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
        $('body').terminal(App, 
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
        });
    }
});
