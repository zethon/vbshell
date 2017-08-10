# vbshell
A terminal shell plugin for vBulletin

![vbshell Screenshot](http://i.imgur.com/uEO1UFv.png "vbShell Screenshot")

## Introduction

This plugin for vBulletin allows users to navigate the board through a command line interface in the browser. This idea is based off of an actual app I write and maintain that does the same thing (see [https://github.com/lulzapps/Owl](https://github.com/lulzapps/Owl)).

## Navigation

The shell maintains a state of the current forum and thread. 

`whereami` - This will print out the name of the current forum and thread, as well as preview text of the last viewed post.

However, the name of the board and the current forum are displayed in the prompt. For example:

    [ AMB:General Topics ]$

This shows that the user is logged into a message board named "AMB" and is currently browsing the "General Topics" forum. If ther user has just started the shell then no current forum is set and the prompt will only show the message board's name.

### Forums

#### Listing Forums

`lf` - This will return a list of subforums in the current forum. The list will look similar to:

    [ AMB ]$ lf
    [ 1 ] General Topics
    [ 2 ] News and Sports
    [ 3 ] Star Wars

This shows three subforums. Once this list is returned, the user can then navigate into a specific subforum.

#### Selecting a Forum
`cf <index>` - Navigates into the forum at the specified `index`. The `index` corresponds to the index in the list resulting from the `lf` command.

    [ AMB ]$ cf 2
    [ AMB:News and Sports]$

Once in the selected forum, the prompt will change automatically. 

**Shortcut:** The `cf` can be omitted in the command following `lf`. Thus in the example above, simply entering `2` would have been the same as entering `cf 2`.

### Threads

#### Listing Threads

`lt [<page-number>]` `[<per-page>]` - Lists the threads in the current forum. The default for `<page-number>` is 1 and the default for `<per-page>` is 10. 

Examples: <br/>

`lt` - displays the first 10 threads in the current forum<br/>
`lt 2` - displays the second page of threads in the current forum (10 threads per page)<br/>
`lt 1 20` - displays the first 20 threads in the current forum<br/>
    
#### Selecting a Thread

`ct <index>` - Selects the thread at the given index.

**Shortcut:** The `ct` can be omitted in the command following `lt`. 

