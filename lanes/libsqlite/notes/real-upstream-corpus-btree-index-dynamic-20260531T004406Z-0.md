# real-upstream-corpus-btree-index-dynamic-20260531T004406Z-0

Status: ready for integration.

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index6.test`.
- Upstream sections covered: `index6-15.2` through `index6-19.2`.
- Focus: late partial-index theorem-prover regressions around `BETWEEN` and `IS FALSE`, NOCASE comparison direction, GLOB partial-index integrity after `REPLACE`, partial UNIQUE predicates involving `NULL`, and RIGHT JOIN no-match loop behavior.
- Focused growth: `1003` focused TestRunner PASS lines in `SQLiteRealUpstreamBtreeIndex6LatePartialDynamicTest.php`, with `12206` assertions.

Non-overlap:

- This avoids accepted index6 partial join/update rows, index7 partial unique/stat batches, index4/index5/index8/index9/indexA dynamic batches, autoindex batches, index expression batches, accepted B-tree page relocation/root collapse/overflow freelist release/freeblock materialization, and current source-neutral cleanup work.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex6LatePartialDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex6LatePartialDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses lane-local B-tree/index dynamic corpus planning, partial-index theorem-prover summaries, collation, GLOB, replace-integrity, and RIGHT JOIN result helpers.
