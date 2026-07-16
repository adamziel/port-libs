# SQLite Suite Release Countability Current Source Release Countability

## Scope

This slice adds `SQLiteUpstreamSuiteEvidence::upstreamRunnerReleaseCountability()` for current-source release-countability release/all countability admission.

The gate is intentionally narrower than accepted release-gap release-gap burnup and full-suite-countability full-suite countability:

- only `release` and `all` tier rows can count;
- artifact paths must stay under `lanes/libsqlite/`;
- runner commands must include `testfixture`, `testrunner.tcl`, and `--stop-on-error`;
- source provenance must match launcher base, dashboard source, status source, implementation source, and next source heads supplied by the caller;
- rows must be zero-error and must not claim release parity;
- duplicate broad runner snapshots block admission;
- focused PHP `TestRunner` output must admit the exact PASS-line movement.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS admits current-source release-countability release countability rows with focused phpPass evidence
PASS blocks current-source release-countability release countability for stale provenance and duplicate runners

1 test files, 1115 assertions, 0 failures
```

New focused PASS-line delta: `+2`.

## Non-Overlap

This does not repeat accepted release-gap release-gap burnup, full-suite-countability full-suite countability, current-source next114 focused release admission, or ordinary behavior slices. It is a lane-local admission/countability blocker removal for release-countability release/all rows only.

## Dependency Closure

No new support component is needed. The slice composes existing lane-local row metadata, guarded runner command strings, duplicate-runner snapshots, source-head provenance, and focused PHP `TestRunner` output.
