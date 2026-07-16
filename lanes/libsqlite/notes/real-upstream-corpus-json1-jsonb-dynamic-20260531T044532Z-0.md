# real-upstream-corpus-json1-jsonb-dynamic-20260531T044532Z-0

Lane: `libsqlite`

Micro-slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T044532Z-0`

Base accepted HEAD: `ea98db4ecded4356aee592549997cc44a35fab5b`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test`
- Ported sections: `json103-100`, `json103-102`, `json103-120`, `json103-202`, and `json103-220`.

## Behavior

This slice adds parser-level SELECT SQL coverage for JSON aggregate execution, not another standalone aggregate-helper batch:

- `json_group_array(a)` grouped by `b`, with range predicates and BLOB exclusion matching the upstream `typeof(a)!='blob'` intent.
- `json_group_object(c,a)` grouped by `b`, including the shared executor fix that now treats object aggregates as aggregate summary expressions instead of unsupported scalar calls.
- `jsonb_group_array(a)` and `jsonb_group_object(c,a)` implicit aggregate SELECT execution, with JSONB canonical text and decode parity checks.
- Dynamic generic `app_events` rowsets preserve the upstream `t1(a,b,c)` shape while avoiding WordPress-shaped API names.

Focused movement:

- New focused file: `SQLiteRealUpstreamJson103SelectSqlDynamicTest.php`
- New distinct focused PASS cases: `1241`
- New focused behavior assertions in the file: `11448`

## Non-Overlap

This does not repeat existing `json103` helper-level aggregate/window files. The new coverage routes the upstream aggregate behavior through `SQLiteSelectSql`, `SQLiteSelectSql::aggregateSummaryColumn()`, and `SQLiteGroupedAggregate::applyJsonAggregate()` so the SQL parser/executor path now handles JSON object aggregates as well as array aggregates.

It also avoids accepted JSON table cursor/source/hidden/visible constraints, JSON502 escaped-label behavior, JSON102/105 path mutation, JSON106 invariants, JSON108 pretty, JSON109 array insert, and jsonb01 removal/inspection batches.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson103SelectSqlDynamicTest.php`
  - Result: `1 test files, 11448 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson103AggregateDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson103AggregateDynamicExpansionTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson103WindowMatrixDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson103SelectSqlDynamicTest.php lanes/libsqlite/tests/SQLiteJsonAggregateOrderDistinctCurrentSourceNext86Test.php lanes/libsqlite/tests/SQLiteJsonAggregateDistinctFilterOrderCurrentSourceNext94Test.php lanes/libsqlite/tests/SQLiteJsonAggregateExpressionOrderCurrentSourceNext99Test.php`
  - Result: `7 test files, 32231 assertions, 0 failures`

## Dependency Closure

No new support component is needed. This reuses the existing SELECT SQL parser/executor, grouped aggregate summarizer, JSON aggregate, JSONB, and canonicalization components. The only native behavior change is bounded support for parser-level `json_group_object()` and `jsonb_group_object()` aggregate summary specs.
