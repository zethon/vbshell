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
        console.log("logout command issued");
        this.echo(errorText("Not yet implemented"));
    },

    whoami: function()
    {
        this.echo();
        this.echo(commandText("UserID  : ") + System.userid);
        this.echo(commandText("Username: ") + System.username);
        this.echo();
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
    }
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
