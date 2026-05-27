# Release Runner Parity Ledger Current Next29

## Scope

Adds `SQLiteUpstreamSuiteEvidence::currentReleaseRunnerParityLedger()` to reconcile current accepted-HEAD runner artifacts, focused upstream artifacts, release/all countability, rerun decisions, and focused PHP PASS admission in one machine-readable record.

This is intentionally distinct from accepted artifact directory hydration, guarded runner preflight countability, focused runner artifact admission, and release-blocker closure wrappers. Focused artifacts remain countable only as focused upstream evidence and never close release/all parity.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerParityLedgerCurrentNext29Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS current next29 counts broad zero error artifact while admitting php pass delta
PASS current next29 keeps focused-only artifacts out of release parity
PASS current next29 blocks stale broad artifacts and exposes provenance blockers
PASS current next29 records exclusion closure without zero error parity
PASS current next29 blocks php pass admission for unfocused or failing output
PASS current next29 rerun decision remains blocked by active broad runner

1 test files, 96 assertions, 0 failures
```

## Status Delta

- `phpPass`: `10028 -> 10124` from the 96 focused PHP assertions above.
- `benchmarkDenominator.mapped`: unchanged; this slice adds current release-runner parity ledger behavior but does not claim a new upstream inventory unit.
- Root harness: not run; isolated micro-slice.

## Dependency Closure

No new support component is needed. The ledger composes existing lane-local runner artifact, provenance, release admission, rerun, and focused PHP TestRunner gates.

## Next Gate

Use this ledger when deciding whether a current accepted-HEAD broad release/all artifact is enough to close zero-error parity, when focused artifacts should remain focused-only, and when stale/mismatched artifacts or active runners require repair before another broad run.
