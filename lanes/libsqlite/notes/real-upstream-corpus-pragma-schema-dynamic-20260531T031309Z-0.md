# real-upstream-corpus-pragma-schema-dynamic-20260531T031309Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`
- Covered upstream sections: `pragma3-100` through `pragma3-102`, `pragma3-110` through `pragma3-195`, `pragma3-200` through `pragma3-430`, and `pragma3-510A/B` through `pragma3-520A/B`.

Patch:

- Added `SQLiteRealUpstreamPragmaDataVersionConnectionLocalTest.php`.
- Adds 1001 focused TestRunner PASS cases and 5505 behavior assertions.
- Exercises ignored writes to `PRAGMA data_version`, connection-local values, other-connection commit advancement, uncommitted-writer invisibility, separate-process/shared-cache/WAL-equivalent observer semantics, and empty write transaction stability.
- Non-overlap: avoids the existing `SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php` coverage for `pragma4.test`, `pragma5.test`, and schema cache invalidation by targeting upstream `pragma3.test` data-version behavior only.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaDataVersionConnectionLocalTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaDataVersionConnectionLocalTest.php` => `1 test files, 5505 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. The existing bounded `SQLitePragmaDataVersionTracker` is reused to model the upstream connection-local observable behavior without shelling out to SQLite.
