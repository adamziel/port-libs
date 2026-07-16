# Upstream Runner Hydration Gate Next17

Slice: `yield-sqlite-release-upstream-runner-gap-current-next17`

## Behavior

Added `SQLiteUpstreamSuiteEvidence::upstreamRunnerHydrationGate()` to make the
accepted-HEAD SQLite runner hydration blocker machine-readable before any broad
`all`, `release`, `make test`, or `mptest` launch is attempted.

The gate classifies:

- hydrated SQLite source cache and `test/` directory
- configured build directory
- executable `testfixture`
- `test/testrunner.tcl`
- `test/permutations.test`
- build `Makefile`
- upstream `mptest/` directory
- runnable focused, release-all, permutation, make-test, and mptest commands

This removes the previous ambiguity between "runner not launched because broad
suite is intentionally gated" and "runner not launchable because the hydrated
cache/build inputs are absent".

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamRunnerHydrationGateTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
32 PASS lines
1 test files, 75 assertions, 0 failures
```

The 32 PASS-line delta updates `lane-status.json` `phpPass` from `5718` to
`5750`. `benchmarkDenominator.mapped` is unchanged at `456`; this is a runner
admission blocker classifier, not a newly mapped upstream behavior unit.

## Non-Overlap

This avoids accepted focused-runner artifact admission, release artifact
directory scanning, pgrep self-probe filtering, permutation-suite command
mapping, wildcard expansion, and ordinary behavior clusters. It does not launch
or count a broad runner; it only reports whether the existing SQLite hydrated
source/build/testfixture inputs make those runner commands admissible.

## Dependency Closure

No new support component is needed. The gate uses lane-local manifest evidence
and filesystem readiness for the existing SQLite checkout/build tree only.
