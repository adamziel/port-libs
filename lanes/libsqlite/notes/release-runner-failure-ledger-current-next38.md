### 2026-05-27 release runner failure ledger current-next38

Added a current/next release-runner failure ledger for failed guarded `all` /
`release` artifacts. The ledger compares the current accepted failed runner
state with the next accepted failed runner state, distinguishes preserved,
resolved, and new failure keys, requires accepted-head repository provenance,
keeps stale or manifest-mismatched artifacts blocked, and requires an exact
focused failed-script repro before failed release evidence can be counted as
blocker evidence.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerFailureLedgerCurrentNext38Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS current next38 preserves matching runtime release failure as blocker evidence
PASS current next38 reports resolved failure when next accepted head has no failed artifact
PASS current next38 reports expanded ledger for a new next release failure
PASS current next38 blocks stale next failure artifacts
PASS current next38 blocks failure evidence without passed focused repro
PASS current next38 blocks when focused repro does not cover next failed script

1 test files, 47 assertions, 0 failures
```

Status delta:

- `phpPass` moves from `13431` to `13478` for the 47 newly passing focused
  assertions in `SQLiteReleaseRunnerFailureLedgerCurrentNext38Test.php`.
- `benchmarkDenominator.mapped` is unchanged; this is runner/admission ledger
  behavior, not a newly mapped upstream inventory unit.

Non-overlap:

This avoids the accepted batch23/31 runner countability and release-runner gap
ledger surfaces by not reshaping artifact-directory admission, hydration
records, current/next count deltas, command manifests, or broad release
parity. The new surface is specifically failed-runner current/next ledgering
with preserved/resolved/new failure keys and focused-repro gating.

Dependency closure:

No new support component is needed. The helper composes lane-local parsed
bounded-runner artifacts and focused repro evidence only; it does not launch
upstream runners, inspect secrets, or mutate the hydrated upstream cache.
