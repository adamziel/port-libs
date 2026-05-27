# Libsqlite Rework Closure Notes

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
php -l lanes/libsqlite/examples/wordpress-select-sql-grouped-preview.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-select-sql-grouped-preview.php
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
raw summary output, strict validation guards, and WordPress smoke output.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteGroupedAggregate.php
php -l lanes/libsqlite/src/SQLiteSelectQuery.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-select-grouped-aggregate-preview.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-select-grouped-aggregate-preview.php
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
aggregate groups, empty groups, strict validation guards, and copied WordPress
option-summary smoke output.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectQuery.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-select-grouped-aggregate-preview.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-select-grouped-aggregate-preview.php
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
expression guards, and copied WordPress option-name/value filtering.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteSelectPredicate.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-options-where-predicate-preview.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-options-where-predicate-preview.php
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
php -l lanes/libsqlite/examples/wordpress-json-table-null-path.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-json-table-null-path.php
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
php -l lanes/libsqlite/examples/wordpress-json-table-subtype-handoff.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-json-table-subtype-handoff.php
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
behavior over copied WordPress settings payloads.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-json-table-reverse-root.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-json-table-reverse-root.php
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
- `examples/wordpress-json-canonical-option-preflight.php`, `examples/wordpress-json-pretty-option-review.php`, and `examples/wordpress-json-extract-subtype-option-diagnostics.php` retain the WordPress-visible smoke paths referenced by the stale rework markers.

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
php -l lanes/libsqlite/examples/wordpress-json-pretty-option-review.php
php -l lanes/libsqlite/examples/wordpress-json-canonical-option-preflight.php
php -l lanes/libsqlite/examples/wordpress-json-extract-subtype-option-diagnostics.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-json-pretty-option-review.php
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
WordPress option review smoke reports the same NULL-with-wrapper-indent paths.

No new upstream denominator is claimed. The rework remains additive on top of
the accepted `json`/`jsonb`, `json_pretty`, and `json_extract`/`jsonb_extract`
dispatch behavior and exists to make the stale May 25 conflict boundary easier
for the clean integrator to accept without replaying old manifest/status text.

Focused verification for this refresh:

```sh
php -l lanes/libsqlite/src/SQLiteJsonPretty.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-json-pretty-option-review.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-json-pretty-option-review.php
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
WordPress query-builder predicates from silently retargeting expansion when a
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
php -l lanes/libsqlite/examples/wordpress-json-table-duplicate-hidden-constraints.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-json-table-duplicate-hidden-constraints.php
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
php -l lanes/libsqlite/examples/wordpress-btree-leaf-merge-plan.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-btree-leaf-merge-plan.php
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
php -l lanes/libsqlite/examples/wordpress-btree-leaf-merge-apply.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-btree-leaf-merge-apply.php
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
php -l lanes/libsqlite/examples/wordpress-shm-index-preflight.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["loads sqlite shm wal-index headers and checkpoint read marks"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-shm-index-preflight.php
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
php -l lanes/libsqlite/examples/wordpress-vfs-file-writer-apply.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["applies sqlite vfs wal checkpoint file writes to local handles"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-vfs-file-writer-apply.php
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
php -l lanes/libsqlite/examples/wordpress-json-table-window-ranking.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["annotates sqlite json table rows with ordered window semantics"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/wordpress-json-table-window-ranking.php
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
php -l lanes/libsqlite/examples/wordpress-table-interior-merge-delete-rebalance.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-table-interior-merge-delete-rebalance.php
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
copied WordPress rollback VFS smoke. The lane status moves `phpPass` from 765
to 766 and mapped coverage from 427 to 428.

Focused verification for this slice:

```sh
php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
php -l lanes/libsqlite/examples/wordpress-vfs-rollback-journal-apply.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["applies sqlite vfs hot rollback journal recovery to local handles"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/wordpress-vfs-rollback-journal-apply.php
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
php -l lanes/libsqlite/examples/wordpress-json-table-cursor.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["opens sqlite json table virtual cursors over planned rows"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/wordpress-json-table-cursor.php
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
php -l lanes/libsqlite/examples/wordpress-wal-checkpoint-transaction.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
php lanes/libsqlite/examples/wordpress-wal-checkpoint-transaction.php
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
php -l lanes/libsqlite/examples/wordpress-select-sql-json-hidden-constraints.php
php -r 'require "tools/bootstrap.php"; require "tools/TestRunner.php"; $tests=require "lanes/libsqlite/tests/SQLiteHeaderTest.php"; $names=["executes bounded sqlite select sql text through query plans"]; $selected=array_intersect_key($tests,array_flip($names)); $r=new TestRunner(); $r->runTests($selected,"lanes/libsqlite/tests/SQLiteHeaderTest.php"); fwrite(STDOUT,"\nfocused assertions=".$r->assertions()." failures=".$r->failures()."\n"); exit($r->failures()===0?0:1);'
php lanes/libsqlite/examples/wordpress-select-sql-json-hidden-constraints.php
php -r 'json_decode(file_get_contents("lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR);'
git diff --check -- lanes/libsqlite
```

Dependency closure: no new shared support component is needed. This reuses
lane-local JSON table planning, JSON path validation, SELECT predicate
filtering, grouped aggregate execution, and JSON row materialization. Follow-up
should broaden virtual-table planner/VDBE cursor integration without repeating
accepted JSON cursor, literal function-source SELECT wiring, host joins,
LIMIT/OFFSET, window ranking, or duplicate hidden-constraint planning.
