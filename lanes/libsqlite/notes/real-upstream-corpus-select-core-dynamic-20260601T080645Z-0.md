# real-upstream-corpus-select-core-dynamic-20260601T080645Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260601T080645Z-0`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/subselect.test`
- Ported scenarios: `subselect-1.1`, `subselect-1.3a` through `subselect-1.3e`, `subselect-1.4`, and `subselect-1.5`.
- Behavior covered: scalar aggregate subqueries inside `WHERE`, scalar non-aggregate subqueries returning the selected first value, empty scalar subquery `NULL` fallback through `coalesce()`, compound scalar subquery first-row ordering, and arithmetic expressions composed from multiple aggregate scalar subqueries.

## Patch

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSubselectScalarWhereDynamic20260601T080645ZTest.php`.
- Adds `1,002` distinct TestRunner PASS cases:
  - 1 source-truth citation case.
  - 1,000 dynamic generic application-table cases.
  - 1 non-overlap/dependency-closure case.
- Focused behavior assertions in the new file: `26,013`.
- Mapped denominator remains `1,589 / 1,589`; `subselect.test` is already in the hydrated upstream manifest.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSubselectScalarWhereDynamic20260601T080645ZTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSubselectScalarWhereDynamic20260601T080645ZTest.php`
  - `1 test files, 26013 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSubselectScalarWhereDynamic20260601T080645ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSubselectOrderLimitDynamicTest.php`
  - `2 test files, 43017 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`

## Non-Overlap

This slice owns the early `subselect.test` scalar-expression block. It avoids the existing `subselect-2` through `subselect-4` ORDER/LIMIT dynamic file, accepted SELECT subquery join corpus, expression `ORDER BY`, grouped SELECT text, JSON table source/cursor/constraint work, WAL/VFS/B-tree/PRAGMA storage surfaces, source-neutral cleanup, and metadata-only runner rows.

## Dependency Closure

No new support component is needed. The batch reuses lane-local `SQLiteSelectSql` support for scalar subqueries, aggregate scalar subqueries, compound SELECT scalar subqueries, `coalesce()`, arithmetic predicates, and the hydrated upstream SQLite `subselect.test` source truth.
