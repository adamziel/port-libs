# SQLite suite denominator current-next66

## Scope

This slice adds a stricter current-next66 release-runner denominator admission
gate. It is intentionally suite/countability work only: it does not claim
release/all parity, does not launch a broad upstream runner, and does not move
mapped upstream coverage.

## Behavior

- Adds `SQLiteUpstreamSuiteEvidence::releaseRunnerSuiteDenominatorCurrentNext66()`.
- Requires accepted-head focused TestRunner PASS-line evidence through
  `focusedPhpPassCurrentHeadAdmission()`.
- Requires ready/preserved denominator rows to include command, artifact,
  evidence, and concrete `.test` script tokens.
- Blocks duplicate broad upstream runners from supplied process snapshots.
- Preserves mapped coverage and release/all parity until a fresh broad
  zero-error artifact exists.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteDenominatorCurrentNext66Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
76 PASS lines
1 test files, 1286 assertions, 0 failures
```

## Counter Delta

- `phpPass`: `24610 -> 24686` (`+76` verified PASS lines).
- `benchmarkDenominator.mapped`: unchanged at `463 / 1589`.

## Non-Overlap

Avoids batch64 suite-denominator current-next64 mapped-delta behavior,
release/all parity claims, accepted JSON/VFS/WAL/B-tree/SQL behavior clusters,
and stale mapped-coverage movement.

## Dependency Closure

No new support component is needed. The gate composes lane-local row records,
accepted-head focused TestRunner PASS output, and duplicate-runner process
snapshot parsing only.
