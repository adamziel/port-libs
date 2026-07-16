# Real Upstream Corpus Trigger/FK Dynamic

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T224054Z-0`
Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`

Added `SQLiteRealUpstreamTriggerFkeyDynamicYieldCurrentTest.php`, a generic
application-settings corpus test over the existing native recursive trigger,
RETURNING, deferred foreign-key, savepoint, page-image, and WAL-frame helper.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
  - `fkey2-2.*`: deferred foreign keys inside explicit transactions.
  - `fkey2-4.*`: FK actions recurse even when recursive triggers are disabled.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
  - `trigger2-4.*`: cascaded trigger execution and recursive trigger handling.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger3.test`
  - `trigger3-3.*`: rollback-style trigger boundary behavior.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicYieldCurrentTest.php`
  - `1 test files, 1601 assertions, 0 failures`
  - `1601` focused PASS lines.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicYieldCurrentTest.php`
  - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed.

Dependency closure: no new support component is needed. This reuses existing
native PHP trigger/FK/savepoint/WAL corpus helpers and keeps all new scenarios
source-neutral (`app_settings`, `setting_id`, `key_name`, `key_value`).

Non-overlap: this does not add WordPress-shaped APIs, metadata-only admission
records, fake upstream script IDs, or another JSON/WAL/B-tree accepted cluster.
