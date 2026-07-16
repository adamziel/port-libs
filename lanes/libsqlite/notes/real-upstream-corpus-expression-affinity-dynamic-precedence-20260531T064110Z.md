Real upstream corpus expression affinity dynamic precedence slice

- Session: port-dev-sqlite-yield-dyn-real-expr-20260531T064110Z
- Micro-slice: real-upstream-corpus-expression-affinity-dynamic-20260531T064110Z-0
- Accepted base: adb26e7f16ecd89937cf2d16ad3f15841131934b
- Upstream source truth: /home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test
- Upstream scenario range: e_expr-13.* comparison, BETWEEN, LIKE, AND, and OR precedence.
- Added PHP focused test: lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicPrecedence20260531Test.php
- Focused TestRunner movement: 3,585 PASS cases, 14,342 behavior assertions.
- Non-overlap: this covers e_expr-13 precedence over mixed NULL, integer, real, and text operands. It avoids accepted e_expr operator arithmetic/bitwise sections, cast/type dynamic real-expression matrices, SELECT expression ORDER BY, grouped SELECT text, subquery, Unicode GLOB, JSON table, VFS/WAL, and B-tree clusters.
- Dependency closure: no new support component is needed. The slice reuses the lane-local SQLiteSelectSql executor and the local sqlite3 binary only as an oracle for expected upstream behavior.
- Verification:
  - php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicPrecedence20260531Test.php
    - 1 test files, 14342 assertions, 0 failures
