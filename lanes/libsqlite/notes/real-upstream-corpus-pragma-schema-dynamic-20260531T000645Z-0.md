# real-upstream-corpus-pragma-schema-dynamic-20260531T000645Z-0

Base accepted HEAD: `88eb6ac3e2ad25d5a4756e5a167672b605fd3e97`.

Added `SQLiteRealUpstreamPragmaCacheSpillDynamicTest.php` with 1002 focused
TestRunner PASS cases and 7506 behavior assertions. Fixed
`SQLitePragmaRuntimeState` so unqualified `PRAGMA cache_spill=ON` applies to
each attached schema using that schema's own cache-size threshold instead of
copying the main schema threshold.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma2.test`
  sections `pragma2-4.1` through `pragma2-4.8`: default enabled
  `cache_spill`, unqualified ON/OFF application to all schemas, numeric
  threshold lock promotion, and attached-database inheritance.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma2.test`
  sections `pragma2-5.1` through `pragma2-5.3`: YES/NO and negative
  parenthesized `cache_spill` forms.

Non-overlap:

- Extends PRAGMA/schema dynamic coverage beyond accepted schema shadowing,
  schema-state cache/default/freelist/version, PRAGMA fault integrity, schema4
  name-collision, and VFS mmap pragma batches.
- Does not add metadata-only admission rows, fake upstream script ids,
  WordPress-specific APIs, or dashboard-only movement.

Dependency closure:

- No new support component needed. The slice reuses the existing
  `SQLitePragmaRuntimeState` bounded runtime state helper.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaCacheSpillDynamicTest.php`
  passed: `1 test files, 7506 assertions, 0 failures` with 1002 PASS lines.
