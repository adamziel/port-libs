# real-upstream-corpus-select-core-dynamic-20260530T180657Z-0

Base accepted HEAD: `70cbf38e6a31c3f41f86a2057096cb0006d09cf6`.

Added `SQLiteRealUpstreamSelectCoreDynamicSelect7Select8Test.php` with 113 focused TestRunner cases and 1156 assertions.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select7.test`: `select7-1.1`, `select7-7.2`, `select7-7.5`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select8.test`: `select8-1.1` through `select8-1.3`.

Covered behavior:

- Three-way `INTERSECT` with `LIKE` filters.
- Grouped `CASE` numeric categories and `typeof()` projection over real values.
- `SELECT DISTINCT artist, sum(timesplayed) ... GROUP BY lower(artist)` with `LIMIT`/`OFFSET`, including upstream fixed cases and dynamic expanded-table windows.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicSelect7Select8Test.php`
- Result: `1 test files, 1156 assertions, 0 failures`.

Non-overlap:

- This follow-up uses `select7.test` and `select8.test` sections and does not repeat the already accepted select1-select6 dynamic projection, predicate, grouping, compound, join, JSON-table, or expression `ORDER BY` batches.

Dependency closure:

- No new support component is needed. The tests exercise existing native `SQLiteSelectSql` SELECT text execution.

Mapped denominator:

- No `UPSTREAM_TEST_MANIFEST.json` denominator rows changed in this slice.
