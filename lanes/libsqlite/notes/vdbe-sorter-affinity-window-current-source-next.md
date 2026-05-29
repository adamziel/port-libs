# VDBE sorter affinity window current-source next

Status: focused PHP behavior growth for `vdbe-sorter-affinity-window-current-source-next`.

This slice adds `SQLiteVdbeSorterAffinityWindowCurrentSourceNextPlan`, a bounded current-source composition of the existing VDBE sorter yield cursor and window aggregate cursor. It sorts current and next row sources with SQLite affinity, collation, descending, and NULL-placement rules, feeds those sorted rows into window frame recalculation, and reports inserted/deleted/moved sorter rows plus peer/frame changes caused by next-source mutations.

WordPress smoke: `wordpress-vdbe-sorter-affinity-window-current-source-next.php` covers copied `wp_options` rows where `autoload`, numeric-looking priority values, and RTRIM option-name ordering determine the sorter stream before a plugin import changes peer membership and window frame sums.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeSorterAffinityWindowCurrentSourceNextTest.php`
- Result: `1 test files, 61 assertions, 0 failures` with 61 PASS lines.
- `php lanes/libsqlite/examples/wordpress-vdbe-sorter-affinity-window-current-source-next.php --self-test`
- Result: `wordpress-vdbe-sorter-affinity-window-current-source-next self-test passed`

Expected dashboard movement: `phpPass +61` from `64226` to `64287`; `benchmarkDenominator.mapped` unchanged at `606 / 1589` because this is current-source PHP behavior over already mapped VDBE sorter/window surfaces, not a newly hydrated upstream inventory row.

Non-overlap: this avoids accepted VDBE affinity/collation sorter next108, DISTINCT sorter current-source next106/116, standalone window frame/exclude/filter current-source next, compound recursive affinity window batch141, expression ORDER BY, JSON table, WAL/pager, B-tree, VFS, schema, and suite-runner clusters. The new surface is specifically the handoff from an affinity/collation sorter stream into next-source window frame recalculation.

Dependency closure: no new support component is needed. The slice reuses lane-local `SQLiteVdbeAffinityCollationSorterSourcePlan`, `SQLiteVdbeSortCompare`, `SQLiteVdbeSorterYieldCursor`, and `SQLiteVdbeWindowAggregateCursor`.
