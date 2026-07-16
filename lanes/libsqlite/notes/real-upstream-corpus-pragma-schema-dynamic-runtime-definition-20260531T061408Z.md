# real-upstream-corpus-pragma-schema-dynamic-runtime-definition-20260531T061408Z

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T061408Z-0`

Base accepted HEAD: `2139c8ce030e83a04c23079c17d6da80f20ffd83`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema2.test`
- Sections ported:
  - `schema2-6.1` through `schema2-6.4`: adding a user function does not expire an already prepared statement; deleting a used function invalidates dependent prepared statements.
  - `schema2-7.1` through `schema2-7.4`: adding a collation is stable; deleting a used collation invalidates dependent prepared statements.
  - `schema2-8.1` and `schema2-8.3`: setting an authorizer invalidates prepared `sqlite_schema` statements.
  - `schema2-11.1` through `schema2-11.8`: active prepared statements make function/collation delete or replacement return `SQLITE_BUSY` and leave the active statement usable.

## Implementation

- Added `SQLiteRuntimeDefinitionInvalidationPlan`, a generic native PHP planner for runtime SQL function, collation, and authorizer invalidation behavior.
- Added `SQLiteRealUpstreamPragmaSchemaRuntimeDefinitionDynamicTest.php` with 1502 distinct TestRunner PASS cases and 9005 focused assertions.
- The test uses generic `settings_runtime_*` table names and `normalize_runtime_*` / `runtime_collation_*` runtime definitions only.

## Non-Overlap

This does not repeat accepted pragma table-info/index metadata, schema4 object-name collision, schema6 equivalence, page-count/max-page-count, data-version, journal-state, rollback/reparse cursor stability, or table-valued PRAGMA batches. It owns the runtime definition invalidation and active-statement busy behavior from `schema2.test`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRuntimeDefinitionInvalidationPlan.php` -> no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaRuntimeDefinitionDynamicTest.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaRuntimeDefinitionDynamicTest.php` -> `1 test files, 9005 assertions, 0 failures`

## Dependency Closure

No new external support component is needed. This reuses the lane-local PHP test runner and models SQLite runtime definition invalidation as a bounded native PHP support primitive for prepared-statement reprepare behavior.
