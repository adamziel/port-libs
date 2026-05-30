# real-upstream-corpus-btree-index-dynamic-20260530T223039Z-0

Status: ready for integration.

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`.
- Upstream sections covered: `index-20.1`, `index-20.2`, `index-21.1`, `index-21.2`, `index-22.0`, `index-23.0`, and `index-23.1`.
- Focus: quoted `DROP INDEX`, TEMP index scope rejection/admission, expression indexes with `IF NOT EXISTS`, unique expression indexes over `GLOB`, unique `TYPEOF()` expression indexes, and REINDEX preservation.
- Focused PASS growth: `1002` TestRunner PASS cases in `SQLiteRealUpstreamBtreeIndexLateDynamicTest.php`.

Non-overlap:

- Existing accepted `index.test` lifecycle coverage handles earlier catalog, duplicate-key, numeric-affinity, constraint, composite-order, and wide-index sections.
- This avoids accepted index7 partial planner, index9 bound partial-index, indexA join/affinity, index4 create-index stress, index5 write-order, index8 order/limit, index expression DDL guard, autoindex, indexfault, B-tree page relocation, overflow freelist release, root collapse, and index-interior merge coverage.

Verification:

- `php -l lanes/libsqlite/src/SQLiteIndexLifecyclePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexLateDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexLateDynamicTest.php` -> `1 test files, 18291 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses lane-local index lifecycle, catalog, expression-index, temp-schema, and REINDEX behavior helpers.
