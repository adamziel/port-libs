# real-upstream-corpus-json1-jsonb-dynamic-20260601T072224Z-0

Base accepted HEAD: `80f68770eb80ae23d626c7edafcf276d6f4e32ec`

## Upstream Source Truth

- Hydrated upstream file:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Ported upstream sections:
  - `json101-5.10`: `json_tree()`/`json_each()` container `value` rows keep the JSON subtype so `json_insert('{}','$.a',value)` inserts structure.
  - `json101-5.11`: scalar strings that look like JSON remain text when inserted through `json_insert()`.

## Patch

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson101ValueSubtypeSelectSqlDynamic20260601Test.php`.
- The new coverage exercises the upstream `value` subtype behavior through parser-level `SQLiteSelectSql` row dispatch over `json_tree()` and `json_each()` sources, for both text JSON and JSONB inputs.
- The generated dynamic cases use generic `app_json_docs` rows and cover root container rows, object-member container rows, root scalar string rows, and object-member scalar string rows.

## Non-Overlap

This slice intentionally avoids the existing direct-helper coverage in:

- `SQLiteRealUpstreamJson101ValueSubtypeDynamicTest.php`
- `SQLiteRealUpstreamJson101TableValueInvariantDynamicTest.php`
- `SQLiteRealUpstreamJson101HiddenSourceSelectDynamic20260601Test.php`

Those tests cover direct `SQLiteJsonTree`/`SQLiteJsonEach` subtype rows or projection of JSON table columns. This slice covers the parser/executor path where JSON table column values are fed directly into JSON functions inside `SQLiteSelectSql` expressions.

## Evidence

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101ValueSubtypeSelectSqlDynamic20260601Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101ValueSubtypeSelectSqlDynamic20260601Test.php`
  - `1 test files, 36009 assertions, 0 failures`
  - `1002` focused TestRunner PASS cases

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP support:

- `SQLiteSelectSql`
- `SQLiteJsonB`
- `SQLiteJsonCanonical`
- JSON table cursor/source handling already wired into the SELECT executor
- JSON mutation and quote/type function dispatch already available through SELECT expressions
