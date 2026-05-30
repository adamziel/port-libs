# JSON table generated path rowid cost current-source next227

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next227`

## Implementation

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext227()`.
- Extends the accepted next224 generated-path rowid yield guard with a current-source guard that compares the observed source generation and source fingerprint before delivering a resumed rowid.
- Records observed/actual source generation, observed/actual source fingerprint, generation/fingerprint match state, delivered rowids, restart rowids, active projected columns, alias values, guard opcode, guard cost class, and replan reasons.
- Prevents copied `wp_options` JSON diagnostics from reusing a yielded `json_tree` row after the generated-path current source has changed.

## Focused verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext227Test.php`
  - `1 test files, 65 assertions, 0 failures`
  - 65 `PASS` lines

## Application smoke

- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next227.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next227 self-test passed`

## Non-overlap

This avoids accepted JSON table cursor/source wiring, hidden and visible constraint extraction, generated-path rowid cost/cache/yield/xNext/xCurrent layers through next224, and the active next115/next116 JSON table constraint/cost/order surfaces. The new behavior is a source-generation/fingerprint admission layer above the accepted yield guard.

## Dependency closure

No new support component is needed. The slice reuses native PHP JSON table row generation, generated-path rowid range planning, rowid alias projection, next224 yield guards, and current-source fingerprints.
