# real-upstream-corpus-trigger-fkey-dynamic-20260531T005911Z-0

Base accepted HEAD: `e307a7e809c115b0b6fbc55bff5508bf94d58480`.

Owned upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- `fkey2-2.61..2.75`

Behavior ported:

- A failed multi-row `INSERT INTO node SELECT parent, 3 FROM leaf` statement
  rolls back the statement transaction when duplicate parent ids hit the
  `node.nodeid` unique constraint.
- The failed statement restores the deferred FK violation counter to the value
  it had before the statement opened.
- The outer transaction remains open after the blocked commit, and the same
  child rows can still be repaired by inserting distinct parent rows.
- Duplicate leaf references are repaired by one distinct parent key, matching
  the upstream `SELECT DISTINCT parent FROM leaf` follow-up.

Changed lane files:

- `lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicStatementCounterResetTest.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T005911Z-0.md`

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicStatementCounterResetTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicStatementCounterResetTest.php`
  - `1 test files, 8404 assertions, 0 failures`
  - `7441` focused PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

Local selected movement:

- Adds `7441` focused PASS lines and `8404` focused assertions.
- `lane-status.json` local selected `phpPass` moves from `1396749` to
  `1404190` if accepted.

Dependency closure:

- No new support component is needed. This reuses the existing native trigger
  and deferred FK dynamic planner machinery.

Non-overlap:

- This slice does not repeat accepted fkey2-12 NOCASE repair, fkey2-13 REPLACE
  composite parent FK behavior, trigger temp/view batches, or non-trigger
  SQL/JSON/WAL/B-tree clusters. It owns only the adjacent real upstream
  deferred-counter reset block in `fkey2.test`.
