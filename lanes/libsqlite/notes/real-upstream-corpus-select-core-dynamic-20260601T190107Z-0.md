# real-upstream-corpus-select-core-dynamic-20260601T190107Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test`.
- Ported scenarios: `selectA-2.7`, `selectA-2.8`, and `selectA-2.9`.
- Focused coverage: `SQLiteRealUpstreamSelectAUnionAllDeclaredCollationDynamic20260601T190107ZTest.php` adds 1,003 distinct TestRunner PASS cases and 18,024 focused assertions for `UNION ALL` final `ORDER BY c...` inheriting declared `NOCASE` collation from the left-arm `t1.c` result expression.
- Behavior: this covers the previously documented `selectA-2.7` through `selectA-2.9` follow-up without a production change. The current `SQLiteSelectSql` / `SQLiteSelectExpression` compound ORDER BY path already propagates row metadata collations for these cases, and the new tests lock the upstream behavior.
- Expected dashboard classification: PASS-line growth only on integration. `selectA.test` is already mapped in the upstream inventory, so mapped denominator coverage remains unchanged. `lane-status.json` is intentionally not edited from this isolated handoff because it records accepted supervisor state.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectAUnionAllDeclaredCollationDynamic20260601T190107ZTest.php` passed with no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAUnionAllDeclaredCollationDynamic20260601T190107ZTest.php` passed: `1 test files, 18024 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectACompoundOrderDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAReversedUnionCollationDynamic20260601T133455ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAUnionAllDeclaredCollationDynamic20260601T190107ZTest.php` passed: `3 test files, 43856 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:

- No new support component is needed. This reuses lane-local `SQLiteSelectSql`, `SQLiteSelectExpression`, compound SELECT execution, `SQLiteBlobValue`, row metadata collations, and the hydrated upstream SQLite `selectA.test` source truth.

Non-overlap:

- This owns only left-arm `UNION ALL` declared-collation ordering in `selectA-2.7` through `selectA-2.9`.
- It avoids accepted `selectA-2.37` through `selectA-2.39` reversed-arm `UNION` collation coverage, duplicate-source `selectA-2.72` through `selectA-2.92`, `select9` set-op slices, `selectB`, `selectD`, JSON table, WAL, B-tree, VFS, source-neutral cleanup, and metadata-only admission rows.
