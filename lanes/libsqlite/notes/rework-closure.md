# Libsqlite Rework Closure Notes

## 2026-05-27 B-tree Overflow Vacuum Current Next16

This isolated B-tree/vacuum slice adds
`SQLiteOverflowVacuumTruncatePlan`, composing existing overflow freelist
release with the next incremental-vacuum tail truncation pass on the current
page image. It avoids accepted standalone overflow freelist release, bulk
overflow freeblock materialization, page relocation, root collapse, and VFS/WAL
apply clusters by proving the post-release freelist image is immediately fed
into tail truncation and that truncated overflow pages disappear from the final
page-image set.

Focused evidence:

```text
php -l lanes/libsqlite/src/SQLiteOverflowVacuumTruncatePlan.php
php -l lanes/libsqlite/tests/SQLiteBTreeOverflowVacuumCurrentNext16Test.php
php -l lanes/libsqlite/examples/application-overflow-vacuum-current-next.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowVacuumCurrentNext16Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 53 assertions, 0 failures
php lanes/libsqlite/examples/application-overflow-vacuum-current-next.php
```

The focused run adds 50 new `TestRunner` PASS lines. `lane-status.json`
therefore raises `phpPass` from 5433 to 5483 without changing mapped upstream
coverage. The Application smoke reports copied `wp_options` transient table and
`option_name` index overflow tails released into the freelist, then removed by
the current vacuum truncation pass with final page count and updated pointer-map
page evidence.

Dependency closure: no new support component is needed. This reuses lane-local
SQLite database page readers, overflow freelist release planning, freelist
trunk parsing/assembly, pointer-map mutation, and incremental-vacuum tail
truncation.

## 2026-05-27 B-tree Overflow Next-pointer Release

This isolated B-tree slice adds native overflow-chain tracing through
`SQLiteOverflowPage::pageNumbersFromChain()` and
`SQLiteOverflowPage::pageNumbersFromDatabase()`. The focused behavior follows
the 4-byte next-page field on each overflow page, so delete/reuse/rebalance
callers can release non-contiguous overflow chains that were moved by
auto-vacuum instead of assuming `first + n` page numbers. It avoids accepted
overflow freelist release, bulk overflow freeblock materialization,
overflow-cell reuse apply, root collapse, page move, and VFS/WAL apply
clusters by narrowing the patch to next-pointer chain discovery and validation.

Focused PASS delta: `+27` verified PASS lines in
`SQLiteBTreeOverflowNextPointerTest.php` (`1 test file / 85 assertions /
0 failures`). `lane-status.json` moves `phpPass` from `3796` to `3823` for
those newly added focused PHP test cases only.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteOverflowPage.php
php -l lanes/libsqlite/tests/SQLiteBTreeOverflowNextPointerTest.php
php -l lanes/libsqlite/examples/application-overflow-next-pointer-release.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowNextPointerTest.php
php lanes/libsqlite/examples/application-overflow-next-pointer-release.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

The Application smoke reports a copied `wp_options` transient whose overflow
chain uses non-contiguous pages `[11, 6, 14]`; the plan reuses the freed local
cell slot for a smaller transient, releases the obsolete overflow pages through
the freelist, secure-deletes overflow leaves, and rewrites auto-vacuum
pointer-map entries to free-page.

Dependency closure: no new support component is needed. This reuses the
lane-local SQLite database reader, overflow page encoder, b-tree leaf
delete/reuse helpers, freelist planner, and pointer-map mutation support.

## 2026-05-27 Auto-vacuum Pointer-map Apply

This isolated B-tree pointer-map slice adds
`SQLiteAutoVacuumPointerMapApplyPlan`, a bounded apply step that merges
auto-vacuum pointer-map page updates and optional B-tree page images into a
complete database image. It avoids accepted table/index page relocation,
root-collapse, index-interior merge, overflow freelist release, bulk overflow
freeblock materialization, freeblock/freelist rebalance, and VFS/WAL storage
apply clusters.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 8909 assertions,
up from the current focused baseline of 8861 assertions (`+48`). The new
assertions cover multi-pointer-map-page application, pointer-map page skip
math at page 105/106, applied-entry summaries, complete database byte
materialization, base page-image preservation, copied `wp_options` readability
after apply, and malformed apply guards. Application smoke:
`examples/application-autovacuum-pointer-map-apply.php`.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteAutoVacuumPointerMapApplyPlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-autovacuum-pointer-map-apply.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-autovacuum-pointer-map-apply.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses the
lane-local pointer-map planner, B-tree/page-image fixtures, SQLite database
reader, and pure PHP Application option rows.

## 2026-05-27 B-tree Overflow Cell Reuse Delete Apply

This isolated B-tree slice adds `SQLiteBTreeOverflowCellReuseDeleteApplyPlan`
to compose three existing primitives into one page-image application path:
delete an overflow-backed table or index leaf cell, write a smaller replacement
cell into the resulting reusable freeblock, then release the obsolete overflow
pages through the native freelist/pointer-map planner. The slice avoids
accepted bulk overflow freeblock materialization, overflow freelist release,
empty-leaf/root-collapse/page-move/index-interior-merge, and the earlier
freeblock/freelist rebalance summary path by proving the replacement cell is
actually written into the freed cell space before overflow pages are made
available for reuse.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteBTreeOverflowCellReuseDeleteApplyPlan.php
php -l lanes/libsqlite/examples/application-overflow-cell-reuse-delete-apply.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-overflow-cell-reuse-delete-apply.php
git diff --check -- lanes/libsqlite
```

The focused `SQLiteHeaderTest.php` run reached `8900` assertions with `0`
failures. The Application smoke reports a copied `wp_options` transient
replacement where the old overflow-backed cell slot is reused for a smaller
local transient cell, obsolete overflow pages enter the freelist, secure-delete
clears released overflow leaves, and auto-vacuum pointer-map entries become
free-page.

Dependency closure: no new support component is needed; this reuses lane-local
table/index leaf delete helpers, freeblock insertion, overflow page traversal,
freelist planning, and pointer-map mutation.
## 2026-05-27 B-tree Freeblock/Freelist Rebalance

This isolated B-tree slice adds `SQLiteBTreeFreeblockFreelistRebalancePlan`
for non-empty table/index leaf delete results: the modified leaf page with
reusable freeblocks is kept in the page-image set while obsolete overflow pages
from the deleted cell are released through native freelist planning. It avoids
accepted empty-leaf release, bulk overflow freeblock materialization, overflow
freelist-release-only, page move, root collapse, and index-interior merge
clusters.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteBTreeFreeblockFreelistRebalancePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-btree-freeblock-freelist-rebalance.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-btree-freeblock-freelist-rebalance.php
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
table/index delete results, B-tree freeblock accounting, freelist free
planning, secure-delete clearing, and auto-vacuum pointer-map mutation.

## 2026-05-27 SELECT SQL Compound Text Dispatch

This isolated SQL execution/planner slice adds parser-level compound SELECT
text dispatch for `SQLiteSelectSql` without repeating accepted standalone
`SQLiteSelectCompound` row-array helpers, single-table SELECT text, JOIN text,
subquery text, expression `ORDER BY`, grouped SELECT text, comma `LIMIT`, CTE
materialization, JSON table sources, VFS/WAL/B-tree storage work, or scalar-only
helpers. Top-level `UNION`, `UNION ALL`, `INTERSECT`, and `EXCEPT` arms are
planned through the existing SELECT SQL executor, combined with accepted
compound row semantics, and then finished with compound-level `ORDER BY`,
`LIMIT`, and `OFFSET`.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 8324 assertions,
up from the current lane-status focused baseline of 8273 assertions (`+51`).
The new assertions cover UNION duplicate removal, UNION ALL duplicate
retention, INTERSECT, EXCEPT, chained compounds, ordinal final ORDER BY,
comma-form compound LIMIT/OFFSET, CTE-fed compound arms, plan-shape evidence,
and malformed compound guards. Application smoke:
`examples/application-select-sql-compound.php`.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-sql-compound.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-sql-compound.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses the
lane-local SELECT SQL parser, query-plan executor, compound row combiner,
projection/predicate/result helpers, CTE materialization, scalar dispatch, and
pure PHP row arrays.

## 2026-05-27 SELECT SQL WITH CTE Materialization

This isolated SQL execution/planner slice adds bounded non-recursive
`WITH` common-table-expression materialization for `SQLiteSelectSql` without
repeating accepted scalar operators, subqueries, grouped SELECT text,
expression `ORDER BY`, JSON table SELECT sources, VFS sync/apply, WAL
checkpoint/savepoint, B-tree page move/root collapse, or Unicode GLOB work.
Each CTE body is executed through the existing SELECT text planner, optional
CTE column lists rename the materialized output columns, later CTEs can read
earlier CTEs, and the final SELECT consumes the materialized row arrays through
the accepted query-plan pipeline.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 8016 assertions,
up from the accepted focused baseline of 7901 assertions (`+115`). The new
assertions cover single CTEs, CTE column-list renaming, chained CTEs, grouped
CTE inputs, CTE joins, JSON table CTE inputs, CTE use from an `IN` subquery,
plan-shape evidence, hidden-order stripping through CTE bodies, and malformed
CTE guards. Application smoke:
`examples/application-select-sql-cte.php`.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-sql-cte.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-sql-cte.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses the
lane-local SELECT SQL parser, query-plan executor, projection/predicate/result
helpers, JSON table row materialization, grouped aggregate summaries, scalar
dispatch, and pure PHP row arrays. Recursive CTEs are explicitly guarded as a
future VDBE-style execution gap.

## 2026-05-27 SELECT SQL Comma LIMIT Dispatch

Current-base SQL-exec slice on accepted `7e509304` adds parser-level SQLite
`LIMIT offset,count` support without repeating queued `BETWEEN`, accepted
expression `ORDER BY`, grouped SELECT text, JOIN text, JSON table source/cursor,
or standalone result LIMIT/OFFSET helpers. `SQLiteSelectSql::limitOffset()` now
recognizes the comma form and stores the second operand as the row limit and
the first operand as the offset, matching SQLite's documented reversed operand
order for that syntax. The existing `SQLiteSelectQuery` and `SQLiteSelectResult`
pipeline then applies the parsed limit/offset to plain rows, joined rows,
grouped aggregate rows, and JSON table rows.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 7393 assertions,
up from the current focused baseline of 7276 assertions (`+117`). The
Application smoke is `examples/application-select-sql-limit-comma.php`.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-sql-limit-comma.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-sql-limit-comma.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses the
lane-local SELECT SQL parser, query-plan executor, row-array result limiter,
join execution, grouped aggregate summaries, and JSON table rows.

## 2026-05-27 VFS Process File-Lock Application

Current-base dependency/open slice adds `SQLiteVfsFileLock` without repeating
accepted VFS lock byte-range planning, bounded lock-state application, VFS file
writer application, VFS rollback-journal apply, VFS capability planning, or
sidecar path planning. The new adapter keeps process-backed PHP lock handles
open for SQLite lock plans, maps shared/reserved/pending/exclusive requests to
bounded lock sidecars, preserves shared reader concurrency, blocks competing
writers and pending/exclusive readers, propagates open-plan/nolock blockers,
and releases per-connection or whole-path lock state.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 6875 assertions,
up from the current lane-status focused baseline of 6793 assertions (`+82`).
The Application smoke is `examples/application-vfs-file-lock-apply.php`.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteVfsFileLock.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-vfs-file-lock-apply.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-vfs-file-lock-apply.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no shared root support component is needed. This is the
smallest lane-local dependency closure for process-backed VFS lock handles.
Follow-up remains true POSIX byte-range locking and durable fsync policy if a
future pager/VFS slice requires host-specific primitives beyond PHP `flock()`
and sidecar files.

## 2026-05-27 SELECT SQL Expression ORDER BY Dispatch

Current-base SQL-exec slice on accepted `f34a1a06` adds bounded parser-level
`ORDER BY` expression support without repeating accepted single-table SELECT
text, JOIN text, JSON table source, GROUP BY/HAVING, or standalone result
ordering helpers. `SQLiteSelectSql` now parses scalar expression ORDER terms
such as `coalesce(autoload, 'zz')`, `length(option_name)`, `lower(option_name)`,
and aggregate ORDER terms such as `sum(bytes)` / `count(*)` in grouped SELECT
text. Expression sort keys are lowered into hidden projection columns so the
accepted `SQLiteSelectQuery` and `SQLiteSelectResult` ordering path can reuse
the same row-array comparator, and `SQLiteSelectSql::execute()` strips hidden
sort keys before returning Application-facing rows.

Focused assertions added: 71. `SQLiteHeaderTest.php` now covers scalar
expression ordering, literal expression ordering, hidden order-key plan shape,
hidden-column stripping, aggregate expression ORDER BY over grouped rows, and
malformed/unsupported expression guards. Application smoke:
`examples/application-select-sql-order-expression.php`.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-sql-order-expression.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-sql-order-expression.php
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed; this reuses the
accepted bounded SELECT parser/query-plan/result helpers and scalar function
dispatcher.

## 2026-05-27 SELECT SQL Text GROUP BY/HAVING Dispatch

This isolated SQL execution/planner slice does not reuse stale May 25 rework
markers and does not repeat accepted single-table SELECT SQL text dispatch,
JOIN text dispatch, standalone grouped aggregate helpers, composite GROUP BY
row-array execution, SELECT query-plan composition, scalar WHERE operands,
JSON host joins, WAL byte truncation, or VFS writer work. It adds one bounded
parser-level behavior cluster: `SQLiteSelectSql` now recognizes `GROUP BY` and
`HAVING` clauses and rewrites bounded aggregate functions into the existing
native grouped summary pipeline.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 6106 assertions,
up from the current lane-status focused baseline of 6055 assertions (`+51`).
The new assertions cover copied `wp_options` SQL text with single and composite
group keys, joined-source grouping, aggregate HAVING predicates, `count(*)`,
`count(column)`, `sum`, `avg`, `min`, `max`, `group_concat`, plan-shape
rewrites, NULL grouping buckets, final ORDER BY/LIMIT/OFFSET, and malformed SQL
guards.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-sql-grouped-preview.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-sql-grouped-preview.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
SELECT SQL parsing, grouped aggregate summaries, predicate/projection/result
helpers, join composition, scalar dispatch, and pure PHP row arrays.

## 2026-05-27 SELECT Composite GROUP BY Query Pipeline

This isolated SQL execution/planner slice does not reuse stale May 25 rework
markers and does not repeat accepted single-column grouped aggregate pipeline,
query-plan composition, WHERE scalar operands, projection, join, wildcard,
CASE, compound SELECT, or expression-index planning. It adds one bounded
execution behavior cluster: `SQLiteSelectQuery` now accepts a non-empty
`groupBy.columns` list and `SQLiteGroupedAggregate` builds composite SQLite
group keys while preserving each grouping column for projection and final
result ordering.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 5473 assertions,
up from the current accepted B-tree interior redistribution baseline of 5340
assertions (`+133`). The new assertions cover copied wp_options rows grouped
by `autoload` plus option kind, composite key coalescing, projected grouping
columns, HAVING predicates over aggregate summary rows, aggregate
ORDER BY/LIMIT, NULL grouping keys, scalar/CASE projection over grouped rows,
raw summary output, strict validation guards, and Application smoke output.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteGroupedAggregate.php
php -l lanes/libsqlite/src/SQLiteSelectQuery.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-grouped-aggregate-preview.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-grouped-aggregate-preview.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local grouped aggregate summaries, SELECT predicate/projection/result
helpers, scalar dispatch, SQLite BLOB wrappers, and pure PHP row arrays.

## 2026-05-27 SELECT GROUP BY/HAVING Query Pipeline

This isolated SQL execution/planner slice does not reuse stale May 25 rework
markers and does not repeat accepted grouped aggregate standalone helpers,
SELECT query-plan composition, WHERE residual predicate basics, projection,
join, wildcard, CASE, compound SELECT, or expression-index planning. It adds
one bounded execution behavior cluster: `SQLiteSelectQuery` now wires
GROUP BY/HAVING aggregate dispatch into the SELECT pipeline after
FROM/JOIN/WHERE and before projection/result clauses.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 5420 assertions,
up from the current accepted B-tree interior redistribution baseline of 5340 assertions (`+80`). The new
assertions cover aggregate ORDER BY/LIMIT/OFFSET, HAVING predicates over
aggregate summary rows, projected summary columns, scalar `printf()` labels,
CASE buckets, DISTINCT over projected aggregate rows, final ORDER BY, NULL-only
aggregate groups, empty groups, strict validation guards, and copied Application
option-summary smoke output.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectQuery.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-grouped-aggregate-preview.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-grouped-aggregate-preview.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local grouped aggregate summaries, SELECT predicate/projection/result
helpers, scalar dispatch, and pure PHP row arrays.

## 2026-05-27 SELECT WHERE Scalar Expression Operands

This isolated scalar SQL execution/planner slice does not reuse stale May 25
rework markers and does not repeat accepted SELECT projection scalar helpers,
CASE projection, wildcard projection, join row production, compound SELECT,
WHERE residual predicate basics, expression-index planning, or bounded
query-plan composition. It adds one bounded execution behavior cluster:
`SQLiteSelectPredicate` operands now evaluate typed column/literal expressions
and scalar function expression arrays inside WHERE predicates.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 5199 assertions,
up from the accepted lane-status recorded 5149 baseline (`+50`). The new
assertions cover scalar operands in comparison, `BETWEEN`, `IN`/`NOT IN`,
`LIKE ESCAPE`, `GLOB`, `IS`/`IS NOT`, `IS NULL`, boolean composition, nested
function arguments, typed literal operands, SQL NULL propagation, malformed
expression guards, and copied Application option-name/value filtering.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectPredicate.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-options-where-predicate-preview.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-options-where-predicate-preview.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses
lane-local scalar dispatch, predicate truth handling, BLOB wrappers,
LIKE/GLOB matchers, and pure PHP result-row arrays.

## 2026-05-27 JSON Table NULL Path Arguments

This isolated JSON table/window slice does not reuse stale May 25 rework
markers and does not repeat accepted JSON projection, duplicate hidden
constraints, malformed JSONB planning, residual LIKE/ESCAPE, reverse-root
metadata, JSON subtype handoff, or JSON object aggregate/window behavior. It
adds one bounded table-valued behavior cluster: `json_each(X, NULL)` and
`json_tree(X, NULL)` now return empty rowsets through SQL argument-vector
dispatch instead of treating the explicit SQL NULL path as an omitted path.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 5018 assertions,
up from the accepted lane-status recorded 4952 baseline (`+66`). The new
assertions cover strict JSON text, JSON5 text, JSONB blobs, JSON constructor
subtype values, case-insensitive function dispatch, and preservation of normal
non-NULL path expansion alongside the NULL-path empty-rowset behavior.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteJsonEach.php
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-table-null-path.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-table-null-path.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
JSON5 decoding, JSONB encoding/decoding, JSON subtype wrappers, JSON
constructors, and existing JSON table-valued row dispatch.

## 2026-05-27 JSON Table Subtype Handoff

This isolated JSON table/window slice does not reuse stale May 25 rework
markers and does not repeat accepted JSON projection, duplicate hidden
constraints, malformed JSONB planning, residual LIKE/ESCAPE, reverse-root
metadata, or JSON object aggregate/window behavior. It adds one bounded
table-valued behavior cluster: `json_each()` and `json_tree()` hidden `json`
constraints now accept `SQLiteJsonSubtypeValue` inputs produced by JSON
constructors and aggregates.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 4890 assertions,
up from the lane-status recorded 4823 baseline (`+67`). The new assertions
cover subtype validation metadata, constructor subtype rows, `json_each`
projection, `json_tree` residual filtering, aggregate-produced subtype
expansion, reverse-root subtype paths, malformed subtype planning, and
inspection helper handoff for `json_type()`, `json_array_length()`, and path
location.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteJsonEach.php
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/src/SQLiteJsonInspection.php
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-table-subtype-handoff.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-table-subtype-handoff.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
JSON subtype wrappers, JSON constructors, JSON aggregate output, JSON
inspection/path decoding, JSON table planning, and residual predicate helpers.

## 2026-05-27 JSON Table Reverse-Root Metadata

This isolated JSON table/window slice does not reuse the stale May 25 rework
markers and does not repeat accepted JSON projection, duplicate hidden
constraints, malformed JSONB planning, residual LIKE/ESCAPE, or JSON object
aggregate/window behavior. It adds one bounded table-valued behavior cluster:
`json_tree()` selected-root rows now preserve the resolved array index for
reverse roots such as `$.plugin.rules[#-1]`.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 4721 assertions,
up from the lane-status recorded 4629 baseline (`+92`). The new assertions
cover selected-root `key`, `parent`, `path`, `root`, value, rowid projection,
residual filtering, JSONB parity for `[#-2]`, and `json_each()` comparison
behavior over copied Application settings payloads.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-table-reverse-root.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-table-reverse-root.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
JSON path parsing, JSONB decoding, JSON table planning, projection, and
residual predicate helpers.

## 2026-05-26 JSON Dispatch Rework Markers

This isolated closure slice checked the outstanding handoff rework markers:

- `port-libsqlite-20260525T071150Z.needs-lane-rework.md`
- `port-libsqlite-20260525T100407Z.needs-lane-rework.md`
- `port-libsqlite-current-rebase-20260525T054020Z-02383337bcf4.needs-lane-rework.md`
- `port-libsqlite-finisher-20260525T092629Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T082910Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T083258Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T093834Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T100451Z.needs-lane-rework.md`
- `port-libsqlite-rework-20260525T105622Z.needs-lane-rework.md`

Current accepted lane files already contain the requested rebased behavior:

- `SQLiteJsonCanonical::jsonSqlFunction()` and `jsonSqlFunctionArguments()` cover case-insensitive `json`/`jsonb` dispatch, SQL NULL propagation, JSON5 text, text BLOB fallback, JSONB passthrough, and malformed input rejection.
- `SQLiteJsonPretty::jsonPrettySqlFunction()` and `jsonPrettySqlFunctionArguments()` cover case-insensitive `json_pretty` dispatch, one-or-two argument SQL arity, scalar SQL coercion including booleans and whole REAL values, JSON subtype input, text/JSONB BLOB input, custom indentation, SQL NULL propagation, and invalid function-name rejection.
- `SQLiteJsonExtract::extractSqlFunction()` and `extractJsonArgumentSqlFunction()` preserve the accepted `json_extract`/`jsonb_extract` SQL result typing and constructor-argument subtype propagation.
- `examples/application-json-canonical-option-preflight.php`, `examples/application-json-pretty-option-review.php`, and `examples/application-json-extract-subtype-option-diagnostics.php` retain the Application-visible smoke paths referenced by the stale rework markers.

The only additive behavior in this closure patch is a focused assertion that
direct `JSON_PRETTY` dispatch and argument-vector `json_pretty` dispatch remain
equivalent for a JSONB BLOB input with a BLOB custom indent. That guards the
conflict-prone rework boundary without changing manifest denominators.

Focused verification for this closure slice:

```sh
php -l lanes/libsqlite/src/SQLiteJsonPretty.php
php -l lanes/libsqlite/src/SQLiteJsonCanonical.php
php -l lanes/libsqlite/src/SQLiteJsonExtract.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-pretty-option-review.php
php -l lanes/libsqlite/examples/application-json-canonical-option-preflight.php
php -l lanes/libsqlite/examples/application-json-extract-subtype-option-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-pretty-option-review.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses lane-local
JSON canonicalization, JSON5 parsing, JSONB encoding/decoding, JSON subtype
wrappers, BLOB value wrappers, and SQL scalar coercion helpers without
activating shared support-library work.

## 2026-05-26 JSON Pretty NULL Wrapper-Indent Rework Refresh

This isolated priority refresh keeps the same outstanding rework-marker scope
and adds one bounded guard to the already accepted `json_pretty()` SQL-dispatch
cluster: when the first SQL argument is NULL, direct dispatch and
argument-vector dispatch now have focused assertions proving that BLOB and JSON
subtype indentation wrappers are ignored and the result remains SQL NULL. The
Application option review smoke reports the same NULL-with-wrapper-indent paths.

No new upstream denominator is claimed. The rework remains additive on top of
the accepted `json`/`jsonb`, `json_pretty`, and `json_extract`/`jsonb_extract`
dispatch behavior and exists to make the stale May 25 conflict boundary easier
for the clean integrator to accept without replaying old manifest/status text.

Focused verification for this refresh:

```sh
php -l lanes/libsqlite/src/SQLiteJsonPretty.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-pretty-option-review.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-pretty-option-review.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses the
lane-local SQL NULL propagation path plus existing BLOB and JSON subtype wrapper
coercion for the optional indentation argument.

## 2026-05-26 JSON Table Duplicate Hidden Constraints

This isolated JSON table/window slice adds planner behavior for repeated hidden
`json` and `root` constraints on `json_each`/`json_tree`: only the first usable
hidden equality is consumed as the virtual-table argument vector, while later
duplicate hidden constraints remain residual filters. That keeps composed
Application query-builder predicates from silently retargeting expansion when a
second hidden `root` or `json` predicate conflicts with the selected argv.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 3918 assertions,
up from the lane-status recorded 3876 baseline (`+42`). The new assertions cover
duplicate `root` plan shape, conflicting residual suppression, matching
duplicate roots, projection/order after residual filtering, duplicate `json`
residuals, and unusable hidden predicates before later usable hidden argv
selection.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-table-duplicate-hidden-constraints.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-json-table-duplicate-hidden-constraints.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses existing
lane-local JSON table planning, JSON path validation, JSONB/BLOB wrappers, and
residual predicate evaluation.

## 2026-05-27 B-tree Leaf Sibling Merge Materialization

This isolated planner/WAL/B-tree closure slice adds bounded B-tree leaf sibling
merge planning after delete underflow without repeating the accepted rebalance
summary-only work. `SQLiteBTreeLeafMergePlan` materializes merged table-leaf
and index-leaf pages from sibling page images, preserves rowid/record order,
reports the parent divider removal, and emits the obsolete right-sibling
free-page action for later freelist/pointer-map application.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 4628 assertions,
up from the lane-status recorded 4560 baseline (`+68`). The new assertions
cover table leaf merge page materialization, index leaf merge page
materialization, merged row/record order, free-space deltas, parent divider
metadata, obsolete sibling page actions, and malformed unordered/type/page
number guards.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteBTreeLeafMergePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-btree-leaf-merge-plan.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-btree-leaf-merge-plan.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses existing
lane-local B-tree page headers, table/index leaf page assemblers, cell parsers,
record encoding, and free-space accounting; pointer-map/freelist application is
left as the next B-tree storage slice.

## 2026-05-27 B-tree Leaf Merge Pointer-Map Application

This isolated B-tree closure slice builds on accepted leaf merge materialization
without repeating the summary-only rebalance work. `SQLiteBTreeLeafMergeApplicationPlan`
composes the merged left sibling page with existing freelist free-page planning
so the obsolete right sibling is placed on the freelist and, for auto-vacuum
databases, its pointer-map entry is rewritten to `free-page`.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 4683 assertions,
up from the accepted 4628 baseline (`+55`). The new assertions cover table and
index leaf merge application, merged page images, freelist first-trunk/count
updates, auto-vacuum pointer-map page rewrites, obsolete sibling free-page
metadata, missing-page/page-size guards, and copied wp_options autoload-index
smoke diagnostics.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteBTreeLeafMergeApplicationPlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-btree-leaf-merge-apply.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-btree-leaf-merge-apply.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses existing
lane-local B-tree leaf merge materialization, SQLiteDatabase freelist mutation,
pointer-map update planning, page header parsing, and record/cell encoders.
Next B-tree work should move to broader redistribution or parent divider/
rightmost write application.

## 2026-05-27 Dependency/Open SHM Wal-Index Loader

This isolated dependency-suite slice adds bounded `-shm` wal-index loading
without repeating accepted sidecar path planning, page-cache loading, lock
coordination, WAL open-view materialization, or WAL read-mark helpers.
`SQLiteShmIndex` parses the duplicated wal-index headers, validates page-size
and backfill counters, reads checkpoint backfill state, classifies five SQLite
reader marks, reports reusable slots, and marks stale header copies for later
VFS/file-control integration.

Focused assertion delta: `SQLiteHeaderTest.php` now passes at 4823 assertions,
up from the accepted 4683 baseline (`+140`). The selected SHM-index test adds
71 assertions covering little- and big-endian SHM headers, initialized/checksum
flags, salts/checksums, checkpoint pinned-frame diagnostics, invalid read marks,
unused reusable slots, stale duplicated headers, `fromFile()` loading, and
malformed short/page-size/backfill/byte-order guards.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteShmIndex.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-shm-index-preflight.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["loads sqlite shm wal-index headers and checkpoint read marks"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-shm-index-preflight.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This is a
lane-local native PHP support component for SQLite SHM/wal-index sidecars and
reuses accepted WAL, open, sidecar, page-cache, and lock-coordination evidence.
Next dependency/open work should connect this to WAL-open/checkpoint
persistence or a bounded native file-control/locking adapter.

## 2026-05-27 Dependency/Open VFS File-Handle Write Application

This isolated dependency-suite slice does not repeat accepted VFS sidecar
planning, VFS capability/file-control planning, lock byte-range planning, WAL
durable checkpoint byte planning, or WAL file-write preview planning. It adds
the missing bounded native application layer:
`SQLiteVfsFileWriter` applies accepted write/sync/truncate/directory-sync
operations to local PHP file handles and exposes an `applyWalCheckpoint()`
adapter that materializes WAL checkpoint database bytes plus WAL restart or
truncate sidecar bytes.

Focused assertion delta: the selected `SQLiteHeaderTest.php` test adds 62
passing assertions for database image writes, WAL restart header writes, WAL
truncate application, operation ordering, sync/directory-sync accounting,
sparse writes, byte-count validation, missing payloads, unsupported operations,
root/path guards, read-only/immutable writer guards, and missing sync targets.
The lane status moves `phpPass` from 761 to 762 and mapped coverage from 423
to 424.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-vfs-file-writer-apply.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["applies sqlite vfs wal checkpoint file writes to local handles"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-vfs-file-writer-apply.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This is a
lane-local native PHP VFS file-handle writer that reuses accepted WAL
checkpoint, VFS sidecar, VFS capability, and lock evidence.

## 2026-05-27 JSON Table Window Ranking

This isolated JSON table/window slice does not repeat accepted JSON projection,
duplicate hidden constraints, malformed JSONB planning, SQL NULL path handling,
JSON subtype handoff, LIMIT/OFFSET planning, or JSON object aggregate/window
behavior. It adds one bounded table/window behavior cluster:
`SQLiteJsonTablePlan::windowedRows()` composes accepted `json_each()` /
`json_tree()` hidden constraints, residual filtering, ORDER BY, LIMIT/OFFSET,
JSONB/subtype inputs, and optional partitioning with SQLite-style window
metadata over the resulting rowset.

Focused assertion delta: the selected `SQLiteHeaderTest.php` test adds 60
passing assertions for row_number, rank, dense_rank, percent_rank, cume_dist,
ntile, lag, lead, first_value, last_value, peer groups, partitions, JSONB,
JSON subtype inputs, limit/offset composition, empty SQL NULL inputs, and
strict malformed window option guards. The lane status moves `phpPass` from
751 to 752; mapped coverage gains `focusedJsonTableWindowRanking`.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-table-window-ranking.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["annotates sqlite json table rows with ordered window semantics"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/application-json-table-window-ranking.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. This reuses existing
lane-local JSON table planning, residual predicate evaluation, row ordering,
JSONB/BLOB wrappers, JSON subtype values, and bounded window semantics.

## 2026-05-27 B-tree Table-Interior Merge Application

This clean-integrated B-tree delete/rebalance slice does not repeat accepted
leaf merge materialization, leaf redistribution, or table-interior
redistribution. It adds table-interior sibling merge materialization after
delete underflow, including parent divider removal, obsolete sibling freelist
release, pointer-map ownership rewrites, and secure-delete page clearing.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteBTreeInteriorMergePlan.php
php -l lanes/libsqlite/src/SQLiteBTreeInteriorMergeApplicationPlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-table-interior-merge-delete-rebalance.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-table-interior-merge-delete-rebalance.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses
lane-local table interior page/cell assembly, database page images, freelist
mutation, pointer-map planning, and secure-delete behavior.

## 2026-05-27 Dependency/Open Hot Rollback-Journal VFS Apply

This isolated dependency-suite slice does not repeat accepted WAL checkpoint
file-writer application, VFS sidecar/capability/lock byte-range diagnostics,
file-header/page-cache loading, or hot rollback-journal preview planning. It
extends the bounded native VFS application layer so `SQLiteVfsFileWriter` can
apply accepted hot rollback-journal recovery results to local PHP file handles:
write recovered database bytes, truncate the database to the pre-transaction
page count, sync the database, delete the `-journal` sidecar, and sync the
containing directory.

Focused assertion delta: the selected `SQLiteHeaderTest.php` test adds 65
passing assertions for recovered database writes, final truncation, journal
deletion, operation ordering, sync/directory-sync accounting, preserved
reserved-lock and super-journal blockers, idempotent delete handling,
read-only/immutable writer guards, malformed database-image guards, and the
copied Application rollback VFS smoke. The lane status moves `phpPass` from 765
to 766 and mapped coverage from 427 to 428.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
php -l lanes/libsqlite/examples/application-vfs-rollback-journal-apply.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["applies sqlite vfs hot rollback journal recovery to local handles"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/application-vfs-rollback-journal-apply.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses
lane-local rollback-journal parsing/recovery planning and the accepted bounded
VFS file-handle writer, while adding native rollback-journal sidecar deletion
application. Follow-up should connect pager transaction state to this writer
or broaden durable fsync/locking integration without repeating this rollback
apply path.

## 2026-05-27 JSON Table Virtual Cursor

This isolated JSON-table slice does not repeat accepted JSON table host-row
joins, JSON table LIMIT/OFFSET/window ranking, malformed hidden-json planner
diagnostics, duplicate hidden constraints, or queued literal SELECT/FROM JSON
table parser wiring. It adds `SQLiteJsonTableCursor`, a bounded native cursor
facade over accepted `json_each()` / `json_tree()` plans so focused tests can
exercise virtual-table open/filter/next/eof/column/rowid semantics directly.

Focused assertion delta: the selected `SQLiteHeaderTest.php` test adds 81
passing assertions for validated planner metadata, residual filtering,
`json_tree` and `json_each` cursor iteration, `rowid`/`_rowid_`/`oid` aliases,
JSON subtype and JSONB inputs, missing-root and SQL NULL empty cursors,
malformed JSONB/text diagnostics, EOF guards, and malformed argument guards.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTableCursor.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-json-table-cursor.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["opens sqlite json table virtual cursors over planned rows"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/application-json-table-cursor.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses
lane-local JSON table planning, JSONB/text/subtype validation, residual
predicate filtering, and row materialization. Follow-up should connect this
cursor lifecycle to parser/VDBE-style execution with correlated host-column
arguments without repeating accepted host-row joins or literal SELECT/FROM
parser wiring.

## 2026-05-27 WAL Pager Checkpoint Transaction

This isolated WAL slice does not repeat accepted WAL checkpoint byte
materialization, VFS file-writer application, hot rollback-journal application,
savepoint page-image rollback, WAL byte truncation, or the queued savepoint VFS
apply handoff. It adds the pager transaction admission step before checkpoint
apply: compose SQLite lock acquisition, busy-handler outcomes, accepted WAL
checkpoint write plans, restart/truncate WAL sidecar decisions, and operation
ordering into one bounded plan.

Focused assertion delta: selected `SQLiteHeaderTest.php` coverage passed at
6528 assertions, +71 over the accepted 6457 baseline. Coverage includes
PASSIVE shared-lock checkpoints, RESTART/TRUNCATE lock escalation through
shared/reserved/pending/exclusive, reader-limited busy checkpoints, pending
writer blockers, shared-reader exclusive-lock blockers, busy-handler
dependencies, restart header writes, truncate sidecar operations, invalid mode
guards, empty path guards, read-only/immutable guards, and malformed database
image rejection.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLitePagerCheckpointTransactionPlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-wal-checkpoint-transaction.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-wal-checkpoint-transaction.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses
lane-local WAL parsing/checkpoint planning, VFS file-write planning,
lock-coordinator, and busy-handler components. Follow-up should connect pager
transaction state and durable fsync/lock policy to native VFS application
without repeating this admission planner, accepted file-writer application,
rollback-journal apply, or WAL byte-truncation preview work.

## 2026-05-27 JSON Table SQL Hidden Constraints

This isolated JSON-table slice does not repeat accepted JSON table cursor
iteration, parser-level `json_each()`/`json_tree()` function sources, host-row
joins, LIMIT/OFFSET pushdown, window ranking, duplicate hidden constraints, or
malformed JSONB planner diagnostics. It adds the SQL text path where bare
`json_each` and `json_tree` virtual-table sources become runnable from WHERE
hidden-column equality terms such as `json = ...` and `root = ...`.

Focused assertion delta: selected `SQLiteHeaderTest.php` coverage passed at
334 assertions, adding 51 assertions for bare `json_tree` and `json_each`
sources, aliased hidden constraints, residual predicates, ORDER BY/LIMIT,
GROUP BY/HAVING composition, hidden-only WHERE removal, SQL NULL empty
rowsets, missing-json non-runnable plans, and malformed root/alias guards.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-sql-json-hidden-constraints.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["executes bounded sqlite select sql text through query plans"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/application-select-sql-json-hidden-constraints.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses
lane-local JSON table planning, JSON path validation, SELECT predicate
filtering, grouped aggregate execution, and JSON row materialization. Follow-up
should broaden virtual-table planner/VDBE cursor integration without repeating
accepted JSON cursor, literal function-source SELECT wiring, host joins,
LIMIT/OFFSET, window ranking, or duplicate hidden-constraint planning.

## 2026-05-27 SELECT Expression-Index Range Cost

This bounded replayed SQL planner slice does not repeat accepted SELECT SQL
text execution, expression ORDER BY, GROUP BY/HAVING, SQL text JOIN, or the
earlier first-pass expression-index planner. It adds cost-ranked plan selection
for competing lower()/upper()/length()/CAST() expression indexes over copied
`wp_options` predicates.

Focused assertion delta: `SQLiteHeaderTest.php` passed at 6998 assertions,
adding 53 assertions over the current accepted 6945 baseline. The new
assertions cover point, range, IN, BETWEEN, mixed predicate ranking,
partial-index residual flags, covering columns, ORDER BY compatibility,
reversed range normalization, no-plan behavior, and metadata validation
guards.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectExpressionIndexPlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-expression-index-cost.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-expression-index-cost.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses
lane-local CREATE INDEX expression metadata, partial-index predicates, scalar
value coercion, and bounded SELECT planner arrays. Follow-up should wire these
ranked decisions into broader parser/executor planning without repeating
accepted expression ORDER BY, GROUP BY/HAVING, SQL text JOIN, or first-pass
expression-index planner work.

## 2026-05-27 VFS Rollback-Journal Commit Apply

This isolated dependency/VFS slice does not repeat accepted hot rollback
journal recovery, VFS file writer checkpoint application, locked writer,
process locks, savepoint rollback, WAL byte truncation, or rollback-journal
diagnostic-only planning. It adds the forward rollback-journal commit path:
write and sync rollback-journal bytes before database pages, write dirty pages
at page offsets, sync database pages, then delete, truncate, or persist-zero
the rollback journal and persist the directory entry.

Focused assertion delta: `SQLiteHeaderTest.php` passed at 7368 assertions,
adding 92 assertions over the current accepted 7276 baseline. The new
assertions cover operation ordering, payload routing by dirty page number,
FULL/NORMAL/EXTRA/OFF sync modes, DELETE/TRUNCATE/PERSIST journal modes,
actual local file bytes, sparse page offsets, dependency tags, read-only and
immutable guards, malformed path/page/payload guards, and copied Application
database commit smoke behavior.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteRollbackJournalCommitPlan.php
php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-vfs-rollback-commit-apply.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-vfs-rollback-commit-apply.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses the
lane-local VFS file-handle writer and rollback-journal durability evidence.
Follow-up should broaden pager/VFS transaction application and durable sync
policy without repeating accepted hot rollback-journal recovery, VFS file
writer, locked writer, process locks, savepoint rollback, WAL byte truncation,
or this rollback-journal commit path.

## 2026-05-27 VFS Super-Journal Commit Apply

This isolated WAL/rollback slice does not repeat accepted rollback-journal
commit, hot rollback-journal recovery, VFS file writer checkpoint application,
locked writer, process locks, savepoint rollback, WAL byte truncation, or the
queued WAL transaction commit append path. It adds the attached-database
super-journal commit path: write a master journal listing each rollback
journal, sync it, write and sync attached rollback journals and dirty database
pages, delete the super-journal as the atomic commit point, then clean attached
rollback journals.

Focused assertion delta: the selected focused test passed with 83 assertions
and 0 failures. The new assertions cover super-journal payloads, operation
ordering, attached database page offsets, FULL/NORMAL/EXTRA/OFF sync modes,
DELETE/TRUNCATE/PERSIST journal cleanup, actual local file bytes for two
attached databases, dependency tags, read-only and malformed input guards, and
copied Application multisite-style commit smoke behavior.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSuperJournalCommitPlan.php
php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-vfs-super-journal-commit.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["applies sqlite super-journal commits across attached database handles"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-vfs-super-journal-commit.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses the
lane-local VFS file-handle writer and rollback-journal durability evidence.
Follow-up should broaden pager/VFS atomic transaction and durable fsync
behavior without repeating accepted rollback-journal commit, hot rollback
recovery, savepoint rollback, WAL byte truncation, locked writer, process
locks, or this super-journal commit path.

## 2026-05-27 VFS Sync Plan

This isolated dependency/VFS slice does not repeat accepted rollback-journal
commit, super-journal commit, hot rollback recovery, savepoint rollback, WAL
byte truncation, locked writer, process locks, or file-writer application. It
adds bounded xSync flag and durable sequence planning for database,
rollback-journal, WAL, directory, read-only, memory, powersafe-overwrite, and
persist-journal paths.

Focused assertion delta: `SQLiteHeaderTest.php` adds 73 assertions over the
worker baseline. The assertions cover FULL/NORMAL/DATAONLY flags, skipped
sync-off/read-only/memory paths, rollback-journal commit sync ordering,
persist-journal header sync, powersafe-overwrite sequencing, dependency tags,
and malformed path/target/mode guards.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteVfsSyncPlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-vfs-sync-plan.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-vfs-sync-plan.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses
lane-local VFS file-handle and rollback-journal durability evidence. Follow-up
should wire the plan into broader pager/VFS transaction application without
repeating this planning-only path.

## 2026-05-27 VFS Sync Apply

This isolated dependency/VFS slice does not repeat accepted VFS sync planning,
rollback-journal commit, super-journal commit, hot rollback recovery,
savepoint rollback, WAL byte truncation, locked writer, process locks, or
generic file-writer application. It applies accepted xSync plans through native
PHP file handles while preserving SQLite FULL/NORMAL/DATAONLY flag evidence,
directory sync accounting, skipped read-only/memory sync plans, and missing
target guards.

Focused assertion delta: `SQLiteHeaderTest.php` adds 70 assertions over the
accepted 7831 assertion baseline and passes at 7901 assertions. The assertions
cover rollback-journal, database, persist-journal-header, directory, WAL,
read-only, memory, missing file/directory, read-only writer, immutable writer,
empty plan, missing path, and malformed status cases.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-vfs-sync-apply.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-vfs-sync-apply.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses the
lane-local VFS sync planner, file-handle writer, and rollback/WAL durability
evidence. Follow-up should wire sync application into broader pager
transaction apply paths without repeating this sync-application helper.
## 2026-05-27 SELECT SQL Scalar Operators

This bounded SQL execution/planner slice does not repeat accepted comma-LIMIT,
expression ORDER BY, GROUP BY/HAVING SQL text, SELECT SQL JOIN/text dispatch,
JSON table source wiring, or pager/VFS behavior. It adds parser-level scalar
operator expressions for `+`, `-`, `*`, `/`, `%`, and `||`, then routes those
expressions through projection, WHERE predicates, hidden ORDER BY expressions,
and grouped HAVING aggregate rewrites.

Focused assertion delta: `SQLiteHeaderTest.php` adds 67 assertions over the
accepted VFS sync-plan baseline. The assertions cover numeric coercion, text
concatenation, NULL propagation, divide/modulo-by-zero NULL results, hidden
ORDER BY expression columns, grouped HAVING rewrites, and malformed operator
guards.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectExpression.php
php -l lanes/libsqlite/src/SQLiteSelectProjection.php
php -l lanes/libsqlite/src/SQLiteSelectPredicate.php
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-sql-scalar-operators.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-sql-scalar-operators.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses the
lane-local SELECT SQL parser, scalar function dispatcher, projection,
predicate, grouped aggregate, and query-plan result pipeline. Follow-up should
broaden SQL executor/planner correctness without repeating this scalar-operator
path.

## 2026-05-27 JSON Dynamic Table Joins

This isolated JSON table slice does not repeat accepted parser-level
json_each/json_tree SELECT source wiring, JSON table cursor iteration, hidden
json/root constraint extraction, visible-column pushdown, JSON table host-row
standalone materializers, JSON table LIMIT/OFFSET, or JSON table windows. It
wires row-correlated JSON table-valued function arguments into SELECT JOIN
execution, so `json_tree(o.option_value, '$.rules')` and
`json_each(o.option_value, '$.rules')` are evaluated for each host row.

Focused assertion delta: `SQLiteHeaderTest.php` adds 60 assertions over the
8016-assertion lane-status baseline and passes at 8076 assertions. The
assertions cover INNER, LEFT, and CROSS dynamic JSON table joins, qualified
JSON table columns, dynamic right-row plan callbacks, grouped aggregate
composition, NULL-extension for missing/NULL JSON rows, and malformed dynamic
root guards.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectQuery.php
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-sql-json-dynamic-join.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-sql-json-dynamic-join.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses the
lane-local SELECT SQL parser, JSON table planner/cursor rows, scalar
expression evaluator, join executor, and copied Application option fixtures.

## 2026-05-27 JSON Dynamic Malformed JSONB Joins

This isolated JSON table slice does not repeat accepted JSON table cursor
iteration, parser-level json_each/json_tree SELECT source wiring, hidden
json/root constraints, visible-column pushdown, LIMIT/OFFSET, windows, or the
standalone host-row materializer. It tightens the parser-level dynamic JOIN
callback so row-sourced JSONB values are validated through
`SQLiteJsonTablePlan::validatedPlan()` before expansion.

Focused assertion delta: `SQLiteHeaderTest.php` moves from 8404 to 8445
assertions on this isolated base, adding 41 assertions for dynamic
`json_tree(o.option_value, '$.rules')` joins over valid JSONB, malformed JSONB,
text JSON, and SQL NULL option values. The assertions cover INNER join skip
semantics, LEFT join NULL extension, qualified JSON table columns, empty
dynamic callback rows for malformed JSONB/NULL, and preserved valid JSONB row
ordering.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-select-sql-json-malformed-jsonb-join.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-select-sql-json-malformed-jsonb-join.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses the
lane-local JSONB validator, JSON table planner, SELECT SQL executor, and
dynamic join callback path.

## 2026-05-27 Pager Journal Open Closure

This isolated pager slice does not repeat accepted rollback-journal commit,
hot rollback-journal recovery/application, VFS sync application, savepoint
rollback, WAL checkpoint transaction, or VFS locked-writer behavior. It adds a
bounded rollback-journal transaction open/no-dirty-close primitive that writes
a zeroed non-hot journal header, closes unused transactions under DELETE,
TRUNCATE, and PERSIST journal modes, and blocks write-transaction open when a
hot rollback journal must be recovered first.

Focused assertion delta: `SQLiteHeaderTest.php` adds 71 assertions over this
worktree baseline. The assertions cover pager open/close operation ordering,
payload sizes, journal-mode closure actions, hot-journal admission blocking,
reserved-lock non-hot handling, invalid stale-journal handling, VFS file-handle
application, and read-only/immutable guards.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLitePagerJournalOpenPlan.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-pager-journal-open-closure.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-pager-journal-open-closure.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses the
lane-local rollback-journal parser/hot-journal classifier and native VFS
file-handle operation applier. Follow-up should wire this primitive into
broader pager transaction-state dirty-page journaling without repeating the
accepted rollback commit/recovery, sync, savepoint, or lock clusters.

## 2026-05-27 Savepoint Counter Preserve Current

This isolated counter slice does not repeat accepted savepoint page-image
rollback, WAL byte truncation, VFS savepoint rollback application, rollback
journal commit/recovery, INSERT INTO SELECT, PRAGMA locking_mode, or SELECT SQL
execution clusters. It adds a narrow `SQLiteConnectionCounters` behavior,
checked against the local `sqlite3` CLI, for SAVEPOINT rollback diagnostics:
`ROLLBACK TO` preserves the most recent DML `changes()` value instead of
restoring it from a savepoint snapshot, while also preserving monotonic
`total_changes()` and the latest successful `last_insert_rowid()` for copied
`wp_options` write previews.

Focused assertion delta: the selected new counter test passes with 45
assertions, and the full focused `SQLiteHeaderTest.php` passes with 9660
assertions in this integration worktree after the SQLite-oracle correction. The
assertions cover nested savepoint snapshots, update/delete/insert counter
transitions, no-op writes, constructor-seeded counters, SQL function dispatch,
diagnostic before/snapshot/after payloads, and preservation flags.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteConnectionCounters.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/application-connection-counter-option-insert.php
sqlite3 :memory: < lanes/libsqlite/fixtures/savepoint-counter-oracle.sql
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["preserves sqlite savepoint rollback counters like sqlite rollback to"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/application-connection-counter-option-insert.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses the
lane-local connection-counter helper and copied Application option counter smoke.
Follow-up should wire counter snapshots into broader pager/VDBE write
execution without repeating this current-counter preservation behavior.
