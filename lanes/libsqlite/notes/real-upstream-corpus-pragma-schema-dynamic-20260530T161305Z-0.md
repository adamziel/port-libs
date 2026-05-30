# Real upstream corpus: PRAGMA schema dynamic

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T161305Z-0`

Base accepted HEAD: `8bf0d9f81b29a5601901bb34dfd730670ed39bbc`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
- Focused upstream scenarios: `pragma4-4.2.2` through `pragma4-4.2.6` table-valued `pragma_table_info`, `pragma4-4.3.2` through `pragma4-4.3.6` `pragma_index_info`, `pragma4-4.4.1` through `pragma4-4.4.6` `pragma_index_list`, `pragma4-4.5.1` through `pragma4-4.5.5` `pragma_foreign_key_list`, `pragma4-6.0` through `pragma4-6.3` `pragma_table_list` with invalid view SQL, and `pragma4-7.1` through `pragma4-7.3` table-valued PRAGMA row use in joins.

Ported PHP coverage:

- Added `SQLiteRealUpstreamPragmaSchemaDynamicTest.php` with 62 focused PASS cases and 549 assertions.
- Uses generic application schema names (`app_parent`, `app_child`, `app_parent_aux`) rather than application-specific API or fixture names.
- Covers dynamic main/temp/attached schema resolution, schema-pinned table-valued PRAGMA calls, detached attached-schema behavior, cursor snapshot preservation, invalid view SQL table-list tolerance, and FK metadata disappearance after the referenced attached schema is detached.

Non-overlap:

- Does not touch bulk suite denominator records, numbered current-next helper consolidation, source API names, PRAGMA optimize, PRAGMA `schema_version`/`user_version`, or existing index/foreign-key integrity pagination helpers.
- This is countable as focused PHP PASS-line growth only: `phpPass` should move from `188377` to `188439` after integration. It does not claim mapped denominator growth.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTest.php`
  - `1 test files, 549 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local schema catalog, attached catalog, and PRAGMA row cursor behavior.
