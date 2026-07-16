# Release Countability Gap Current Next21

## Scope

Added a lane-local focused phpPass admission helper for release/countability handoffs. The helper admits a dashboard `phpPass` delta only when the captured output is a focused `tools/run-tests.php` run for exactly one `lanes/libsqlite/tests/*Test.php` file, has a positive assertion count, reports zero failures, and includes a non-overlap note.

This avoids overlapping accepted behavior clusters such as WAL byte truncation, VFS writers/locks/sync, rollback-journal apply/commit, B-tree page moves/root collapse/overflow freelist release, JSON table cursor/source/hidden/visible constraints, SELECT SQL text/JOIN/GROUP/subquery/comma LIMIT/expression ORDER BY, Unicode GLOB ranges, and batch19 executor/storage corpus work.

## Evidence

Command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseCountabilityGapCurrentNext21Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS admits focused phpPass delta only from a zero-failure lane test run
PASS blocks phpPass movement when focused output has failures
PASS blocks phpPass movement for root harness output without focused marker
PASS blocks phpPass movement for multi-file focused output
PASS blocks phpPass movement when no assertions are present
PASS rejects non lane-local focused paths before counting output
PASS rejects missing non-overlap notes before counting output
PASS rejects negative current phpPass values before counting output

1 test files, 35 assertions, 0 failures
```

## Status Delta

- `phpPass`: `7262 -> 7297` (`+35`), exactly matching the focused assertion delta above.
- `phpFail`: unchanged at `0`.
- `benchmarkDenominator.mapped`: unchanged; this slice does not map a new upstream inventory unit.

## Dependency Closure

No new support component is needed. The slice reuses lane-local manifest loading and local TestRunner output only.

## Next Gate

Use this admission helper for future suite/release dashboard updates so raw assertion totals, root-harness output, failed focused runs, and multi-file aggregate runs cannot move `phpPass` without focused zero-failure lane evidence.
