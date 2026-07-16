# JSON table generated path rowid cost current-source next234

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next234`

Status: focused PHP behavior growth for generated-path `json_tree()` rowid xNext resume admission.

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext234()`.
- Extends the accepted next224 xCurrent yield guard with an xNext resume layer that records observed and actual yield-guard fingerprints, delivered rowid tapes, remaining rowids, restart rowids, advanced rowids, pending rowids, opcode, cost class, transition reasons, and reader policy.
- Prevents copied `wp_options` JSON diagnostics from advancing a generated-path rowid cursor with xNext after the pinned current-source yield guard or delivered rowid tape becomes stale.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext234Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext234Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next234.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next234.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext234Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 57 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next234.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next234 self-test passed`

Dashboard delta:

- `phpPass`: `115305` to `115362` (`+57` focused PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior coverage, not a newly mapped upstream inventory row.

Non-overlap:

This avoids accepted JSON table cursor/source wiring, hidden/visible constraints, host joins, parser-level JSON table SELECT sources, rowid alias order/limit/xCurrent/yield guard layers through next224, and the rejected older JSON-table rowid/cost handoffs. The new behavior is specifically the xNext resume checkpoint above the accepted xCurrent yield guard: it advances only when the yield fingerprint and delivered rowid tape still match the pinned current source, otherwise it restarts/reprepares before consuming next-source rows.

Dependency closure:

No new support component is needed. The slice reuses native PHP JSON table generated-path rowid planning, rowid alias projection, xCurrent yield guards, current-source fingerprints, and transition/replan reporting.
