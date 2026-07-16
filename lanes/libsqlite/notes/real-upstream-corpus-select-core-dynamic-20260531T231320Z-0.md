# real-upstream-corpus-select-core-dynamic-20260531T231320Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- `e_select-1.5`: `EVIDENCE-OF: R-22776-52830`
- `e_select-1.6`: `EVIDENCE-OF: R-54046-48600`
- `e_select-1.7`: `EVIDENCE-OF: R-57047-10461`

Behavior ported:

- `JOIN ... USING (...)` filters rows by equality across the named columns.
- The right-hand copy of each `USING` comparison column is omitted from `SELECT *`.
- `ON`-equivalent joins still expose both comparison columns.
- `USING` equality uses the left input column's collation metadata, so `NOCASE` on the left matches text that `BINARY` on the left rejects.
- Internal column affinity and collation metadata is preserved through join planning but stripped from wildcard result rows.

Focused movement:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamESelectUsingCollationDynamic20260531T231320ZTest.php`.
- New focused file contributes +1002 TestRunner PASS cases and 33011 assertions.
- Red-first command failed before source fixes with `1 test files, 8011 assertions, 1000 failures`.
- Fixed command passed with `1 test files, 33011 assertions, 0 failures`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectUsingCollationDynamic20260531T231320ZTest.php` -> `1 test files, 33011 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicNaturalLeftJoin20260531T092250ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamESelect2JoinCollationDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectGroupByCollationDynamic20260531T151801ZTest.php` -> `3 test files, 98023 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php && php -l lanes/libsqlite/src/SQLiteSelectProjection.php && php -l lanes/libsqlite/src/SQLiteSelectResult.php && php -l lanes/libsqlite/tests/SQLiteRealUpstreamESelectUsingCollationDynamic20260531T231320ZTest.php` -> all changed PHP files report no syntax errors.

Non-overlap:

- Avoids prior e_select `1.8` through `1.12` natural/left join dynamic coverage.
- Avoids explicit `ON` collation tests in `SQLiteRealUpstreamESelect2JoinCollationDynamicTest.php`.
- Avoids group/order/compound/JSON/storage behavior and does not add metadata-only admission rows.

Dependency closure:

- No new support component is needed.
- Reuses the existing SELECT SQL executor, projection/result helpers, row metadata arrays, and `SQLiteAffinityComparison`.
- Root harness was not run for this isolated micro-slice.
