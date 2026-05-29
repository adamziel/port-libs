# Release Runner Upstream Gap Map Current Next49

## Scope

Adds `SQLiteUpstreamSuiteEvidence::releaseRunnerUpstreamGapMap()` for lane-local release-runner evidence. The helper maps countable current accepted bounded-runner artifacts to explicit next focused runner targets without launching a runner or counting release/all parity.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerUpstreamGapMapTest.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS current next49 maps current release artifact to ready focused next targets
PASS current next49 marks targets already covered by current focused artifacts
PASS current next49 blocks release target without release tier baseline
PASS current next49 blocks non hydrated next target scripts
PASS current next49 blocks duplicate broad runner snapshot
PASS current next49 blocks stale or failed current artifacts globally
PASS current next49 blocks php pass admission without focused output
PASS current next49 reports partial script coverage for mixed target
PASS current next49 validates required inputs

1 test files, 79 assertions, 0 failures
```

## Dashboard Delta

- `phpPass`: `17920 -> 17999` (`+79` verified focused PASS assertions)
- `phpFail`: remains `0`
- `benchmarkDenominator.mapped`: unchanged; this is runner gap-map evidence, not a fresh upstream inventory unit.

## Non-Overlap

This avoids accepted next38 release failure-ledger admission, next37 upstream gap proof, next34 denominator audit, focused-runner artifact admission, and accepted SQL/JSON/WAL/B-tree/VFS behavior clusters. It only classifies next focused runner targets as ready, covered, blocked, or partial based on bounded artifact records, active-runner state, and focused PHP admission.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded runner artifact parsing, duplicate-runner gates, and local TestRunner output parsing.
