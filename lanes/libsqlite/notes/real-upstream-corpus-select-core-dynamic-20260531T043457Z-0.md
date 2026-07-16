# real-upstream-corpus-select-core-dynamic-20260531T043457Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260531T043457Z-0`
Base accepted HEAD: `7db59d242cf2590641e3217c1b87d71727256c92`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test`
- Ported section: `select2-4.1` through `select2-4.7`
- Behavior cluster: multi-table SELECT WHERE expressions over joined rows using scalar `max(a,b)`, scalar `min(a,b)`, truthiness, `NOT`, and searched `CASE` filters.

## Patch

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T043457ZTest.php`.
- The test file cites the hydrated upstream `select2.test` source and adds `1250` dynamic SELECT cases plus source/non-overlap/dependency assertions.
- The cases exercise `SQLiteSelectSql` join row production, WHERE expression truthiness, scalar min/max evaluation, `NOT`, and searched `CASE` dispatch against varied input rows and thresholds.

## Non-overlap

This avoids accepted grouped SELECT text, SELECT subqueries, expression `ORDER BY`, JSON table SELECT sources, compound SELECT, select1 projection/repeated wildcard, and earlier select2 count/range batches. The selected upstream section is specifically the `select2-4.*` multi-table WHERE scalar-function and CASE filter block.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T043457ZTest.php`
  - `1 test files, 5009 assertions, 0 failures`
  - `1252` PASS lines

## Dependency closure

No new support component is needed. The slice reuses existing lane-local `SQLiteSelectSql` support for joins, WHERE predicates, scalar `min()`/`max()`, truthiness, `NOT`, and `CASE`.

## Follow-up

The next select-core dynamic batch should choose a different unowned upstream section, preferably `selectB` or a non-overlapping `selectC` behavior that is not only another wrapper around accepted compound/order/group/subquery helpers.
