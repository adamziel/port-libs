# real-upstream-corpus-select-core-dynamic-20260531T040252Z-0

- Base accepted HEAD: `86b40e76030ee95766e1bca45c19abb4f5a3c27f`.
- Added focused PHP corpus file: `lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreYieldDynamicTest.php`.
- Upstream sources: `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test` and `/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test`.
- Ported scenario ranges:
  - `select1-1.4` through `select1-1.13`: SELECT column extraction, wildcard/repeated wildcard expansion, literal interleaving, cross-source output order, qualified cross-source columns, and scalar `min()`/`max()` projection.
  - `select2-1.1` and `select2-1.2`: nested SELECT re-entry style behavior over ordered DISTINCT outer rows and ordered inner rows.
  - `select2-4.1` through `select2-4.7`: cross-source WHERE expression behavior for scalar `min()`/`max()`, truthy and `NOT` predicates, and CASE predicates.
- Focused movement: `1429` TestRunner PASS cases and `7503` assertions.
- Non-overlap: this does not repeat accepted select8 LIMIT/OFFSET, grouped SELECT SQL text, expression ORDER BY, SELECT JOIN text, SELECT subquery text, JSON table SELECT sources, or existing SELECT limit/compound-collation known-red diagnostics.
- Dependency closure: no new support component is needed; this reuses the existing native `SQLiteSelectSql` executor and row-array table fixtures.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreYieldDynamicTest.php
=> 1 test files, 7503 assertions, 0 failures
```

Root harness: not run - isolated micro-slice.
