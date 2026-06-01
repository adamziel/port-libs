# Real Upstream Corpus: Window Functions Dynamic

Slice: `real-upstream-corpus-window-functions-dynamic-20260601T102626Z-0`

Accepted base: `9fdbbaf081786bb1d6389d15e519a76f8a24a31c`

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Ported scenario cluster:
  - `window1.test` `35.2` through `35.4`: `VALUES(...)` compound operators over `SELECT sum(x) OVER f ... WINDOW f AS (ORDER BY x)`.
  - `window1.test` `45.2`: `UNION ALL` preserving duplicate `sum(a) OVER()` partition totals.
  - `window1.test` `46.2` through `46.4`: scalar window subqueries used as truthy predicates and joined residual predicates.
  - `window1.test` `48.0` and `48.1`: nested scalar window subquery oracle coverage for whole-partition and grouped first-row semantics.
  - `window1.test` `60.1`: `EXISTS` over an ordered window subquery.

## Patch Summary

- Added `SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T102626ZTest.php`.
- Added 1,007 focused TestRunner PASS cases.
- Added 14,031 focused behavior assertions.
- Exercised direct `SQLiteSelectSql` execution for the supported upstream SQL shapes in sections 35, 45, 46, and 60, and used `SQLiteWindowFunction` frame aggregation as the oracle for the nested section 48 scalar-window behavior.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T102626ZTest.php`
  - Result: no syntax errors detected.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T102626ZTest.php`
  - Result: `1 test files, 14031 assertions, 0 failures`.
- `jq empty lanes/libsqlite/lane-status.json`
  - Result: passed.
- `git diff --check -- lanes/libsqlite`
  - Result: passed.

## Non-Overlap

This slice owns `window1.test` compound/scalar window-subquery behavior for sections `35`, `45`, `46`, `48`, and `60`. It avoids the accepted `window4`, `window5`, `window6`, `window9`, `windowpushd`, prior `window1` alias/order/count/range clusters, and the accepted JSON/VFS/WAL/B-tree/source-neutral surfaces.

## Dependency Closure

No new support component is needed. The patch reuses existing bounded native PHP components: `SQLiteSelectSql` for parser/executor coverage and `SQLiteWindowFunction` for upstream window-frame oracle coverage.
