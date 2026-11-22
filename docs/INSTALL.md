# Installation and configuration

## Stageprerequisites

1. Moodle 4.5 or later.
2. PHP 8.1 or later.
3. A current database backup.
4. Access to Moodle CLI and Apache logs.
5. A test quiz and test student.

## Install

Copy the directory to:

```text
/var/www/html/moodle/local/examtelemetry
```

Set ownership:

```bash
chown -R apache:apache /var/www/html/moodle/local/examtelemetry
```

Install through the CLI:

```bash
sudo -u apache /usr/bin/php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive
```

Build/purge caches:

```bash
sudo -u apache /usr/bin/php /var/www/html/moodle/admin/cli/purge_caches.php
```

## Configure

Activate the licence first: **Site administration → Plugins → Local plugins → Exam Telemetry → Manage
licence**, paste the key received with the Moodle Marketplace purchase and press *Activate licence*.
Until a licence is active the collector is not loaded and the report shows a licence notice.

Open:

```text
Site administration → Plugins → Local plugins → Exam telemetry
```

Recommended staging values:

| Setting | Value |
|---|---:|
| Enable telemetry | Yes |
| Enabled quiz course-module IDs | Test quiz CMID only |
| Long-task threshold | 500 ms |
| Delay threshold | 5000 ms |
| Retention | 30 days |

An empty CMID list enables collection for every quiz. Do not use an empty list
on production until staging testing and privacy review are complete.

## Report

Open:

```text
Site administration → Reports → Exam telemetry incidents
```

The scheduled classifier runs every five minutes. It can be run manually:

```bash
sudo -u apache /usr/bin/php /var/www/html/moodle/admin/cli/scheduled_task.php \
  --execute='\local_examtelemetry\task\classify_incidents'
```

## Disable safely

Turn off **Enable telemetry** in the plugin settings. Existing records remain
until the configured retention task deletes them.

## Uninstall

Use Moodle's standard plugin uninstall interface. Uninstalling removes both
plugin tables.

