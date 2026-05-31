# real-upstream-corpus-btree-index-dynamic-20260531T034929Z-0

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/btree02.test`

Ported sections:

- `btree02-100`: WITHOUT ROWID table with `PRIMARY KEY(a,ax)`, secondary index `t1a ON t1(a)`, and four-row driver table.
- `btree02-110`: cursor-position preservation while a `t1 CROSS JOIN t3` scan alternates inserted rows and deleted source rows, committing and reopening a transaction inside the cursor loop.

Focused addition:

- `SQLiteBTreeIndexDynamicCorpusPlan::btree02SkipNextCursorMutationCases(1200)`
- `SQLiteRealUpstreamBtree02SkipNextDynamicTest.php`

Non-overlap:

- This targets upstream `btree02.test` skip-next cursor mutation behavior, not accepted page relocation, root collapse, overflow freelist/freeblock release, `index4`, `index5`, `index6`, `index7`, `index8`, `index9`, `indexA`, `bestindex*`, `whereK`, or `whereL/M/N` planner batches.
- The focused file adds 1202 TestRunner cases from the 40-step upstream cursor mutation loop repeated across 30 source-backed batches.

Dependency closure:

- No new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner and models the upstream WITHOUT ROWID primary-key plus secondary-index cursor preservation contract.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtree02SkipNextDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtree02SkipNextDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
