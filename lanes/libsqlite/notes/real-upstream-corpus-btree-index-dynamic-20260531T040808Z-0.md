# real-upstream-corpus-btree-index-dynamic-20260531T040808Z-0

Base accepted HEAD: `86b40e76030ee95766e1bca45c19abb4f5a3c27f`.

Added a real upstream `index.test` tail schema/affinity corpus slice for
`test/index.test` sections `index-14.1` through `index-23.1`.

Covered behavior:

- mixed-affinity indexed `ORDER BY` and range predicates from `index-14.*`;
- numeric-looking text ordering and `typeof()` filtering from `index-15.*`;
- duplicate automatic-index constraint coalescing and autoindex names from
  `index-16.*` and `index-17.*`;
- reserved `sqlite_*` object-name errors from `index-18.*`;
- single-index conflict-policy validation from `index-19.*`;
- temp-index schema scope from `index-21.*`;
- expression-index row behavior and `REINDEX` preservation from `index-22.*`
  and `index-23.*`.

Focused growth:

- `SQLiteRealUpstreamBtreeIndexTailSchemaAffinityDynamicTest.php` adds 1,001
  focused TestRunner PASS cases.
- Focused assertion count: 15,585 assertions.
- Expected dashboard classification: PASS-line growth only; mapped denominator
  remains complete.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexTailSchemaAffinityDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexTailSchemaAffinityDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexTailSchemaAffinityDynamicTest.php`
  - `1 test files, 15585 assertions, 0 failures`

Non-overlap:

- Does not repeat accepted B-tree page relocation, root collapse, overflow
  freelist release, freeblock materialization, JSON table source/cursor,
  grouped SELECT text, SQL subqueries, expression `ORDER BY`, VFS writer/sync,
  WAL byte truncation, rollback-journal apply/commit, or Unicode GLOB clusters.

Dependency closure:

- No new support component needed. The slice reuses the existing
  `SQLiteBTreeIndexDynamicCorpusPlan` corpus-plan surface and focused
  TestRunner harness.
