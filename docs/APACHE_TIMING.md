# Focused Apache submission timing

Browser telemetry should be paired with a small Apache log containing only
`processattempt.php` requests.

Set **Site administration → Plugins → Local plugins → Exam telemetry → Apache
quiz timing log** to the absolute path of this file. The five-minute classifier
then correlates `duration_us` with the attempt ID in the referer. Leave the
setting empty where the web server log is not readable by Moodle.

The reader stores a byte offset and consumes at most 1 MB or 5,000 complete
lines per cron execution. It resets safely when the configured path changes or
the file is rotated to a smaller size. Correlated durations are retained in a
small staging table and removed by the normal retention task.

```apache
LogFormat "%{%Y-%m-%dT%H:%M:%S%z}t real_ip=%{X-Forwarded-For}i request=\"%r\" status=%>s bytes=%b duration_us=%D referer=\"%{Referer}i\" user_agent=\"%{User-Agent}i\"" quiz_submission_timing

SetEnvIf Request_URI "^/mod/quiz/processattempt\.php" quiz_submission

CustomLog logs/quiz_submission_timing.log quiz_submission_timing env=quiz_submission
```

Validate and reload:

```bash
apachectl configtest
systemctl reload httpd
```

`duration_us=60000000` means 60 seconds.

This log records about two requests per completed attempt and excludes
autosaves, page assets and answers.

Recommended rotation:

```text
/var/log/httpd/quiz_submission_timing.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    sharedscripts
    postrotate
        /bin/systemctl reload httpd >/dev/null 2>&1 || true
    endscript
}
```
