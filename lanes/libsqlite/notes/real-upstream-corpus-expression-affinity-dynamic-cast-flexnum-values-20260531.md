# real-upstream-corpus-expression-affinity-dynamic-cast-flexnum-values-20260531

Base accepted HEAD: `96c3c12f0e388eba581b5758d55cd85f17d538ef`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`
- Upstream section: `cast-7.1` through `cast-7.8`, FLEXNUM/REAL storage-class preservation through `VALUES`, `UNION ALL`, subquery, CROSS JOIN, and view-like wrappers.

Patch summary:

- Fixed parenthesized `VALUES(...)` source recognition in `SQLiteSelectSql` so `FROM (VALUES(CAST(...)))` is parsed the same way as top-level `VALUES(...)`.
- Added an oracle-backed dynamic corpus with 180 REAL-producing literal pairs across 6 upstream `cast-7` query forms.
- Focused growth: 1,080 distinct TestRunner PASS cases / 3,245 assertions.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicCastFlexnumValuesTest.php`
- Result: `1 test files, 3245 assertions, 0 failures`.

Non-overlap:

- This slice does not repeat accepted real operator, real cast-prefix, real-IN, affinity2/types2, expr7 WHERE, expression ORDER BY, SELECT GROUP BY, or compound SELECT coverage.
- The behavior is specifically the upstream `cast.test` FLEXNUM storage-class path through parenthesized `VALUES` table sources and related SELECT wrappers.

Dependency closure:

- No new support component is needed. The test uses the existing bounded `SQLiteSelectSql` executor and local `sqlite3` only as an oracle for hydrated upstream SQLite behavior.
