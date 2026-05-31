# real-upstream-corpus-trigger-fkey-dynamic-affinity-20260531T024711Z

Base accepted HEAD: `47e43ea345c857243140b52082e7a664319c5aa0`.

Owned upstream corpus:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test`
- `fkey8-5.0..5.3`: deferred foreign-key counters satisfied by late parent insert and parent update, with integrity check clean after commit.

Patch summary:

- Added `SQLiteDynamicTriggerForeignKeyPlan::deferredAffinityParentSatisfaction()` for deferred child rows whose parent rows are inserted or updated before commit.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicAffinityDeferredTest.php` with 110 dynamic cases across late parent insert, parent update, and missing-parent deferred failure paths.
- The helper models parent affinity comparison for numeric/text key equivalence and returns generic application table/key diagnostics only.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicAffinityDeferredTest.php`
- Result: `1 test files, 4077 assertions, 0 failures`
- Distinct TestRunner PASS cases: `4072`
- Expected lane-local `phpPass` movement: `1726669 -> 1730741` (`+4072`)
- Trigger/FK family check: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkey*.php`
- Result: `69 test files, 508791 assertions, 0 failures`
- PHP lint: `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` and `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicAffinityDeferredTest.php`
- Result: no syntax errors.
- Guard: `SQLiteNoWordPressSpecificApiTest.php` is not present in this worktree.
- Whitespace: `git diff --check -- lanes/libsqlite` passed.

Non-overlap:

- Does not repeat accepted fkey8 action-journal, fkey8 implicit-delete counter, fkey8 attached cascade, trigger/FK quoted cascade, fkey7 authorizer/OR FAIL, fkey6 deferred restrict repair, or fkey5 foreign_key_check collation coverage.
- This slice is limited to fkey8 section 5 deferred affinity satisfaction and negative deferred-commit failure.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP trigger/FK plan surface and upstream-cache `.test` source citations.

Follow-up:

- Before broad batch acceptance, run the trigger/upsert related family gate because this edits a shared trigger/FK helper.
