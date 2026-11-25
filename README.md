# Exam Telemetry for Moodle

`local_examtelemetry` is a privacy-minimised diagnostics plugin for Moodle quiz
attempts. It distinguishes browser-side freezes and JavaScript failures from
server/network waits using client performance telemetry and an explainable,
scheduled incident classifier.

## Current release

- Component: `local_examtelemetry`
- Plugin version: `2026112201`
- Release: `1.0.1`
- Moodle: 4.5 and 5.0
- PHP: 8.1+
- Licence: GNU GPL v3 or later (code); a site licence key is required to use the plugin — see below

## Commercial licence

Exam Telemetry is a **commercial plugin sold through Moodle Marketplace**. The code is GPL v3 or later;
using it on a site requires a licence key for that site.

- **Purchase:** one licence per Moodle site (domain), **paid once** (perpetual licence), bought on the
  plugin's Moodle Marketplace listing. The licence key is issued after the purchase and is bound to the
  site's domain. The licence includes updates and e-mail support for the period shown on the licence
  page (Site administration → Plugins → Local plugins → Exam Telemetry → Manage licence).
- **Without a valid key** the telemetry collector is not loaded on quiz pages, `collect.php` refuses
  data (HTTP 403) and the report page shows a licence notice. Existing events, incidents and timing
  rows are kept; teachers are asked to contact their site administrator.
- **What is sent to the licence service:** the site host name, the licence key and the product name, to
  `https://tandreig.com/api/license/activate` when the key is activated and to `…/renew` once a week by
  the scheduled task "Refresh the site licence". No telemetry, quiz or user data are sent; everything
  the plugin records stays on your Moodle site. A network error never disables an activated licence.

## What it records

- Separate finish-attempt, confirmation-dialog and confirmed-final-submit actions
- Batched, deduplicated and per-attempt rate-limited ingestion
- Long browser main-thread tasks
- JavaScript errors and unhandled Promise rejections
- Online/offline transitions
- Page visibility changes
- Navigation, response-wait, download and render timings
- Page-transition duration between attempt and summary
- Optional correlated Apache/PHP processing duration
- Failed scripts, stylesheets, media and privacy-safe HTTP request outcomes
- Content Security Policy blocks and a Safari-compatible event-loop lag detector
- Explicit external proctoring/integration health events

It does **not** record answers, DOM/page contents, keystrokes, screenshots,
passwords, cookies, session identifiers, or request bodies.

External integrations can report a controlled failure without exposing their
remote message or page content:

```javascript
window.dispatchEvent(new CustomEvent('examtelemetry:integration', {
    detail: {
        integration: 'exam_service',
        event: 'initialisation_failed',
        reason: 'domain_not_authorised'
    }
}));
```

Accepted failure events are `initialisation_failed`, `connection_failed`, and
`required_service_unavailable`. Values are cleaned and length-limited before
collection. The integration must emit this event from its own error handler;
Exam Telemetry does not scrape alerts or DOM content.

To avoid load, successful HTTP requests are not reported, routine events remain
batched, duplicate events are suppressed, each page is capped at 100 events,
and the endpoint applies a per-attempt rolling rate limit.

## Automatic classifications

- `external_integration_failure`
- `external_service_failure`
- `external_resource_failure`
- `security_policy_failure`
- `moodle_request_failure`
- `network_request_failure`
- `resource_load_failure`
- `browser_js_freeze`
- `javascript_error`
- `browser_render_delay`
- `network_interruption`
- `server_delay`
- `server_or_network_delay`
- `unexplained_transition_delay`
- `failed_final_submission`
- `normal`

Every classification includes a severity, confidence and plain-language reason.
The rules are deterministic and auditable. Incidents also identify the likely
responsible area: student device/browser, student network, Moodle/server,
external service, exam configuration, shared server/network, or unknown.

`normal` on a finished attempt is displayed as **Healthy**. A normal attempt
whose Moodle state is still `inprogress` is displayed as **Monitoring — no
incident detected so far**, because it is not yet a final conclusion.

The five-minute scheduled classifier is incremental and bounded. It prioritises
submission-state checks, processes only attempts with new events or Apache
timings, and defaults to a maximum of 200 attempts per run.

## Evidence sources and storage

The administrator report combines:

1. Browser events sent to `collect.php` and stored in
   `local_examtelemetry_event`.
2. Optional Apache `%D` timings read incrementally from the dedicated log and
   staged in `local_examtelemetry_apache`.

The classifier combines those sources into one current record per attempt in
`local_examtelemetry_inc`. It does not parse general Apache access logs.
Integration failures that are returned as application-level messages must be
emitted through the controlled, provider-neutral `examtelemetry:integration`
event shown above.

The default 30-day cleanup deletes only plugin diagnostic records. It does not
delete Moodle quiz attempts, answers, grades, quizzes, courses, or external
proctoring records. Apache log retention is controlled separately by
Apache/logrotate.

## Updating an existing installation

Deploy the complete plugin directory, then run:

```bash
cd /path/to/moodle
sudo -u <php-user> php admin/cli/upgrade.php --non-interactive
sudo -u <php-user> php admin/cli/purge_caches.php
```

After updating, confirm `amd/build/telemetry.min.js` starts with `define(`.
Never deploy a build containing `export const`; it can break Moodle's combined
`core/first` response and prevent navigation drawers from opening.

### Building the AMD JavaScript

Moodle production files under `amd/build` must use AMD `define(...)` format.
Minifying `amd/src/telemetry.js` directly as an ES module leaves an `export`
statement in the production bundle and can break Moodle's combined
`core/first` response. Build it by converting to AMD first:

```bash
npx rollup amd/src/telemetry.js --format amd --file amd/build/telemetry.min.js
npx terser amd/build/telemetry.min.js --compress --mangle --comments "/^!/" -o amd/build/telemetry.min.js
```

Before deployment, confirm that the result starts with `define(` and contains no
`export const`. Purge Moodle caches after replacing a production AMD file.

## Manual Apache `%D` timing setup

> **This step is manual and optional. The Moodle plugin cannot edit Apache's
> configuration, reload the web server, create its log file, or grant PHP
> permission to read it. A server administrator must complete these steps.**

Without this setup, browser telemetry and the dashboard continue to work, but a
long time to first byte can only be classified as a server **or** network delay.
Apache `%D` provides the duration measured inside the web server, allowing the
classifier to identify a Moodle/server delay with much higher confidence.

### 1. Add a focused Apache log

Add the following configuration to the Apache virtual host serving Moodle, or
to a dedicated Apache configuration file loaded by that virtual host:

```apache
LogFormat "%{%Y-%m-%dT%H:%M:%S%z}t real_ip=%{X-Forwarded-For}i request=\"%r\" status=%>s bytes=%b duration_us=%D referer=\"%{Referer}i\" user_agent=\"%{User-Agent}i\"" quiz_submission_timing

SetEnvIf Request_URI "^/mod/quiz/processattempt\.php" quiz_submission

CustomLog logs/quiz_submission_timing.log quiz_submission_timing env=quiz_submission
```

Common configuration locations are:

- Debian/Ubuntu: `/etc/apache2/sites-available/your-moodle-site.conf` or a file
  under `/etc/apache2/conf-available/`
- RHEL/Rocky/AlmaLinux: `/etc/httpd/conf.d/moodle.conf`
- Windows/Laragon: the Moodle virtual-host file under Laragon's Apache
  `conf/extra` directory

`CustomLog logs/...` is relative to Apache's server root. Typical resulting
paths are `/var/log/apache2/quiz_submission_timing.log` on Debian/Ubuntu,
`/var/log/httpd/quiz_submission_timing.log` on RHEL-family systems, or
`C:\laragon\bin\apache\<version>\logs\quiz_submission_timing.log` in Laragon.
Check your Apache `ServerRoot` rather than assuming the path.

The directives mean:

- `%D` records Apache request processing time in **microseconds**.
- `duration_us=60000000` represents 60 seconds.
- `SetEnvIf` limits the separate log to final quiz processing requests instead
  of logging every Moodle page and asset.
- The referrer is used to extract and correlate the Moodle attempt ID.
- No quiz answers or POST request bodies are written by this log format.

If Moodle is not behind a reverse proxy, `X-Forwarded-For` may be empty. It is
not required for timing correlation and can be removed from the format if your
privacy policy does not permit recording IP addresses. The user-agent can also
be removed from this Apache log; browser/OS information is already available
from the plugin telemetry.

### 2. Validate and reload Apache

Run the command appropriate for the server:

```bash
# Debian/Ubuntu
sudo apache2ctl configtest
sudo systemctl reload apache2

# RHEL/Rocky/AlmaLinux
sudo apachectl configtest
sudo systemctl reload httpd
```

On Windows/Laragon, use Apache's configuration test from the Laragon terminal,
then restart Apache through Laragon. Do not reload Apache until the configuration
test reports `Syntax OK`.

### 3. Generate and verify a timing line

Complete a test quiz attempt, then inspect the configured log:

```bash
sudo tail -n 20 /var/log/apache2/quiz_submission_timing.log
```

A usable line must contain both:

```text
duration_us=1433000
referer="...attempt=123..."
```

If `duration_us` exists but the referrer does not contain `attempt=...`, the
plugin cannot correlate the line. Check Apache's referrer output and the site's
`Referrer-Policy`. Do not weaken a deliberate security policy without completing
a security and privacy review.

### 4. Allow Moodle to read only this log

The operating-system account running PHP/Moodle must be able to read the timing
log. Depending on the installation, that account is commonly `www-data`,
`apache`, or the account used by PHP-FPM. Prefer a dedicated group or ACL that
grants read-only access to this file and its rotated successors. Do not make the
entire Apache log directory world-readable.

Confirm access using the actual PHP account, for example:

```bash
sudo -u www-data test -r /var/log/apache2/quiz_submission_timing.log
```

The command exits successfully when the file is readable. File permissions must
also continue to work after log rotation.

### 5. Configure Moodle

In Moodle, open:

**Site administration → Plugins → Local plugins → Exam telemetry**

Set **Apache quiz timing log** to the log's absolute filesystem path, for
example:

```text
/var/log/apache2/quiz_submission_timing.log
```

Do not enter a URL. Leave the setting empty if Apache logging is not configured
or PHP cannot read the file. The plugin reads incrementally from a saved byte
offset, handles normal log rotation, and consumes at most 1 MB or 5,000 complete
lines per five-minute classifier run.

### 6. Verify Moodle cron and correlation

The plugin task is scheduled every five minutes, but it runs only when the
server's main Moodle cron is working. Check:

**Site administration → Server → Tasks → Scheduled tasks**

Find **Classify exam telemetry incidents**, confirm its schedule is `*/5`, and
use Moodle's task logs or “Run now” during initial testing. After cron runs, the
attempt investigation page should show an **Apache processing** duration. A
blank value normally means that the path is wrong, PHP cannot read the file, or
the log line did not contain a correlatable attempt ID.

### 7. Rotate and protect the log

This log contains timestamps and may contain IP addresses, referrers, and user
agents, so include it in the privacy review and restrict access. Configure
rotation separately from Moodle. A Linux example is available in
[Apache submission timing](docs/APACHE_TIMING.md). The plugin's 30-day database
retention does not delete Apache log files; Apache/logrotate retention must be
configured independently.

### Complete procedure for Ubuntu/Debian

This concrete first-time procedure assumes Moodle is installed at
`/var/www/moodle`, Apache logs are under `/var/log/apache2`, and Moodle/PHP runs
as `www-data`. Change these values to match your server.

Verify the installation:

```bash
apache2ctl -v
ps aux | grep -E 'apache2|php-fpm' | grep -v grep
sudo find /var/www -name config.php -type f 2>/dev/null
```

After copying the plugin to `/var/www/moodle/local/examtelemetry`, install its
database upgrade and clear Moodle caches:

```bash
cd /var/www/moodle
sudo -u www-data php admin/cli/upgrade.php
sudo -u www-data php admin/cli/purge_caches.php
```

Create a protected timing log and confirm Moodle can read it:

```bash
sudo touch /var/log/apache2/quiz_submission_timing.log
sudo chown root:www-data /var/log/apache2/quiz_submission_timing.log
sudo chmod 0640 /var/log/apache2/quiz_submission_timing.log
sudo -u www-data test -r /var/log/apache2/quiz_submission_timing.log
echo $?
```

The final command must print `0`. Create the Apache configuration:

```bash
sudo nano /etc/apache2/conf-available/examtelemetry.conf
```

Paste:

```apache
LogFormat "%{%Y-%m-%dT%H:%M:%S%z}t real_ip=%{X-Forwarded-For}i request=\"%r\" status=%>s bytes=%b duration_us=%D referer=\"%{Referer}i\" user_agent=\"%{User-Agent}i\"" quiz_submission_timing

SetEnvIf Request_URI "^/mod/quiz/processattempt\.php" quiz_submission

CustomLog /var/log/apache2/quiz_submission_timing.log quiz_submission_timing env=quiz_submission
```

Enable it, validate Apache, and reload only after the test reports `Syntax OK`:

```bash
sudo a2enconf examtelemetry
sudo apache2ctl configtest
sudo systemctl reload apache2
sudo systemctl status apache2 --no-pager
```

Complete a test quiz submission and inspect the result:

```bash
sudo tail -n 20 /var/log/apache2/quiz_submission_timing.log
```

A correlatable record looks like:

```text
request="POST /mod/quiz/processattempt.php HTTP/1.1" status=303 duration_us=1433000 referer="https://moodle.example/mod/quiz/summary.php?attempt=123&cmid=45"
```

Configure Moodle's **Apache quiz timing log** setting as:

```text
/var/log/apache2/quiz_submission_timing.log
```

For the first test, run the classifier directly:

```bash
cd /var/www/moodle
sudo -u www-data php admin/cli/scheduled_task.php --execute='\local_examtelemetry\task\classify_incidents'
```

Open the attempt investigation page and confirm that **Apache processing** has
a value. If it is blank, check the absolute path, `www-data` read access,
`duration_us`, and whether the referrer contains `attempt=...`.

Confirm that the main Moodle cron exists:

```bash
sudo systemctl status cron --no-pager
sudo crontab -u www-data -l
```

A typical Moodle cron entry is:

```cron
* * * * * /usr/bin/php /var/www/moodle/admin/cli/cron.php >/dev/null 2>&1
```

If it is missing, run `sudo crontab -u www-data -e` and add that line. Moodle's
main cron runs each minute; the plugin's bounded classifier becomes eligible
every five minutes.

Create `/etc/logrotate.d/moodle-examtelemetry`:

```text
/var/log/apache2/quiz_submission_timing.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0640 root www-data
    sharedscripts
    postrotate
        /bin/systemctl reload apache2 >/dev/null 2>&1 || true
    endscript
}
```

Validate it without forcing rotation:

```bash
sudo logrotate --debug /etc/logrotate.d/moodle-examtelemetry
```

The `create 0640 root www-data` directive preserves Moodle's read access after
rotation.

For RHEL, Rocky Linux, or AlmaLinux, use:

- `/etc/httpd/conf.d/examtelemetry.conf` without `a2enconf`;
- `/var/log/httpd/quiz_submission_timing.log`;
- the `apache` account/group instead of `www-data`;
- `sudo apachectl configtest`;
- `sudo systemctl reload httpd`; and
- `crond` where the distribution uses that service name.

## Documentation

- [Installation and configuration](docs/INSTALL.md)
- [Architecture and classification](docs/ARCHITECTURE.md)
- [Staging test plan](docs/TEST_PLAN.md)
- [Privacy and data protection](docs/PRIVACY.md)
- [Apache submission timing](docs/APACHE_TIMING.md)
- [Roadmap](docs/ROADMAP.md)

## External requests

The AJAX collector remains a dedicated endpoint because `navigator.sendBeacon()`
on pagehide or visibility changes cannot use the normal external service response
flow without a sesskey in the URL. It checks login, sesskey and attempt ownership.

## Author and licence

Developed and maintained by Andrei Toma — TAG Digital Solutions Ltd (trading as TAG Web Design & Development), https://tandreig.com. © 2026 TAG Digital Solutions Ltd.
