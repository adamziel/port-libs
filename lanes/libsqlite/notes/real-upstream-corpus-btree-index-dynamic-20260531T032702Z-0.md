# real-upstream-corpus-btree-index-dynamic-20260531T032702Z-0

Status: ready for integration.

This slice adds a non-overlapping real upstream B-tree/index planner corpus batch from the hydrated SQLite checkout:

- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereL.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereM.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereN.test`
- Upstream sections covered:
  - `whereL-110`, `whereL-120`, `whereL-122`, `whereL-200/201`, `whereL-300/302`, `whereL-500/530`, and `whereL-700/710`
  - `whereM-1.1.*` through `whereM-1.5.*`
  - `whereN-1.1`
- Focus: WHERE constant propagation, nonconstant expression guards, collation-aware wrong-answer prevention, view/CAST affinity preservation, expression-index preservation, affinity-sensitive equality/LIKE behavior, and interstage join planning.
- Focused addition: `SQLiteBTreeIndexDynamicCorpusPlan::whereLMNConstantPropagationPlannerCases(1000)` plus `SQLiteRealUpstreamBtreeWhereLMNConstantPropagationDynamicTest.php`.

Non-overlap:

- This avoids accepted `whereK` OR factoring, `index7`, `index8`, `index9`, `indexA`, `bestindexA/B/C/D/E/F`, B-tree page relocation, overflow freelist/freeblock, root collapse, index-interior merge, JSON, WAL, VFS, PRAGMA, and source-neutral cleanup clusters.
- It targets real upstream WHERE planner sections that were not part of the prior same-family `index7`, `bestindex6/7`, `bestindexD/E`, or `whereK` dynamic slices.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLMNConstantPropagationDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLMNConstantPropagationDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses lane-local B-tree/index dynamic corpus planner, constant-propagation, affinity, collation, expression-index, and join-planner helpers.
