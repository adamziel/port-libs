real-upstream-corpus-trigger-fkey-dynamic-20260531T061507Z-0

Base accepted HEAD: 2139c8ce030e83a04c23079c17d6da80f20ffd83

Scope:
- Added a focused upstream-backed PHP corpus for `fkey5.test` missing-parent and table-target `PRAGMA foreign_key_check` behavior.
- Upstream source sections cited: `fkey5.test` tests 9.*, 10.3, 13.0-13.12.
- Non-overlap: this does not repeat the accepted trigger/FK action matrix in `SQLiteRealUpstreamTriggerFkeyDynamicTest.php` or the accepted fkey5 parent-collation/without-rowid collation matrix in `SQLiteRealUpstreamTriggerFkeyDynamicCheckCorpusTest.php`; it focuses on missing parent tables, target filtering, without-rowid result rowids, and table-valued target-equivalent rows.

Focused evidence:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyMissingParentDynamicTest.php`
- Result: `1 test files, 3505 assertions, 0 failures`
- PASS-line movement: 1001 focused TestRunner PASS cases.

Dependency closure:
- No new support component is needed. The slice reuses the existing generic `SQLitePragmaForeignKeyCheck` implementation and hydrated upstream source files under `/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Root harness:
- Not run; isolated micro-slice.
