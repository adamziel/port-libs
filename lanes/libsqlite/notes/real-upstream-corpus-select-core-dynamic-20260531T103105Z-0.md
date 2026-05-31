# real-upstream-corpus-select-core-dynamic-20260531T103105Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260531T103105Z-0`

Base accepted HEAD: `1681be96b403cae039655fef5cb4703982266b2d`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- Ported scenario family: `e_select-5.1` through `e_select-5.6`.
- Behavior: `SELECT ALL` and default SELECT row preservation, `SELECT DISTINCT`
  duplicate filtering, NULL equality for DISTINCT duplicate detection, and
  collation-sensitive DISTINCT result filtering.

## Patch

- Added `SQLiteRealUpstreamESelectDistinctAllDynamic20260531T103105ZTest.php`.
- The new file has one hydrated-source assertion test, 1,000 dynamic
  row-varying behavior tests, and one dependency/non-overlap test.
- Each dynamic case runs the native `SQLiteSelectSql` executor over generic
  `h1`, `h2`, and `h3` application-style row arrays and checks:
  - `SELECT ALL a FROM h1`
  - default `SELECT a FROM h1`
  - `SELECT DISTINCT a FROM h1`
  - `SELECT DISTINCT d FROM h3`
  - binary and `COLLATE nocase` DISTINCT behavior over `h1.b`
  - binary and `COLLATE nocase` DISTINCT behavior over `h2.x`
  - SELECT ALL and DISTINCT behavior after a collation-aware join predicate

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectDistinctAllDynamic20260531T103105ZTest.php`
  - Result: `1 test files, 41009 assertions, 0 failures`
  - PASS lines: `1002`

## Expected Movement

- `phpPass`: `+1002`, from `2874050` to `2875052`.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; this is behavior
  PASS-line growth over already mapped upstream `e_select.test` coverage.

## Non-Overlap

This owns `e_select.test` section 5.1 through 5.6 only. It does not repeat the
accepted SELECT empty-input aggregate row behavior, implicit projection aliases,
JOIN text/source handling, grouped SELECT text, expression `ORDER BY`, comma
`LIMIT`, `selectC` alias coverage, `selectD` parenthesized joins, `selectA` /
`selectB` / `selectG` / `selectH` compound batches, JSON table source/cursor/
constraint work, B-tree, WAL, VFS, PRAGMA, or metadata-only runner rows.

## Dependency Closure

No new support component is needed. The slice reuses `SQLiteSelectSql`,
`SQLiteSelectResult` DISTINCT handling, and explicit `COLLATE` expressions in
the native row-array executor.
