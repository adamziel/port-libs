# PRAGMA integrity index rootpage current-source next124

Slice: `pragma-integrity-index-rootpage-current-source-next`.

This adds `SQLitePragmaIntegrityIndexRootpageCurrentSourceNext`, a focused
current-source pager for copied Application schema preflight. It combines
`PRAGMA index_xinfo(...)` rows for one target index with concrete
`sqlite_schema` rootpage analysis rows for the target index and its table, so
a resume cursor can stay bound to the same database image, catalog snapshot,
index PRAGMA SQL, integrity PRAGMA SQL, and table-valued mode.

Behavior covered:

- schema-qualified, unqualified TEMP-first, and table-valued `index_xinfo`
  target resolution;
- target index/table rootpage rows for ok, pointer-map mismatch,
  wrong b-tree type, beyond-image, and largest-root header mismatch states;
- pagination and source cursor continuation through index metadata into
  rootpage diagnostics;
- stale database, catalog, SQL, integrity SQL, and offset cursor rejection;
- copied `wp_options` expression-index smoke for import preflight.

Evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityIndexRootpageCurrentSourceNextTest.php`
  - `1 test files, 81 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-integrity-index-rootpage-current-source-next.php --self-test`
  - `application-pragma-integrity-index-rootpage-current-source-next self-test passed`

Dashboard delta:

- Adds 81 focused PASS lines in a new lane-scoped test file.
- Expected `phpPass`: `49426 -> 49507`.
- No mapped upstream denominator change claimed.

Dependency closure:

- No new support component is needed. This reuses existing lane-local schema
  catalog, `index_xinfo`, integrity rootpage analysis, pointer-map, and page
  image primitives.

Non-overlap:

- Avoids accepted next121/next122 PRAGMA FK/rootpage pointer-map checks,
  `SQLitePragmaIndexIntegrityForeignKeyCurrentSourceYield`, and generic
  `SQLitePragmaIndexXinfoIntegrityRootYield` root-message pagination. This
  slice adds target index/table rootpage row materialization with source cursor
  validation rather than foreign-key or global rootpage pagination.
