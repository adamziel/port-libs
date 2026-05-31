# real-upstream-corpus-select-core-dynamic-20260531T071905Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`
- `selectH-4.1`: `SELECT 1 FROM (...)` over a compound whose left arm is
  `SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema`.
- `selectH-4.2`: direct compound output preserves distinct collated schema-name
  rows followed by the table arm.

## Change

Added `SQLiteRealUpstreamSelectHSchemaCompoundDynamicTest.php` with 1000 dynamic
TestRunner cases plus source/non-overlap guard cases. The batch varies schema
names, duplicate schema entries, RTRIM-significant names, and table-arm values
while preserving the upstream `selectH-4.1`/`selectH-4.2` SQL shape.

No production source change was needed; the existing `SQLiteSelectSql` compound
and derived-table executor already handles this upstream behavior.

## Non-Overlap

This owns only the `selectH-4.1` and `selectH-4.2` schema compound cluster. It
does not repeat accepted `selectH-1`/`selectH-2`/`selectH-3`/`selectH-5`
omit-unused and empty-right UNION batches, `selectG` large/scalar VALUES,
`selectF` register-copy ordering, `selectE` compound collation/error,
`selectD` parenthesized joins, `selectC` alias resolution, grouped SELECT text,
expression ORDER BY, SELECT subquery, or JSON table SELECT source/cursor/
constraint work.

Mapped denominator coverage remains unchanged because `selectH.test` is already
represented in the hydrated upstream manifest/runner map.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectHSchemaCompoundDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectHSchemaCompoundDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This reuses the existing bounded
`SQLiteSelectSql` parser/executor, compound SELECT handling, derived-table
materialization, DISTINCT comparison, and `COLLATE rtrim` expression plumbing.
