# real-upstream-corpus-select-core-dynamic-20260531T050054Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260531T050054Z-0`
Base accepted HEAD: `7d59ee97325649cafd2449deb321f30571bf474f`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test`
- Ported section: `selectC-1.1` through `selectC-1.14.1`
- Behavior cluster: SELECT result aliases and expression aliases resolved in `WHERE`, `GROUP BY`, `HAVING`, and `ORDER BY`, including unary-plus alias predicates and `upper()` ordered expression aliases.

## Patch

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T050054ZTest.php`.
- The test ports the named upstream `selectC.test` alias-resolution scenarios and adds dynamic variants over multiple expression-alias targets.
- The cases exercise `SQLiteSelectSql` projection alias resolution, `b||c` expression reuse, DISTINCT filtering, grouped HAVING alias dispatch, unary alias predicates, and descending alias order.

## Non-overlap

This does not repeat accepted SELECT JOIN text, GROUP BY/HAVING text, subquery, expression `ORDER BY`, select2 scalar-function WHERE, select5 aggregate/null, selectD parenthesized JOIN, JSON table SELECT source, or compound SELECT flattening coverage. It owns the `selectC.test` alias-resolution cluster for this session.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T050054ZTest.php`
  - `1 test files, 1065 assertions, 0 failures`
  - `133` PASS lines

## Dependency closure

No new support component is needed. The slice reuses existing lane-local `SQLiteSelectSql` support for expression projection, aliases, DISTINCT, GROUP BY, HAVING, ORDER BY, unary plus, concatenation, and `upper()`.

## Follow-up

Remaining SELECT corpus work should choose a different upstream section, such as `selectB` compound-subquery flattening or later `selectC` view/compound rows, without duplicating this alias-resolution batch.
