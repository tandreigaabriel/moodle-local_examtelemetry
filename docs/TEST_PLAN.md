# Staging validation plan

## 1. Installation

- Run the Moodle upgrade.
- Confirm both database tables exist.
- Confirm both scheduled tasks appear.
- Confirm the report requires the correct capability.

## 2. Normal submission

- Start a test attempt.
- Answer several questions.
- Wait for autosave.
- Click **Finish attempt**.
- Click **Submit all and finish**.
- Run the classifier.
- Expect a `normal` incident and a finished Moodle attempt.

## 3. Browser freeze simulation

On a stage-only test page, use browser developer tools to execute a busy loop
for longer than the configured delay threshold, then submit.

In Chrome DevTools **Console**, run:

```javascript
setTimeout(() => {
    const end = performance.now() + 7000;
    while (performance.now() < end) {
        // Deliberately block this browser tab for seven seconds.
    }
}, 1000);
```

Wait until the tab responds again before completing the attempt.

Expected:

- `long_task` event
- `browser_js_freeze`
- longest-task duration close to the simulated delay

Do not perform this test during a real exam.

## 4. JavaScript exception

Introduce a controlled stage-only rejected Promise in the console.

```javascript
setTimeout(() => {
    throw new Error('Exam telemetry staging test error');
}, 1000);
```

Expected:

- `unhandled_rejection`
- `javascript_error`

## 5. Network interruption

- Start an attempt.
- Temporarily disable the test device network.
- Re-enable it.
- Complete submission.

Expected:

- `offline`
- `online`
- `network_interruption`

## 6. Slow response

Use a controlled stage2 delay or network throttling. Compare:

- browser `serverwaitms`
- Apache `%D`
- transition duration

Expected:

- high server wait with no long task
- `server_or_network_delay`

## 7. Failed final submission

This test must be performed only on Stage2 with a disposable attempt:

1. Reach the quiz summary page normally.
2. Open Chrome DevTools and select **Network**.
3. Open **More tools → Network request blocking**.
4. Add `*processattempt.php*` and enable request blocking.
5. Click **Submit all and finish** and confirm it.
6. Confirm the final request is blocked and Moodle still shows the attempt as
   in progress.
7. Leave browser telemetry endpoints unblocked.
8. Wait at least two minutes, then run the classifier task.

Expected:

- `final_submission_confirmed` exists in the client timeline;
- Moodle attempt state remains `inprogress`;
- classification is `failed_final_submission`;
- severity is `critical`.

Disable and remove the request-blocking rule immediately after the test. Resume
and submit the disposable attempt normally so it does not remain abandoned.

## 8. Privacy and access

- Confirm students cannot access the report.
- Export a test student's privacy data.
- Delete the test student's privacy data.
- Confirm no answer text, DOM content, cookie or sesskey is stored.

## 9. Load

Simulate expected concurrency only on stage website. Confirm:

- collection endpoint remains responsive;
- database growth matches the estimate;
- no PHP or database errors;
- scheduled classification completes within five minutes;
- cleanup removes expired test records.

## Production acceptance

Production deployment requires all tests to pass, a privacy review, a rollback
plan, a maintenance window, and initial enablement for one low-risk quiz CMID.
