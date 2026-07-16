# Real Upstream Window Dynamic ROWS

Status: ported a focused real upstream SQLite window batch from the hydrated corpus into PHP behavior tests.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`
- Scenario ids: `window2.test` `2.1` through `2.11`, `2.13` through `2.29`, plus `3.1`, `3.3`, and `3.4`.

Behavior:

- `SQLiteVdbeWindowAggregateCursor` now accepts explicit frame start and end boundary sides while preserving the existing constructor defaults.
- Dynamic upstream frames such as `ROWS BETWEEN 3 PRECEDING AND 1 PRECEDING`, `ROWS BETWEEN 1 FOLLOWING AND 3 FOLLOWING`, `ROWS BETWEEN 1 FOLLOWING AND UNBOUNDED FOLLOWING`, and empty frames now produce SQLite-shaped sums and row sets.
- Empty dynamic frames now return no frame rows instead of the PHP `range()` reverse sequence.

Focused assertion count:

- New focused test: `33` PASS lines, `560` assertions.
- Shared VDBE window family check: `17` files, `1991` assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVdbeWindowAggregateCursor.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowDynamicRowsTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowDynamicRowsTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindow*Test.php lanes/libsqlite/tests/SQLiteSqlAggregateWindowOrderCurrentNext72Test.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowDynamicRowsTest.php`

Dependency closure: no new support component is needed. This reuses the native PHP VDBE window aggregate cursor, sorter, numeric aggregate, and text aggregate helpers.

Non-overlap: this uses real upstream `window2.test` dynamic frame cases and avoids accepted current-source row-value/window wrappers, compound recursive window slices, JSON table windows, grouped SELECT text, expression ORDER BY, WAL/VFS, B-tree, PRAGMA, trigger/FK, and suite-evidence metadata work.
