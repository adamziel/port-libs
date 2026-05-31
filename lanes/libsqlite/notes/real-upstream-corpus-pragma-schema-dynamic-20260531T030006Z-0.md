## real-upstream-corpus-pragma-schema-dynamic-20260531T030006Z-0

Base accepted HEAD: 57904efd88f87abfad6d70c753ea59660958850e.

Implemented dynamic PRAGMA state coverage for real upstream `pragma.test`
`pragma-14.1` through `pragma-14.6uc` page-count behavior and the coupled
pager `max_page_count` limit behavior. The source change extends
`SQLitePragmaDynamicSchemaState` with schema-local `page_count` and
`max_page_count` parsing/execution while preserving existing cache,
freelist, schema_version, and user_version behavior.

Focused corpus added:

- `SQLiteRealUpstreamCorpusPragmaSchemaDynamicPageCount20260531T030006ZTest.php`
  covers 601 TestRunner cases and 5301 assertions against main/temp/attached
  schema page counts, uppercase PRAGMA/schema parsing, read-only page_count
  writes, max_page_count assignment, schema isolation, and clamping below the
  current page count.
- Existing `SQLiteRealUpstreamPragmaSchemaStateDynamicTest.php` was updated for
  parser compatibility and still passes with 2330 assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaDynamicSchemaState.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaStateDynamicTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicPageCount20260531T030006ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicPageCount20260531T030006ZTest.php`
  passed: 1 file, 5301 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaStateDynamicTest.php`
  passed: 1 file, 2330 assertions, 0 failures.
- `SQLiteNoWordPressSpecificApiTest.php` is not present in this worktree.

Dependency closure: no new support component is needed. The slice reuses the
existing dynamic PRAGMA state helper.

Expected movement: +601 focused TestRunner PASS cases if accepted. Mapped
denominator remains complete; this is PASS-line/assertion growth only.
