# JSON table generated path rowid cost current-source next224

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next224`

## Implementation

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext224()`.
- Extends the accepted generated-path rowid `xCurrent` profile with a yield guard that compares the observed `xCurrent` fingerprint and active rowid against the current source before delivering a resumed row.
- Records delivered rowids, restart rowids, active projected columns, alias values, source/fingerprint match state, guard opcode, guard cost class, and replan reasons.
- Prevents copied `wp_options` JSON diagnostics from reusing a stale generated-path rowid cursor when the next source changes the current-source fingerprint or active rowid.

## Focused verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext224Test.php`
  - `1 test files, 60 assertions, 0 failures`
  - 60 `PASS` lines

## Application smoke

- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next224.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next224 self-test passed`

## Non-overlap

This avoids accepted JSON table cursor/source wiring, hidden and visible constraint extraction, generated-path rowid cost/cache/yield/xNext/xCurrent layers through next212, VFS/WAL/B-tree/storage surfaces, and suite-runner evidence work. The new behavior is the resume guard above `xCurrent`: it delivers only when the observed fingerprint and active rowid still match, and otherwise forces a restart/reprepare path.

## Dependency closure

No new support component is needed. The slice reuses native PHP JSON table row generation, generated-path rowid range planning, rowid alias projection, `xCurrent` current-source fingerprints, and projection validation.
