# full-run-parity-json-table-rowid-cost-dynamic-20260531T215122Z-0

Micro-slice: `full-run-parity-json-table-rowid-cost-dynamic-20260531T215122Z-0`

Base accepted HEAD: `9ef60eb910c3006c081a236c1ec05f4d0e7024c4`

## Behavior

Fixed the pre-existing JSON table generated-path rowid-cost full-run blocker
recorded in `lane-status.json`. The assigned shard
`SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext761776Test.php`
previously failed with 32 rowid-cost/reuse-policy failures. The shared issue was
that `currentSourceGeneratedPathRowidCostSelection()` composed the canonical
selection profile through the older x-current yield guard and xRowid pipeline,
which could carry stale next-source admission state into stable current-source
reuse checks.

The canonical selector now builds current and next cost profiles directly from
the rowid-cost profile helper, preserves stable unsuffixed observable keys, and
treats source identity changes as a reprepare boundary even when the next source
can independently perform a rowid point lookup. Numbered aliases route through
the existing dynamic variant path so generated nextNN tests preserve their
numbered keys, dependencies, policies, and reasons.

No new support component is needed.

## Focused Evidence

Before fix:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext761776Test.php`
- Result: `1 test files, 129 assertions, 32 failures`

After fix:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- Result: no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostSelectionTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostSelectionReplayTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceSelectionTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentCostSelectionTest.php`
- Result: `4 test files, 99 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext761776Test.php`
- Result: `1 test files, 129 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext745760Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext761776Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext777792Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext238Test.php`
- Result: `4 test files, 445 assertions, 0 failures`

## Exclusions

The full `SQLiteJsonTable*Test.php` domain remains broader than this
micro-slice and is not claimed green. A diagnostic family run reported
`304 test files, 19602 assertions, 1155 failures`, starting in unrelated
generated-hidden/error-boundary suites. A narrower
`SQLiteJsonTableGeneratedPathRowidCost*Test.php` diagnostic run reported
`199 test files, 14236 assertions, 272 failures`, concentrated in older offset,
resume, xColumn-cache, and bestindex current-source shards outside the assigned
761-776 blocker. Those remain follow-up work.

## Non-Overlap

This avoids accepted JSON table cursor/source/hidden/visible constraint work and
does not alter JSON parsing, JSONB malformed handling, SELECT source wiring, or
upstream-runner admission. The patch is limited to generated-path rowid cost
selection/reuse policy in `SQLiteJsonTablePlan`.
