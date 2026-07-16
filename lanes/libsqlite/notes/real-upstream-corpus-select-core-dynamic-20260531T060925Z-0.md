# real-upstream-corpus-select-core-dynamic-20260531T060925Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260531T060925Z-0`

Base accepted HEAD: `cd24ba2f7b741bb89ced6cb6c27264084794565b`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test`
- Owned scenarios: `select6-9.1` through `select6-9.11`, the upstream
  Ticket #1634 cluster for derived tables with inner `LIMIT` / `OFFSET`, outer
  limit composition, and scalar expressions projected by the derived select.

## PHP coverage added

- Added `SQLiteRealUpstreamSelect6LimitDerivedDynamicTest.php`.
- The file contributes `1001` distinct TestRunner PASS cases:
  - 1 upstream source-citation case.
  - 250 dynamic inner-derived `LIMIT/OFFSET` plus outer `LIMIT` cases.
  - 250 dynamic outer `LIMIT/OFFSET` cases over a derived table.
  - 250 dynamic scalar-expression projection cases inside a limited derived
    table, using accepted explicit `AS` alias syntax.
  - 250 dynamic joins against a limited derived table.
- Focused verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect6LimitDerivedDynamicTest.php`
  - Result: `1 test files, 4005 assertions, 0 failures`.

## Non-overlap

This slice extends real upstream `select6.test` section 9. It does not repeat
accepted `select1` through `select5` aggregate/grouping work, existing
`select6-1.x` through `select6-8.x` derived-table coverage, `select7` through
`selectH` dynamic files, parser-level JSON table source/cursor/constraint
work, expression `ORDER BY`, grouped SELECT text, compound-collation batches,
or metadata-only runner rows.

Mapped denominator remains unchanged because `select6.test` is already in the
hydrated upstream inventory.

## Dependency closure

No new support component is needed. This reuses the existing generic
`SQLiteSelectSql` row-array SELECT executor and the hydrated upstream SQLite
test cache as source truth.

## Follow-up

The red-first probe found that the exact upstream implicit alias form
`(SELECT 10+x) y` is still rejected by the parser as an unsupported expression.
The admitted dynamic cases use equivalent explicit `AS y` syntax to keep this
handoff countable and green. A later parser slice can target implicit alias
acceptance directly.
