# Real upstream corpus select core dynamic literal count

- Slice: `real-upstream-corpus-select-core-dynamic-20260530T205811Z-0`
- Base accepted HEAD: `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`
- Ported scenarios: `selectH-5.1` result shape context and `selectH-5.2` `count(1234)` over a derived `SELECT DISTINCT ... UNION ALL ...` subquery.
- Behavior change: `SQLiteSelectSql` now treats aggregate `count(non-null-literal)` as `count(*)`, matching SQLite's row-count semantics for a non-NULL constant argument. `count(NULL)` maps to zero through the existing count-value summary.
- Focused PASS growth: `1001` distinct TestRunner PASS cases in `SQLiteRealUpstreamSelectCoreDynamicLiteralCountTest.php`.
- Focused assertions: `3003`.
- Non-overlap: avoids the rejected `selectH` omit-unused counter side-effect optimization cases and does not add metadata-only denominator rows. This slice exercises real SELECT executor behavior: derived compound source parsing, DISTINCT left arm, UNION ALL right arm, WHERE filtering, and aggregate literal-count projection.
- Dependency closure: no new support component needed; this reuses the lane-local SELECT SQL parser/executor, compound executor, derived-table source handling, and grouped aggregate summaries.
- Root harness: not run - isolated micro-slice.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteSelectSql.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicLiteralCountTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicLiteralCountTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicLiteralCountTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 3003 assertions, 0 failures
```
