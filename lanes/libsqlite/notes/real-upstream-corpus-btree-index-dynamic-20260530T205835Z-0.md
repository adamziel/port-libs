# real-upstream-corpus-btree-index-dynamic-20260530T205835Z-0

Base accepted HEAD: `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`.

Added a non-overlapping real upstream B-tree/index expression corpus slice from
SQLite upstream `test/indexexpr1.test` sections `indexexpr1-300` through
`indexexpr1-410`. The new dynamic cases cover expression-index DDL guardrails:
non-deterministic functions, date/time `now`, subqueries, expression terms in
UNIQUE/PRIMARY KEY/FOREIGN KEY declarations, unique expression-index integrity,
and duplicate-row rejection through `t3abc`.

Focused growth:

- Added 1,000 dynamic focused TestRunner PASS cases.
- Added 1 invalid-count guard PASS case.
- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExprDynamicTest.php`
- Result: `1 test files, 34275 assertions, 0 failures`.

Dependency closure: no new support component is needed. The slice reuses the
existing `SQLiteBTreeIndexDynamicCorpusPlan` corpus helper and TestRunner
harness.

Non-overlap: this does not repeat the existing expression-index lookup/order
dynamic cases, B-tree page move/root collapse/overflow freelist coverage, or
mapped denominator bookkeeping. It adds distinct upstream DDL/error and unique
expression-index duplicate behavior from `indexexpr1.test`.
