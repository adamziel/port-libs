# real-upstream-corpus-pragma-schema-dynamic-20260530T214738Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T214738Z-0`

Upstream source:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema4.test`
  - `schema4-1.*`: triggers share names with tables, views, and indexes while remaining separate schema objects.
  - `schema4-2.*`: table rename rewrites dependent table/index records while same-named triggers on a different target remain trigger-namespace objects.

Behavior added:
- Added `SQLiteRealUpstreamPragmaSchemaDynamicSchema4NamespaceTest.php`, a dynamic 250-variant corpus over generic schema object names.
- The batch exercises the existing PHP DDL reparse model for trigger/table/view/index namespace separation, drop survival, same-name recreation, and rename dependency rewrites.

Focused coverage:
- 1,000 focused TestRunner PASS cases.
- 6,250 focused assertions.

Non-overlap:
- This does not repeat prior `pragma.test` table-info/index metadata, `pragma3` data-version, `pragma4` table-valued PRAGMA, `pragma5` function/module list, `schema5` legacy constraint, or `schema6` rowid/WITHOUT ROWID coverage.
- It specifically ports `schema4.test` namespace and rename/drop behavior into the schema DDL reparse surface.

Dependency closure:
- No new support component is needed. The slice reuses lane-local schema records and `SQLiteSchemaDdlReparsePlan`.

Verification:
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema4NamespaceTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema4NamespaceTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
