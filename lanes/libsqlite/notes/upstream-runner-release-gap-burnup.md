# Upstream Runner Release Gap Burnup Release Gap

## Slice

This lane removes a release/all countability blocker for current-source to
next-source runner evidence. `SQLiteUpstreamSuiteEvidence` now has
`releaseGapBurnupRecord()`, which burns down the release
gap only for paired zero-error next-source artifacts whose `Testset` is
`all` or `release`.

Focused next-source artifacts remain focused evidence. Current-source release
artifacts are preserved, and stale, missing-log, manifest-mismatched, or failed
artifacts keep the burnup blocked instead of inflating release/all progress.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php`
  - `1 test files, 1068 assertions, 0 failures`
  - New PASS lines:
    - `admits only next-source release artifacts for current-source release-gap release gap burnup`
    - `blocks current-source release-gap release gap burnup when artifacts are focused stale or missing logs`

## Status Delta

- Focused PASS-line delta: `+2`
- `lane-status.json` `phpPass`: `45302 -> 45304`
- Mapped upstream coverage: unchanged; this is a runner/countability blocker
  removal, not a new upstream inventory mapping.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted upstream-runner release admission, focused-runner
artifact admission, accepted-head provenance, or current/next source directory
countability. It adds the narrower release-gap release-gap burnup rule that
classifies `all`/`release` artifacts separately from focused next-source
artifacts before reducing the release/all gap.

## Dependency Closure

No new support component is needed. The record composes existing bounded
runner audit/log parsing, current/next source head gates, SQLite manifest UUID
checks, and `Testset` classification.
