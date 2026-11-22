# Roadmap

## 0.1 alpha — current build

- Selected-quiz collection
- Long-task, error, connectivity and navigation telemetry
- Automatic scheduled classification
- Administrator incident report
- Configurable thresholds and retention
- Moodle Privacy API support

## 0.2 beta

- Attempt detail timeline
- CSV export
- Dashboard charts by browser, OS, quiz and classification
- Incident acknowledgement and notes
- PHPUnit and Behat coverage
- Accessibility and language-string completion
- Rate limiting and event deduplication

## 1.0 production

- Automated Apache timing importer with rotation-safe checkpoints
- Correlation of Apache, browser and Moodle event evidence
- Configurable alerts for clustered incidents
- Scheduled email summaries
- Per-exam health score
- Browser/version anomaly detection
- Multi-node web-tier support
- Documented upgrade and rollback guarantees

## Automatic clustered detection

The production target will raise an alert when multiple attempts on the same
quiz show the same classification within a configurable time window. This
separates isolated device problems from shared platform incidents.

