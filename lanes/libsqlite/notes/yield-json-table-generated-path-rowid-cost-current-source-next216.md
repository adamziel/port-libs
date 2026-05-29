# JSON Table Generated Path Rowid Cost Current Source Next216

## Behavior

- Added `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext216()`.
- Extends the existing generated-path rowid range/XCurrent chain with a current-source `xNext` profile.
- Models whether a pinned `json_tree` rowid range can advance to the next rowid, reaches EOF for a point range, or must reprepare/reseek when the next source or rowid range is stale.

## WordPress Smoke

- Added `examples/wordpress-json-table-generated-path-rowid-cost-current-source-next216.php`.
- Scenario: copied `wp_options` JSON rule previews can advance a generated-path rowid cursor through a pinned current-source range while changed next-source rows force reprepare instead of stale rowid reuse.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext216Test.php`
  - `1 test files, 50 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext209Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext212Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext216Test.php`
  - `3 test files, 159 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next216.php --self-test`
  - `wordpress-json-table-generated-path-rowid-cost-current-source-next216 self-test passed`

## Dashboard Delta

- Focused PHP PASS/assertion growth: `+50` from the new next216 test file.
- Expected `phpPass` movement after clean integration: `105283 -> 105333`.
- Mapped upstream denominator unchanged; no new manifest row is claimed.

## Non-Overlap

- Avoids accepted JSON table SELECT source/cursor, hidden/visible constraint pushdown, generated-path rowid range next209, and XCurrent next212 behavior.
- This slice only adds the next cursor-advance step after a current row has been produced from an already-admitted generated-path rowid range.

## Dependency Closure

- No new support component is needed.
- Reuses existing native JSON table generated-path rowid range, alias projection/order, and XCurrent metadata.
