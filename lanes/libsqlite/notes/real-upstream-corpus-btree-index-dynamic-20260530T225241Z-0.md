# real-upstream-corpus-btree-index-dynamic-20260530T225241Z-0

Status: ready for integration.

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`.
- Upstream sections covered: `index-11.1`, `index-11.2`, and `index-13.1` through `index-13.4`.
- Focus: primary-key autoindex lookup over 50 inserted rows, preserved planner search-count expectation, automatic UNIQUE/PRIMARY KEY index catalog count, quoted/unquoted `DROP INDEX` rejection, `IF EXISTS` rejection for automatic indexes, and post-error insert preservation.
- Focused growth: `2002` distinct TestRunner PASS cases in `SQLiteRealUpstreamBtreeIndexPrimaryAutoindexDynamicTest.php` with `16010` behavior assertions.

Non-overlap:

- Existing accepted lifecycle coverage has static `index.test` assertions and late `index-20.1` through `index-23.1` dynamic coverage.
- Existing accepted B-tree/index corpus already covers `index7`, `indexA`, `index5`, `index8`, `index6`, `index9`, `indexedby`, `indexexpr`, `indexfault`, `autoindex`, index page relocation, overflow freelist release, bulk overflow freeblocks, root collapse, and index-interior merge.
- This batch is limited to earlier upstream `index.test` primary-key automatic-index lookup and automatic-index drop-guard behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLiteIndexLifecyclePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexPrimaryAutoindexDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexPrimaryAutoindexDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses lane-local index lifecycle catalog, primary-key autoindex, and automatic-index guard helpers.
