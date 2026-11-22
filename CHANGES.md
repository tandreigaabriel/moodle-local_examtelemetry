# Changes

## Unreleased

- Report, attempt page: the button that saves the review status and notes was labelled "1"; it now reads "Save status and notes".

## 1.0.1 (2026-11-22)

- Commercial licence per site (paid once), purchased through Moodle Marketplace. Licence page for site administrators (Site administration → Plugins → Local plugins → Exam Telemetry → Manage licence) with the plan and the support end date; the licence is re-checked weekly by a scheduled task and a network error never disables an activated licence.
- Without a valid licence the collector is not loaded on quiz pages, `collect.php` answers HTTP 403 and the report shows a licence notice instead. Existing data is kept.
- Privacy API: the licence service is declared as an external location (site host name and licence key).
- PHPUnit: `license_test` (activation, trial expiry, domain binding, revocation, transport failure, incomplete response).

## 1.0.0 (2026-11-22)

First Moodle Marketplace release.

- All report labels, filters, CSV/table headers and classifier reasons come from language strings.
- Privacy API: the Apache timing table and the Apache log are declared; users in a course context can be listed and deleted (`core_userlist_provider`); PHPUnit test for metadata, export and deletion.
- `collect.php`: page kind limited to the column length.
- Marked stable; supports Moodle 4.5 and 5.0.
- Coding standard (moodle-extra), PHPDoc and Moodle Plugin CI checks pass; GitHub Actions CI and Marketplace release workflow added.

## 0.2.0-beta (2026-07-28)

- Attempt detail timeline, CSV export, incident acknowledgement and notes.
- Failed resource, HTTP request, CSP and external-integration events; Safari-compatible event-loop lag detector.
- Rate limiting, event deduplication, batched collection.
- Incremental Apache `%D` importer with checkpoints; responsibility areas.

## 0.1.0-alpha

- Initial collection, scheduled classification, administrator report, thresholds and retention.
