# real-upstream-corpus-pragma-schema-dynamic-20260531T071920Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T071920Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - section `5.0`: `PRAGMA table_info` keeps default expressions while ignoring SQL comments around the declaration.
  - sections `6.0` and `7.3`: table-valued schema PRAGMAs act as row sources in joins.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test`
  - sections `1.0` through `3.1`: virtual PRAGMA tables expose `table_info` shapes and runtime rows for function/module/pragma lists.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - sections `8.1.1` through `8.1.16`: `schema_version` assignment, defensive no-op behavior, and attached schema-version state.

Focused PHP coverage:

- Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicRemainder20260531Test.php`.
- The file contains 1,251 focused TestRunner PASS cases over 250 dynamic generic schema/runtime variants plus a source-citation/non-overlap case.
- Assertions cover PRAGMA virtual table shapes, table-valued PRAGMA function/module/list rows, table-valued PRAGMA join inputs, FK/table-info cross-lookups, default token preservation with SQL comments, defensive `schema_version`, attached schema `schema_version`, and generic application file state.

Non-overlap:

- This slice avoids accepted PRAGMA schema3/store-mode/page-count/object namespace/result-shape/version-list/join-xinfo batches and does not touch source-neutral production API names.
- It also avoids JSON, B-tree, WAL/VFS, SELECT, trigger/FK, encoding, and suite-evidence clusters.

Dependency closure:

- No new support component is needed. The tests reuse lane-local `SQLitePragmaSchemaCatalog`, `SQLitePragmaRuntimeState`, `SQLiteSchemaRecord`, and `SQLiteSelectSql` primitives.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicRemainder20260531Test.php`
  - `1 test files, 6754 assertions, 0 failures`
  - 1,251 focused TestRunner PASS cases.
