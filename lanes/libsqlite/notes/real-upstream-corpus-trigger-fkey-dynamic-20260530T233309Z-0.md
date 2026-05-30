# real-upstream-corpus-trigger-fkey-dynamic-20260530T233309Z-0

Base accepted HEAD: `d7c5d7f50d0d0c3f24c91125036d23912559b628`.

Added `SQLiteRealUpstreamCorpusTriggerFkeyDynamic20260530T233309ZTest.php`, a focused trigger/FK dynamic corpus file using real upstream SQLite sources from `/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Upstream sections cited:

- `trigger5.test` `trigger5-1.1`: AFTER DELETE trigger emits undo SQL using OLD values and `quote()`.
- `fkey6.test` `3.3.1..3.3.4`: `PRAGMA defer_foreign_keys` delays RESTRICT so an AFTER DELETE trigger can repair the parent row before outer commit.
- `fkey8.test` counter coverage around implicit deletes and trigger-side replacement during deferred FK checks.
- `trigger7.test` UPDATE OF/selective trigger and trigger lifecycle source coverage, with existing rowid mutation behavior exercised through the shared trigger/FK plan.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamic20260530T233309ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamic20260530T233309ZTest.php`
- Result: `1 test files, 6135 assertions, 0 failures`

Dependency closure: no new support component needed. The slice reuses existing native PHP trigger/FK planning helpers and the hydrated upstream SQLite test cache as source truth.

Non-overlap: this does not add metadata-only suite rows, domain-specific APIs, or dashboard/status counter edits. It avoids the already accepted trigger/FK dynamic nocase, fkey5 collation, fkey6 defer-restrict, fkey8 attached cascade, and statement-preservation files by adding a new dynamic test surface centered on trigger5 undo SQL, fkey6 repair boundaries, fkey8 deferred counter replacement, and rowid mutation behavior.
