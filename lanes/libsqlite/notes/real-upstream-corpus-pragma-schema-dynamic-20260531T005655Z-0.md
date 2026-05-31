# real-upstream-corpus-pragma-schema-dynamic-20260531T005655Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test`
- Ported sections: `pragma5-1.0`, `pragma5-1.1`, `pragma5-1.2`, `pragma5-2.0`, `pragma5-2.1`, `pragma5-3.0`, and `pragma5-3.1`.

Lane-local behavior added:

- `SQLiteRealUpstreamCorpusPragmaSchemaDynamicPragma5VirtualRowsTest.php`
- 1,001 focused TestRunner PASS cases.
- 4,753 focused assertions.
- Exercises PRAGMA virtual rowsets for `pragma_function_list`, `pragma_module_list`, and `pragma_pragma_list`, including `PRAGMA table_info(...)`, `PRAGMA table_xinfo(...)`, direct PRAGMA rowsets, table-valued PRAGMA rowsets, builtin function filtering, application-defined `external_%` function filtering, `fts5` module filtering, and `pragma_list` row filtering.

Non-overlap:

- Does not repeat accepted `pragma6.test` integrity/quick_check generated-schema coverage.
- Does not repeat accepted `schema6.test` same-content equivalence coverage.
- Does not repeat accepted attached-schema shadowing or table_list flag coverage.
- Does not add metadata-only fake upstream script IDs.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded `SQLitePragmaSchemaCatalog` virtual PRAGMA rowset support.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicPragma5VirtualRowsTest.php`
  - `1 test files, 4753 assertions, 0 failures`
  - 1,001 PASS lines.
