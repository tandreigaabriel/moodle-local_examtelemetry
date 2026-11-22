# Architecture and automatic classification

## Event flow

1. Moodle injects the AMD module only on selected `attempt.php` and
   `summary.php` pages.
2. The browser observes performance events without reading answers or page
   contents.
3. Events are sent with `navigator.sendBeacon()` and a same-origin fallback.
4. `collect.php` validates the Moodle session, sesskey, attempt ownership and
   CMID before inserting a record.
5. A scheduled task aggregates evidence into one incident per attempt.
6. Administrators review incidents through a capability-protected report.
7. A daily cleanup task applies the configured retention period.

## Classification rules

Rules are applied in priority order:

1. Explicit required-integration failure → `external_integration_failure`
2. Content Security Policy block → `security_policy_failure`
3. Failed Moodle/external request → responsibility-specific request failure
4. Script, CSS, image or media load failure → responsibility-specific resource failure
5. Offline transition → `network_interruption`
6. Long task or event-loop lag over threshold → `browser_js_freeze`
7. JavaScript exception → `javascript_error`
8. Render duration over threshold → `browser_render_delay`
9. Apache duration over threshold → `server_delay`
10. Time to first byte over threshold → `server_or_network_delay`
11. Transition over threshold without stronger evidence →
    `unexplained_transition_delay`
12. Otherwise → `normal`

The browser Navigation Timing API cannot separate server execution from every
form of network delay. Apache `%D` logging is therefore recommended for
definitive server-duration evidence.

## Data model

`local_examtelemetry_event` stores individual, sanitised evidence records.

`local_examtelemetry_inc` stores one current automatic assessment per attempt.
Reclassification updates the same row while retaining administrator status.

## Security controls

- Authenticated Moodle session required
- Valid sesskey required
- Attempt must belong to the current user
- CMID must resolve to the attempt's quiz
- Event types are allow-listed
- Browser events are capped at 100 per page and routine reports are batched
- Request monitoring reports failures only, never successful request traffic
- URLs are reduced to hostname and path; query strings are discarded
- Text values are cleaned and truncated
- Reports require `local/examtelemetry:viewreports`
- No raw POST bodies or quiz answers are captured

## Known alpha limitations

- Classification is diagnostic evidence, not a disciplinary decision.
- Long Tasks API availability varies by browser.
- A browser killed before recovery may not transmit its last buffered event.
- Apache timing is correlated manually in this alpha release.
- Report strings and accessibility require a final production review.
