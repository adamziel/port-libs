# Real Upstream Corpus Trigger/FK Dynamic

Micro-slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T175106Z-0`

Accepted base: `e35ca6042fb93bdd5d8709bbc17efa06e6d9c2b0`

Added `SQLiteRealUpstreamCorpusTriggerFkeyDynamicTest.php` with 42 focused
TestRunner PASS cases and 600 assertions. The test ports generic behavior from
real hydrated upstream SQLite corpus files:

- `fkey2.test`: deferred FK transactions (`fkey2-2.*`), `SET NULL` and
  `SET DEFAULT` actions (`fkey2-9.*`), `CASCADE` actions (`fkey2-11.*`), and
  `RESTRICT` behavior (`fkey2-12.*`).
- `fkey6.test`: `defer_foreign_keys` does not defer `RESTRICT`.
- `trigger2.test`: cascaded and recursive trigger execution (`trigger2-4.*`).
- `triggerG.test`: recursive trigger `OP_Once` behavior (`triggerG-100`).
- `triggerE.test`: malformed trigger definitions are rejected.

The assertions use generic application table and column names only. No new
support component is needed; the batch reuses existing native trigger/FK
RETURNING and recursive deferred FK helper behavior.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

Root harness: not run - isolated micro-slice.
