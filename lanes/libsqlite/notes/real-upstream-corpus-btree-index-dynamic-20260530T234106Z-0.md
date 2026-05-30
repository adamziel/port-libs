# real-upstream-corpus-btree-index-dynamic-20260530T234106Z-0

Status: ready for integration.

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index3.test`.
- Upstream section covered: `index3-99.1`.
- Focus: `writable_schema` corruption of an index `sqlite_schema.sql` record, defensive-mode bypass precondition, schema reparse on `DROP INDEX`, malformed schema diagnostic binding to the corrupt index name, and blocked drop behavior after reopen.
- Focused PASS cases: 1203 TestRunner cases in `SQLiteRealUpstreamBtreeIndex3MalformedSchemaDynamicTest.php`.

Non-overlap:

- Existing accepted `index3` coverage handles duplicate UNIQUE rollback in `index3-1.1` through `index3-1.4` and quoted identifier compatibility in `index3-2.1` through `index3-2.5`.
- This does not repeat accepted index7 partial UNIQUE/planner coverage, index8 ORDER/LIMIT, index9 bound partial-index matching, indexA join/affinity coverage, autoindex planner coverage, indexedby enforcement, index expression batches, index5 write-order, B-tree page relocation, overflow freelist release, bulk overflow freeblocks, root collapse, or index-interior merge.

Verification:

- `php -l lanes/libsqlite/src/SQLiteIndexLifecyclePlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex3MalformedSchemaDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex3MalformedSchemaDynamicTest.php` -> 1 test file / 21610 assertions / 0 failures / 1203 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> 1 test file / 3 assertions / 0 failures.
- `git diff --check -- lanes/libsqlite` -> clean.

Dependency closure:

No new support component is needed. This reuses lane-local index lifecycle, schema-reparse error, writable-schema corruption, and DROP INDEX guard helpers.
