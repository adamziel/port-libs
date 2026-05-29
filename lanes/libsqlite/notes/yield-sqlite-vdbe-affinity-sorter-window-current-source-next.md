# SQLite VDBE Affinity Sorter Window Current-Source Next

## Behavior

- Added `SQLiteVdbeAffinitySorterWindowCurrentSourceNextPlan` for a current/next source snapshot that runs copied `wp_options`-style rows through VDBE sorter affinity/collation and window aggregate current/next summaries.
- The slice covers SQLite-style NUMERIC/TEXT affinity over partition/order keys, NOCASE/RTRIM/BINARY collations, NULLS LAST ordering, FILTER handling, non-advancing next-row exposure, inserted/deleted/moved row detection, and frame aggregate values after source drift.
- This intentionally avoids accepted batch142 compound-window, accepted VDBE affinity/collation sorter next108, and accepted standalone VDBE window cursor surfaces by adding only the combined current-source/next-source comparison wrapper and focused next coverage.

## WordPress Smoke

- `examples/wordpress-vdbe-affinity-sorter-window-current-source-next.php` models copied `wp_options` rows before and after import replacement: one deleted option, one inserted option, priority drift, affinity/collation ordering, and FILTERed byte summaries.

## Verification

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeAffinitySorterWindowCurrentSourceNextTest.php`
- Example smoke: `php lanes/libsqlite/examples/wordpress-vdbe-affinity-sorter-window-current-source-next.php`
- PHP lint: `php -l` for the new source, test, and example files.
- Whitespace: `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The implementation reuses existing native PHP sorter, affinity comparison, collation, and window cursor primitives.
