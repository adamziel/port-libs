# real-upstream-corpus-btree-index-dynamic-20260531T065645Z-0

Status: ready for integration.

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr2.test`.
- Upstream sections covered: `indexexpr2-5.0` through `indexexpr2-11.0`.
- Focus: expression-index OR-union rows, `CAST(b AS INTEGER/TEXT)` expression-index affinity, no-residue handling after `ABS()` integer overflow during index build, NULL partial-index truthiness wrappers, LEFT JOIN no-match loop preservation, correlated aggregate resolution through indexed `abs(b)`, stale collation flag stripping for indexed aggregate expressions, and generated-column expression-index aggregate resolution.
- Focused assertion growth: `1003` TestRunner PASS cases in `SQLiteRealUpstreamBtreeIndexExpr2LateRegressionDynamicTest.php`.

Non-overlap:

- Existing accepted `indexexpr2` coverage already owns sections `3.4.5`, `3.4.6`, and `4.110` through `4.130`; this batch starts at section `5.0` and runs through `11.0`.
- This avoids accepted `index2`, `index3`, `index4`, `index5`, `index6`, `index7`, `index8`, `index9`, `indexA`, `indexexpr1`, `indexexpr3`, autoindex, indexed-by, indexfault, B-tree page relocation/root-collapse/interior merge, overflow freeblock/freelist release, JSON table, WAL, VFS, SELECT expression `ORDER BY`, and source-neutral cleanup surfaces.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExpr2LateRegressionDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExpr2LateRegressionDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses lane-local B-tree/index expression-index regression, CAST affinity, OR-union, no-residue overflow, LEFT JOIN, correlated aggregate, collation, and generated-column corpus helpers.
