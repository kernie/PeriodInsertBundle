# PeriodInsertBundle

A plugin for Kimai that generates entries for a given time period.

## Installation

This plugin is compatible with Kimai version 2.26.0 or higher. Releases 1.0-1.2 are compatible with Kimai version 2.1.0 or higher.

Download and extract the bundle in `var/plugins/` (see [plugin docs](https://www.kimai.org/documentation/plugin-management.html)).

The file structure needs to look like this afterwards:

```bash
var/plugins/
├── PeriodInsertBundle
│   ├── PeriodInsertBundle.php
|   └ ... more files and directories follow here ... 
```

Then rebuild the cache:
```bash
bin/console kimai:reload --env=prod
```

## Permissions

This bundle comes with the following permission:

- `period_insert` - show the period insert screen to generate entries for a given time period

By default, it is assigned to each user with the role `ROLE_SUPER_ADMIN`.

Read how to assign these permissions to your user roles in the [permission documentation](https://www.kimai.org/documentation/permissions.html).

## Screenshots

![Alt text](/screenshot0.png?raw=true "Period Insert plugin in Default timetracking mode")
![Alt text](/screenshot1.png?raw=true "Period Insert plugin in Duration and Time-clock timetracking modes")

## Acknowledgements

This plugin is a migration of a bundle created by the software company MR Software GmbH. The plugin now supports Kimai 2.0!
