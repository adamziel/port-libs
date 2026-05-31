# real-upstream-corpus-pragma-schema-dynamic-20260531T000118Z-0

- Base accepted HEAD: `8c83cd38b21e6ef37afec24c7a1c1aa06c561658`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/trustschema1.test`
    - `trustschema1-1.100` through `trustschema1-1.540`
    - `trustschema1-2.100` through `trustschema1-2.150`
    - `trustschema1-3.100` through `trustschema1-3.131`
    - `trustschema1-4.1` through `trustschema1-4.2`
- Behavior: added `SQLiteTrustedSchemaPolicy`, a generic schema-snippet policy helper for `PRAGMA trusted_schema` behavior. It models innocuous functions, ordinary deterministic functions, direct-only functions, temp schema exceptions, and schema SQL contexts for generated columns, CHECK constraints, DEFAULT expressions, partial indexes, expression indexes, views, and triggers.
- Focused PHP coverage: added `SQLiteRealUpstreamTrustedSchemaDynamicTest.php` with 1041 focused TestRunner PASS cases and 6245 assertions from real upstream `trustschema1.test` behavior.
- Non-overlap: this ports trusted-schema safety behavior from `trustschema1.test`; it does not repeat accepted `pragma.test` table/index metadata, `pragma3` data-version, `pragma4`/`pragma5` table-valued PRAGMA, `schema.test` invalidation, `schema6` rowid, WAL/pager, JSON, B-tree, SELECT, or source-neutral cleanup clusters.
- Dashboard delta: PASS-line growth only. Mapped denominator remains unchanged because the upstream inventory is already complete in this worktree.
- Dependency closure: no new external support component is needed. The slice reuses the lane-local schema-policy model and generic function metadata; no WordPress-specific API or fixture is introduced.
- Verification:
  - `php -l lanes/libsqlite/src/SQLiteTrustedSchemaPolicy.php` -> no syntax errors
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTrustedSchemaDynamicTest.php` -> no syntax errors
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTrustedSchemaDynamicTest.php` -> `1 test files, 6245 assertions, 0 failures`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`
