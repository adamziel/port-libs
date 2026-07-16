# real-upstream-corpus-select-core-dynamic-20260531T061343Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260531T061343Z-0`
Base accepted HEAD: `2139c8ce030e83a04c23079c17d6da80f20ffd83`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test`
- Ported sections: `selectB-$ii.4`, `selectB-$ii.8`, `selectB-$ii.11`, and `selectB-$ii.17`
- Behavior cluster: compound `UNION ALL` subquery result behavior with outer `WHERE`, `ORDER BY`, `LIMIT`, and `OFFSET`, plus equivalent flattened compound SELECT result forms.

## Patch

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T061343ZTest.php`.
- The file adds `1251` focused TestRunner PASS cases and `5006` behavior assertions.
- The dynamic cases vary row contents, thresholds, LIMIT/OFFSET values, and whether the compound subquery has two or three arms.

## Non-Overlap

This slice does not repeat accepted single-table SELECT SQL text, JOIN text dispatch, grouped SELECT text, scalar subquery filters, expression `ORDER BY`, select2 scalar-function WHERE batches, select3 aggregate/group batches, select8 LIMIT/OFFSET grouped batches, selectC alias-resolution batches, selectD USING/coalescing batches, JSON table SELECT source/cursor/constraint work, or metadata-only upstream runner rows.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T061343ZTest.php`
  - `1 test files, 5006 assertions, 0 failures`
  - `1251` PASS lines

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local `SQLiteSelectSql` support for compound SELECT arms, subquery sources, outer predicates, ordering, and LIMIT/OFFSET result slicing.

## Follow-Up

A larger follow-up can target non-overlapping `selectB.test` sections 3 through 6 that mix compound subqueries with DISTINCT, GROUP BY/HAVING, EXCEPT/UNION, joins, and multi-column compound projections.
