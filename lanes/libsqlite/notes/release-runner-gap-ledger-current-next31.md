# SQLite release-runner gap ledger current-next31

## Scope

- Adds `SQLiteUpstreamSuiteEvidence::releaseRunnerGapLedgerCurrentNext31()` as a lane-local release/all-suite blocker ledger.
- Composes existing accepted-HEAD artifact provenance, next-HEAD artifact provenance, runner hydration, command manifest, duplicate-runner, wildcard expansion, and permutation-suite map gates.
- Does not launch upstream `testfixture`, does not count skipped preflights as release parity, and does not change mapped upstream denominator.

## Focused evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerGapLedgerCurrentNext31Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS current next31 preserves current artifact while next runner gaps remain open
PASS current next31 marks next artifact countable and suppresses duplicate launches
PASS current next31 reports ready launch only when current evidence and all gates are clear
PASS current next31 blocks duplicate active broad runners before next launch
PASS current next31 blocks empty head inputs and invalid job counts

1 test files, 58 assertions, 0 failures
```

## Status delta

- New focused PHP PASS lines: +5.
- `phpPass`: 10687 -> 10692.
- `benchmarkDenominator.mapped`: unchanged; this is a release-runner blocker ledger, not newly mapped upstream behavior.

## Non-overlap

Avoids accepted batch23/batch26 release-runner countability and upstream-map surfaces, guarded runner preflight, focused artifact admission, artifact-directory hydration, accepted-head upstream mapping, VFS/WAL/B-tree/JSON/SQL behavior clusters, and newer accepted VFS sync/apply, SELECT subquery, B-tree root collapse, and related storage/executor slices.

## Dependency closure

No new support component needed. The ledger reuses lane-local manifest evidence, bounded runner artifact parsers, hydration probes, command-manifest gates, wildcard/permutation inventory, and supplied process snapshots only.
