# real-upstream-corpus-trigger-fkey-dynamic-20260530T194319Z-0

Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
  - `fkey2-1.*`: immediate/deferred FK success and failure checks.
  - `fkey2-4.*`: FK actions may recurse even when recursive triggers are disabled.
  - `fkey2-9.*`: `SET DEFAULT` action behavior.
  - `fkey2-11.*`: `CASCADE` action behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`
  - RETURNING-style row image checks around after-trigger side effects.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerB.test`
  - recursive trigger queue behavior for update triggers.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
  - recursive delete-trigger queue behavior.

## Handoff Delta

- Expanded existing `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTest.php` without deleting accepted coverage.
- Focused PASS lines: `+1626`.
- Focused assertions: `9130`.
- Mapped denominator coverage: unchanged at `1472 / 1589`; this is PHP PASS-line growth only.
- Non-overlap: avoids accepted trigger/FK composite cascade, action-matrix, nocase, OR REPLACE, view/trigger2/RAISE/blob-column/self-reference/current/next batches by focusing this slice on hydrated upstream `fkey2.test` action dynamics plus `trigger1`/`triggerB`/`triggerC` recursive trigger queue and RETURNING/FK image-order behavior.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTest.php`
  - Result: `1 test files, 22041 assertions, 0 failures`, `2720` total PASS lines.
  - New slice contribution inside that file: `+1626` PASS lines and `+9130` assertions.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTest.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: passed, `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`
  - Result: passed.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded native PHP libsqlite trigger/FK plan helpers and the hydrated upstream SQLite `.test` corpus as source truth.
