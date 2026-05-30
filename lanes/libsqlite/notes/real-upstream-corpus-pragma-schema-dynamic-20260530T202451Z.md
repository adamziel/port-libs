real-upstream-corpus-pragma-schema-dynamic-20260530T202451Z

Base accepted HEAD: a5d711ea245dda1130ca2ff1ba1b791f9a863c2b

Scope:
- Extended `SQLiteRealUpstreamPragmaSchemaDynamicTest.php` with a non-overlapping dynamic matrix from hydrated upstream SQLite `test/pragma4.test`.
- Upstream sections covered: `pragma4.test` 4.2 table-valued `pragma_table_info`, 4.3 `pragma_index_info`, 4.4 `pragma_index_list`, 4.5 `pragma_foreign_key_list`, 4.6 detach/drop invalidation, 6.0 `pragma_table_list`, and 7.3 table-valued PRAGMA join inputs.
- Added 80 generic schema variants over main/temp/attached catalogs with distinct table, index, FK, default-value, detach, table-list, join-input, and cursor-snapshot assertions.

Countability:
- Added 640 focused TestRunner PASS cases.
- Added 6400 behavior assertions in the focused file.
- Focused verification now reports 1 test file / 6982 assertions / 0 failures.
- Expected `phpPass` movement: 573146 -> 573786.
- Mapped denominator movement: none.

Non-overlap:
- Reuses the existing real upstream pragma/schema dynamic test file and extends unclaimed variant coverage rather than adding suite metadata rows.
- Does not add fake upstream script ids, WordPress-specific APIs, compatibility wrappers, or dashboard/root publication edits.

Dependency closure:
- No new support component is needed. The batch reuses `SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`, `SQLitePragmaRowCursor`, and `SQLiteSchemaRecord`.

Verification:
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTest.php`
- `php -l lanes/libsqlite/lane-status.json` is not applicable because it is JSON.
- `git diff --check -- lanes/libsqlite`

Root harness:
- Not run - isolated micro-slice.
