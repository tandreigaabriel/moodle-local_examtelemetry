# Roadmap

## 1.0 — current release

- Selected-quiz collection with batching, deduplication and per-attempt rate limiting
- Long-task, JavaScript error, connectivity, visibility, navigation and transition telemetry
- Failed resource, HTTP request, CSP and external-integration health events
- Scheduled, incremental, explainable incident classification with responsibility areas
- Optional Apache `%D` timing correlation with rotation-safe checkpoints
- Administrator report: filters, attempt timeline, incident acknowledgement and notes, CSV export
- Configurable thresholds, batch size and retention; scheduled cleanup
- Moodle Privacy API (metadata, export, deletion, user lists) and PHPUnit coverage
- All user-facing text from language strings

## Planned

- Configurable alerts for clustered incidents (same classification on one quiz in a time window)
- Scheduled email summaries and a per-exam health score
- Dashboard charts by browser, operating system, quiz and classification
- Browser/version anomaly detection
- Multi-node web-tier support for the Apache importer
- Behat coverage of the report
