# Changes

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
