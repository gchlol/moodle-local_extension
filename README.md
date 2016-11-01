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

### Capabilities ###

- local/extension:viewallrequests

This allows the user to view all requests in the system and act upon them.

- local/extension:modifyrequeststatus

This allows the user to modify the request length regardless of their access level.

### Manage Adapter Triggers ###

- Rule name

This is used to identify the rule.

- Priority [1-10]

When rules are at the root level or on child branches in the tree, this is the order of execution for the rules.

- Only activate if [rule name] has triggered

This allows rules to be nested in a parent/child relationship. A rule will not be considered for execution unless its parent has been triggered.

- And the requested length is [any/lt/ge] [x days] long

This is the length of the request in days.

- And the request is [any/lt/ge] [x days] old

This is how long the request has existed in the system. Useful for providing update notifications after an amount of days.

- Then set all roles equal to [role] to [Approve/Subscribe/Force Approve] this request

Approve: Grants the roles specified the ability to approve the status of the request or modify the legnth.

Subscribe: Grants the roles specified to read and comment on the request only.

Force Approve: This option is used when nested rules have been setup. Each time a child rule is triggered, it will downgrade the previous roles from Approval to Subscribe. When this setting is enabled those roles will not have their access level downgraded.

- And notify that role with [email notification template]

Send the roles that have been subscribed to this request with the specified template. A notification will not be sent if this is empty.

- And notify the requesting user with [email notification template]

Send the user that has made the request with the specified template. A notification will not be sent if this is empty.

#### Example Trigger Configuration ####

Rule 1
- Rule Name: Notify Teachers
- Priority: 1
- Only activate if [N/A] has triggered
- And the requested length is [lt] [5 days] long
- And the request is [any] [0 days] old
- Then set all roles equal to [Teacher] to [Approve] this request
- And notify that role with [email notification template]
- And notify the requesting user with [email notification template]

Rule 2
- Rule Name: Notify Managers
- Priority: 1
- Only activate if [Notify Teachers] has triggered
- And the requested length is [ge] [10 days] long
- And the request is [any] [0 days] old
- Then set all roles equal to [Manager] to [Approve] this request
- And notify that role with [email notification template]
- And notify the requesting user with [empty email notification template]

An overview of these two rules.

When a request is made that is less than 5 days in length, Teachers will be subscribed with approval access and notified. Rule1.

When a request is made that is greater or equal to 10 days in length, initially nothing will happen as the parent rule does not trigger. Rule2.

If the request was less than 5 days in length but one of the approval roles modified the length to be greater than 10 days, then Rule2 will trigger.

If Rule2 triggers, then the existing Teacher roles will have their access downgraded to being a subscriber only. Manager roles will be added with approval access.
