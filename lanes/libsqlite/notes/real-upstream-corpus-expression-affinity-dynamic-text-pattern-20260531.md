## real-upstream-corpus-expression-affinity-dynamic-text-pattern-20260531

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`.
- Owned sections: `expr-3.*` text comparison family, `expr-5.*` ASCII `LIKE` / `NOT LIKE` / `ESCAPE` family, and `expr-6.*` ASCII `GLOB` / `NOT GLOB` family.
- Added focused PHP coverage: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTextPattern20260531Test.php`.
- Verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTextPattern20260531Test.php` passed with `1 test files, 9057 assertions, 0 failures` and `2263` PASS lines.
- Non-overlap: avoids accepted REAL arithmetic, overflow, precedence, `expr7` WHERE, collation, DQS, Unicode GLOB range, JSON, WAL, B-tree, VFS, and planner clusters.
- Dependency closure: no new support component needed; this reuses the existing `SQLiteSelectSql`, scalar `quote`/`typeof`/`coalesce`, and LIKE/GLOB expression dispatch.
