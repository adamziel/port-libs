# VDBE Sorter DISTINCT Collation Current Source Next106

## Behavior

- Adds `SQLiteVdbeSorterDistinctCurrentSourceCursor`, a small current-source
  wrapper around the existing aggregate DISTINCT sorter cursor.
- `refresh()` rebuilds the DISTINCT sorter only when the source token changes
  and reseeks to the current DISTINCT key using SQLite affinity/collation
  comparison, so changed copied rows do not replay stale duplicates or rewind a
  VDBE current/next loop.
- Covered NOCASE, RTRIM, BINARY, numeric affinity, NONE affinity, composite
  keys, filter changes, inserted lower/greater keys, EOF, and invalid refreshed
  sources.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeSorterDistinctCollationCurrentSourceNext106Test.php`
  - `1 test files, 66 assertions, 0 failures`
  - 66 focused PASS lines.
- `php lanes/libsqlite/examples/application-vdbe-sorter-distinct-collation-current-source-next106.php --self-test`
  - `application-vdbe-sorter-distinct-collation-current-source-next106 self-test passed`

## Status Delta

- `lane-status.json` `phpPass`: `40990 -> 41056`.
- `phpFail`: `0`.
- Mapped upstream coverage unchanged. This is focused PHP behavior coverage for
  an already mapped VDBE sorter/aggregate DISTINCT family, not a newly mapped
  upstream inventory row.

## Non-Overlap

This avoids accepted JSON aggregate DISTINCT/ORDER behavior, VDBE sorter
distinct-group reset behavior, UTF-16 GLOB/RTRIM comparison-only coverage,
SQL expression ORDER BY, JSON table source/constraint work, B-tree/VFS/WAL/
pager/PRAGMA current-source clusters, and suite-runner admission. The narrower
surface is VDBE DISTINCT sorter current/next refresh after a current-source
token changes, with collation-aware reseek.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local VDBE
sort comparison and aggregate DISTINCT cursor primitives.
