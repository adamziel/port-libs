# real-upstream-corpus-trigger-fkey-dynamic-authorizer-reset-20260531

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T061104Z-0`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`

Ported sections:

- `fkey2-18.1..18.11`: foreign-key authorizer callback reads for parent
  inserts, immediate/deferred child inserts, cascade updates, integer-primary-key
  parent probes, and `SQLITE_IGNORE` parent reads.
- `fkey2-19.1..19.4`: prepared `DELETE FROM main WHERE id = ?` constraint
  failure, reset preserving `SQLITE_CONSTRAINT_FOREIGNKEY`, rebind success, and
  clean finalize.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::fkey2AuthorizerCallbackPlan()` for
  the real upstream authorizer callback behavior.
- Added `SQLiteDynamicTriggerForeignKeyPlan::preparedForeignKeyDeleteResetPlan()`
  for prepared statement FK constraint/reset behavior.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicAuthorizerReset20260531Test.php`
  with 7,459 focused assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicAuthorizerReset20260531Test.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicAuthorizerReset20260531Test.php` passed:
  `1 test files, 7459 assertions, 0 failures`.

Non-overlap:

- This does not repeat accepted fkey2 deferred transaction/savepoint/counter
  reset, fkey2-15 counter elision, fkey2-16 self-reference, fkey2-17
  count_changes, fkey2-20 conflict policy, fkey6 defer pragma, fkey7 read-set
  and OR FAIL, fkey8 action journal, trigger9 view/old-row, trigger rowid/variable,
  triggerF conflict-delete, triggerC lifecycle, or source-neutral cleanup. The
  new surface is specifically upstream `fkey2-18` authorizer callback behavior
  and `fkey2-19` prepared statement reset semantics.

Dependency closure:

- No new support component is needed. This reuses the existing bounded
  `SQLiteDynamicTriggerForeignKeyPlan` trigger/FK behavior model and the
  hydrated upstream SQLite test corpus.
