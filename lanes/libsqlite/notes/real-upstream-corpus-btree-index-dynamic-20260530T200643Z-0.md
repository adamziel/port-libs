# Real upstream corpus: autoindex5 coroutine planner batch

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/autoindex5.test`
- Covered upstream scenarios:
  - `autoindex5-1.0` through `autoindex5-1.1`: automatic covering index over the `debian_cve` coroutine view for `bug_name=?`.
  - `autoindex5-2.1`: scalar subquery over a compound view preserves duplicate aggregate input.
  - `autoindex5-2.2`: nested coroutine subquery resolves `rowid` against the base table rowid.
  - `autoindex5-3.1` through `autoindex5-3.3`: DISTINCT coroutine subqueries inside `IN` and scalar predicates preserve outer OR/index probe results.

## Delta

- Added `SQLiteBTreeIndexDynamicCorpusPlan::autoindex5CoroutineSubqueryCases()` with 1000 real upstream-backed dynamic cases.
- Wired the batch into `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`.
- Focused PASS-case delta: +1000 distinct `TestRunner` PASS cases.
- Focused assertion delta in the edited file is approximately +11000 behavior assertions.
- Non-overlap: this does not repeat the accepted `index3`, `index5`, `index6`, `index7`, `index9`, `indexA`, `btree01`, `btree02`, B-tree page relocation, overflow freelist, expression-index range-cost, JSON table, WAL/VFS writer, or source-neutral cleanup clusters. This slice is limited to `autoindex5.test` coroutine/automatic-index planner behavior.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed: `1 test files, 83585 assertions, 0 failures`.

## Dependency closure

No new support component is needed. The slice reuses lane-local B-tree/index planner corpus structures and adds bounded native PHP representations of upstream automatic-index and coroutine-subquery behavior.
