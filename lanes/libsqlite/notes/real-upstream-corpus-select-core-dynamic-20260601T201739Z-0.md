# real-upstream-corpus-select-core-dynamic-20260601T201739Z-0

Added `SQLiteRealUpstreamSelectADeclaredCollationIntersectExceptDynamic20260601T201739ZTest.php` and fixed the predicate metadata guard in `SQLiteSelectPredicate`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test`

Ported upstream scenarios:

- `selectA-2.42`: `INTERSECT` keeps the high `b >= 'd'` rows and orders by `a,b,c`, including TEXT before BLOB for column `a`.
- `selectA-2.43`: reversed-arm `INTERSECT` keeps the same high rows and ordering.
- `selectA-2.44`: `EXCEPT` removes low `b < 'd'` rows and keeps the high rows ordered by `a,b,c`.
- `selectA-2.59`: `EXCEPT` keeps low rows and orders by `c, a DESC`, inheriting declared `NOCASE` from `t1.c`.
- `selectA-2.64`: reversed-arm `INTERSECT` keeps low rows and orders by inherited declared `NOCASE` `c`.

Behavior fix:

- Red-first focused run initially failed with `1001` failures because `SQLiteSelectPredicate::assertRow()` accepted hidden affinity metadata but rejected hidden collation metadata arrays as predicate values.
- The fix treats `__sqlite_column_collations` and qualified `*.__sqlite_column_collations` the same way as affinity metadata during predicate row validation, allowing declared collation metadata to coexist with `WHERE` filters.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectADeclaredCollationIntersectExceptDynamic20260601T201739ZTest.php`
- Result after fix: `1 test files, 29036 assertions, 0 failures`
- Distinct TestRunner PASS cases: `1003`
- Expected `phpPass` movement: `6247535 -> 6248538`

Additional verification:

- `php -l lanes/libsqlite/src/SQLiteSelectPredicate.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectADeclaredCollationIntersectExceptDynamic20260601T201739ZTest.php`: no syntax errors.
- `php -r '$json = file_get_contents("lanes/libsqlite/lane-status.json"); json_decode($json, true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid JSON\n";'`: valid JSON.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectADeclaredCollationIntersectExceptDynamic20260601T201739ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAIntersectExceptDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAUnionAllDeclaredCollationDynamic20260601T190107ZTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: `4 test files, 65593 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`: passed.
- Root harness: not run - isolated micro-slice.

Non-overlap:

- This fills the exact `selectA.test` declared-collation `INTERSECT`/`EXCEPT` remainder (`selectA-2.42`, `selectA-2.43`, `selectA-2.44`, `selectA-2.59`, `selectA-2.64`) that was explicitly excluded by the 2026-05-30 selectA handoff.
- It avoids accepted `selectA` `UNION ALL` declared/reversed collation work, the existing `selectA-2.41`, `selectA-2.45` through `selectA-2.58`, and `selectA-2.60` through `selectA-2.63` coverage, `select9` set operations, SELECT JOIN/GROUP/ORDER text, JSON table, WAL, B-tree, VFS, and source-neutral cleanup.

Dependency closure:

- No new support component is needed. The batch reuses `SQLiteSelectSql`, `SQLiteSelectPredicate`, `SQLiteBlobValue`, row metadata collations, and hydrated upstream SQLite `selectA.test` source truth.
- Mapped denominator remains unchanged because `selectA.test` is already mapped in the upstream manifest.
