# real-upstream-corpus-pragma-schema-dynamic-20260531T090006Z-0

Implemented a focused real upstream PRAGMA/schema dynamic batch from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `pragma-19.1` through `pragma-19.5`

Behavior covered:

- `SQLITE_FCNTL_PRAGMA` handling for `PRAGMA error` default SQL logic error.
- Custom error text from `PRAGMA error='message'`.
- Numeric error-code handling, including `PRAGMA error=7` mapping to `out of memory` and numeric-code-with-message payloads.
- `PRAGMA filename` returning the active VFS database filename.

Expected movement:

- `SQLiteRealUpstreamCorpusPragmaSchemaDynamicFileControl20260531Test.php` adds 1002 focused TestRunner PASS cases: 1000 dynamic behavior variants plus source/non-overlap guard cases.
- This is PASS-line growth over already mapped upstream `pragma.test`; mapped coverage remains `1589 / 1589`.

Non-overlap:

- Does not repeat accepted table_info/index_info/index_xinfo/table_list, data-version, cache-spill, temp-store, page-count, result-shape, runtime-list, schema5/schema6, VFS file-control persistence, VFS sync/write/lock, or trigger/FK count_changes batches.
- The owned section is upstream `pragma.test` `pragma-19.*` VFS file-control PRAGMA behavior only.

Dependency closure:

- No new support component is needed. The slice adds a bounded lane-local PHP helper for PRAGMA file-control dispatch and reuses existing generic VFS/file-control concepts without adding live VFS, external runner, or provider dependencies.
