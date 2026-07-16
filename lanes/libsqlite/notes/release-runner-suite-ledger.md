# Release Runner Suite Ledger Current Next35

Adds `SQLiteUpstreamSuiteEvidence::releaseRunnerSuiteLedger()` as a bounded current/next suite-ledger admission record. It composes existing bounded-runner artifact hydration, current/next countability, focused PHP PASS admission, and active broad-runner gates into one decision record without claiming broad release parity from focused artifacts.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteLedgerTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS current next35 suite ledger classifies count movement one-to-two
PASS current next35 suite ledger classifies count movement one-to-three
PASS current next35 suite ledger classifies count movement two-to-four
PASS current next35 suite ledger classifies count movement three-to-five
PASS current next35 suite ledger classifies count movement five-to-six
PASS current next35 suite ledger classifies count movement release-audit-extension
PASS current next35 suite ledger classifies count movement focused-next-artifact
PASS current next35 suite ledger classifies count movement next-head-candidate
PASS current next35 suite ledger classifies count movement large-current-next
PASS current next35 suite ledger classifies count movement wide-current-next
PASS current next35 suite ledger classifies count movement single-current-many-next
PASS current next35 suite ledger classifies count movement many-current-one-new
PASS current next35 suite ledger classifies count movement two-new-release
PASS current next35 suite ledger classifies count movement three-new-release
PASS current next35 suite ledger classifies count movement four-new-release
PASS current next35 suite ledger classifies count movement six-new-release
PASS current next35 suite ledger classifies count movement ten-new-release
PASS current next35 suite ledger classifies count movement preserve-two
PASS current next35 suite ledger classifies count movement preserve-five
PASS current next35 suite ledger classifies count movement preserve-audit
PASS current next35 suite ledger classifies count movement preserve-focused
PASS current next35 suite ledger classifies count movement preserve-next-head
PASS current next35 suite ledger classifies count movement preserve-large
PASS current next35 suite ledger classifies count movement preserve-one
PASS current next35 suite ledger blocks invalid evidence missing-current
PASS current next35 suite ledger blocks invalid evidence missing-next
PASS current next35 suite ledger blocks invalid evidence regressed-next
PASS current next35 suite ledger blocks invalid evidence wrong-manifest
PASS current next35 suite ledger blocks invalid evidence failed-next
PASS current next35 suite ledger blocks invalid evidence missing-next-log
PASS current next35 suite ledger blocks invalid evidence active-runner
PASS current next35 suite ledger blocks invalid evidence php-failure
PASS current next35 suite ledger blocks invalid evidence php-unfocused
PASS current next35 suite ledger generated countable case 01
PASS current next35 suite ledger generated countable case 02
PASS current next35 suite ledger generated countable case 03
PASS current next35 suite ledger generated countable case 04
PASS current next35 suite ledger generated countable case 05
PASS current next35 suite ledger generated countable case 06
PASS current next35 suite ledger generated countable case 07
PASS current next35 suite ledger generated countable case 08
PASS current next35 suite ledger generated countable case 09
PASS current next35 suite ledger generated countable case 10
PASS current next35 suite ledger generated countable case 11
PASS current next35 suite ledger generated countable case 12
PASS current next35 suite ledger generated countable case 13
PASS current next35 suite ledger generated countable case 14
PASS current next35 suite ledger generated countable case 15
PASS current next35 suite ledger generated countable case 16
PASS current next35 suite ledger generated countable case 17
PASS current next35 suite ledger generated countable case 18
PASS current next35 suite ledger generated countable case 19
PASS current next35 suite ledger generated countable case 20
PASS current next35 suite ledger generated countable case 21

1 test files, 447 assertions, 0 failures
```

Focused PASS-line delta: `+54`, moving lane-local `phpPass` from `12271` to `12325`.

## Non-Overlap

This avoids accepted release-runner parity ledger, current/next count record, guarded preflight, artifact hydration, accepted-HEAD provenance, and release-blocker closure wrappers by composing them into a next35 decision record with focused PHP PASS admission and active-runner suppression. It does not touch SQL, JSON table, WAL, B-tree, VFS writer/lock/sync, Unicode GLOB, rollback-journal, or Application runtime behavior clusters.

## Dependency Closure

No new support component is needed. The helper reuses existing lane-local bounded-runner artifact parsing, current/next countability, active-runner, and focused TestRunner admission gates.

## Next Gate

Use the record when a next candidate has runner artifacts to compare against current accepted artifacts. Publish only `next35-suite-ledger-countable` movements, preserve `next35-suite-ledger-preserved` as no count movement, and repair blocker IDs before counting stale, failed, missing-log, regressed, active-runner, or unfocused PHP evidence.
