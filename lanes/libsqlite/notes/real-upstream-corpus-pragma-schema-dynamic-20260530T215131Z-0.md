# real-upstream-corpus-pragma-schema-dynamic-20260530T215131Z-0

Implemented a real upstream PRAGMA runtime-list corpus batch from SQLite upstream:

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test`
- Upstream scenarios:
  - `pragma5-1.0`: `PRAGMA table_info(pragma_function_list)` exposes the virtual-table shaped runtime function-list columns.
  - `pragma5-1.1`: builtin functions such as `upper` appear as builtin runtime-list rows.
  - `pragma5-1.2`: application-defined functions appear as non-builtin runtime-list rows.
  - `pragma5-2.0` and `pragma5-2.1`: `pragma_module_list` exposes one-column module rows including `fts5`.
  - `pragma5-3.0` and `pragma5-3.1`: `pragma_pragma_list` exposes one-column pragma-name rows including `pragma_list`.

Changed files:

- `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaRuntimeListDynamicTest.php`
- `lanes/libsqlite/lane-status.json`

Behavior delta:

- Added 1,000 distinct focused TestRunner PASS cases and 9,250 assertions over runtime PRAGMA rowsets.
- The corpus varies generic application-defined scalar, window, and aggregate functions, virtual-table modules, and collations across 250 variants.
- Assertions cover direct PRAGMA and table-valued `pragma_*()` parity, sorted rowsets, virtual-table `table_info` column metadata, builtin versus external function rows, module and pragma-list membership, collation sequence ordering, and malformed runtime-list row guards.
- Expected selected `phpPass` movement if accepted: `844276 -> 845276`. Mapped denominator coverage remains `1589 / 1589`.

Red-first evidence:

- Initial focused run failed 250 generated cases because the module/pragma-list closure did not capture `$variant`.
- After fixing the capture, the focused corpus passed.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaRuntimeListDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPragmaRuntimeListDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaRuntimeListDynamicTest.php`
  - `1 test files, 9250 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `jq empty lanes/libsqlite/lane-status.json && git diff --check -- lanes/libsqlite`
  - passed with no output

Non-overlap:

- This does not repeat accepted PRAGMA schema first/second/third/fourth/fifth-thousand table/index/FK/schema metadata batches, schema3/cache invalidation, schema4 name-collision, schema5/schema6 CREATE TABLE layout coverage, data-version coverage, or table-list flag batches.
- This owns upstream `pragma5.test` runtime introspection rowsets at batch scale, specifically custom function rows, module/pragma/collation list ordering, direct/table-valued parity, and runtime-row guard behavior.
- It adds no generated fake upstream script ids, metadata-only admission rows, WordPress-specific APIs, or `wp_*` scenarios.

Dependency closure:

- No new support component is required. This reuses `SQLitePragmaSchemaCatalog` runtime function/module/collation/pragma list support.
