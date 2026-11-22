# Privacy and data protection

## Purpose

The plugin diagnoses technical disruption during online assessments. Telemetry
must not be used as the sole basis for academic misconduct, disciplinary, or
grading decisions.

## Personal data

The plugin stores Moodle user, course, quiz, course-module and attempt IDs,
browser user agent, timestamps, performance durations, sanitised JavaScript
error text, failed resource/request hostnames and paths without query strings,
controlled integration reason codes, and an automatic diagnostic classification.

## Excluded data

The design excludes answers, question content, DOM content, typed text,
screenshots, camera or microphone data, cookies, passwords and Moodle sesskeys.
Request and response bodies are never inspected. External failure URLs have
their query strings and fragments removed before collection.

## Minimisation

- Enable only for selected exam CMIDs.
- Use the shortest operationally useful retention period.
- Limit report access to authorised IT/assessment managers.
- Treat user-agent and error strings as personal data.
- Keep the 100-event page cap and per-attempt endpoint rate limit enabled.

## Retention

Default retention is 30 days. A scheduled cleanup task removes expired events
and incidents daily.

## Moodle Privacy API

The plugin declares stored metadata and supports user export and deletion in
course contexts.

## Operational recommendation

Before production deployment, the site operator should document the lawful purpose, update
its relevant privacy information if required, and agree who may review
incidents.
