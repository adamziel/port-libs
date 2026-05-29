# PRAGMA Index Integrity Cursor Current Source Next133

## Scope

- Added `SQLitePragmaIndexIntegrityCursorCurrentSourceNext` for a table-level current-source cursor over `PRAGMA index_list(table)`, each listed index's `PRAGMA index_xinfo(index)`, and sqlite_schema rootpage integrity rows.
- This is distinct from accepted single-index `index_xinfo` rootpage checks and FK-combined current-source cursors: the source id now covers the table index-list SQL plus catalog/database/integrity source, so paged resumes are rejected after index catalog, database image, SQL, integrity mode, or offset drift.
- WordPress path: copied `wp_options` preflight can page all option-table index metadata and rootpage blockers before resuming import/index analysis without ext/sqlite.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexIntegrityCursorCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 74 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/wordpress-pragma-index-integrity-cursor-current-source-next.php --self-test
wordpress-pragma-index-integrity-cursor-current-source-next self-test passed
```

## Non-Overlap

- Avoids accepted PRAGMA `index_xinfo` single-index rootpage current-source next124 and PRAGMA index/FK current-source next121 by covering table-level `index_list` enumeration plus per-index details under one cursor.
- Avoids accepted batch130 schema generated-index reparse and WAL/row-value surfaces.

## Dependency Closure

- No new support component is needed. The slice reuses existing native PHP schema catalog cursors, `SQLitePragmaIntegrityIndexRootpageCurrentSourceNext`, pointer-map/rootpage integrity analysis, and test fixture page assemblers.
