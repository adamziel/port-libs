# real-upstream-corpus-btree-index-dynamic-20260530T230454Z-0

Session: `port-dev-sqlite-yield-dyn-real-btree-20260530T230454Z`

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index3.test`.
- Upstream sections covered: `index3-1.1` through `index3-1.4`.
- Focus: failed `CREATE UNIQUE INDEX` over duplicate input rows inside a transaction, successful `COMMIT` afterward, preserved duplicate table rows, `PRAGMA integrity_check` success, and no leftover index/catalog residue.
- Focused PASS cases: 1202 TestRunner cases in `SQLiteRealUpstreamBtreeIndex3UniqueRollbackDynamicTest.php`.

Non-overlap:

- Existing accepted `index3` coverage handles quoted string identifiers and compatibility catalog behavior in sections `index3-2.1` through `index3-2.5`.
- This slice does not repeat accepted index7 partial UNIQUE/planner coverage, index8 ORDER/LIMIT, index9 bound partial-index matching, indexA join/affinity coverage, autoindex1/4/5 planner coverage, indexedby enforcement, index expression batches, index5 write-order, B-tree page relocation, overflow freelist release, bulk overflow freeblocks, root collapse, or index-interior merge.

Verification:

- `php -l lanes/libsqlite/src/SQLiteIndexLifecyclePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex3UniqueRollbackDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex3UniqueRollbackDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

No new support component is needed. This reuses lane-local index lifecycle, catalog-residue, transaction-result, integrity, and unique-index failure helpers.
