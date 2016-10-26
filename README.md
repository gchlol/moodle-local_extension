[![Build Status](https://travis-ci.org/central-queensland-uni/moodle-local_extension.svg?branch=master)](https://travis-ci.org/central-queensland-uni/moodle-local_extension)

moodle-local_extension
======================
 
What is this
------------

This is a Moodle plugin that has hooks into activity modules to facilitate a dialog 
between a user and configured roles for requesting a submission extension to the module.

Installation
------------

1. Install the plugin the same as any standard moodle plugin either via the
Moodle plugin directory, or you can use git to clone it into your source:

    `git clone git@github.com:central-queensland-uni/moodle-local_extension.git local/extension`

    Or install via the Moodle plugin directory:
    https://moodle.org/plugins/local_extension

1. Then run the Moodle upgrade.

    If you have issues please log them in github here:
    https://github.com/central-queensland-uni/moodle-local_extension/issues

1. Initally please review the two main configurable pages.

    `Dashboard ► Site administration ► Plugins ► Local Plugins ► Activity extensions ►`
    - `General settings`
    - `Manage Adapter Triggers`

    The plugin will not appear to be active until there is one rule configured 
    in the settings page `Manage Adapter Triggers`.

Initial configuration
---------------------

### General settings ###

When a user requests an extension it searches the calendar for suitable activities. 

- searchback: The default number of days to search backwards in the calendar.
- searchforward: The default number of days to search forwards in the calendar.

Sometimes you want to make a request in the future. A dropdown select is available 
on the request page to extend the search length.

- searchbackwardmaxweeks: The number of weeks allowed to search backwards.
- searchforwardmaxweeks: The number of weeks allowed to search forward.

During debugging this option will prevent email notifications from the plugin.

- emaildisable: Enable to prevent email notifications.

When a user makes a request, the email will be said to be sent from this name.

- supportusername: The name that extension requests email are from.

When making a request you may wish to provide a banner / policy message to the user.

- extensionpolicyrequest: This policy will be displayed at the top of the page.
- extensionpolicystatus: This policy will be displayed at the top of the page.

Allow the user to make a request in the following contexts.

- systemcontext: Allow multiple activity modules to be selected when making a request in the system context. 
This spans across the site and allows making a single request that has activities from multiple courses.
- coursecontext: Allow multiple activity modules to be selected when making a request in the course context.
- modulecontext: Allow individual requests to be made in the module context. 
If this is disabled, no requests can be made individually.

Limit the length of an initial request.

- extensionlimit: Restricts the length that a student can initially request an extension for.

Enforce the requirement for providing supporting documentation when creating a request.

- requireattachment: Enabling this setting will enforce that a user attaches supporting documentation.

### Manage Adapter Triggers ###

- Rule name
- Priority [1-10]
- Only activate if [rule name] has triggered
- And the requested length is [any/le/ge] [x days] long
- And the request is [any/le/ge] [x days] old
- Then set all roles equal to [role] to [Approve/Subscribe/Force Approve] this request
- And notify that role with [email notification template]
- And notify the requesting user with [email notification template]

