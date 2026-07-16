# Integrate libsqlite PRAGMA locking_mode

- Integrated slice: `pragma-locking-mode-current`
- Accepted base: `1fc5144a5bdf7ae89431df340dccb22064782068`
- Handoff: `.tmux-team/tmp/handoff-candidates/port-dev-sqlite-lockmode-20260527T155049Z.ready`
- Patch: `.tmux-team/tmp/handoff-candidates/port-dev-sqlite-lockmode-20260527T155049Z.patch`
- Patch sha256: `39e6423a1764a3bde1516861ba11326eec90c842b7aded57286d54c061f19fbd`
- Patch bytes: `88098`

## Review

- Scope stayed under `lanes/libsqlite/**`.
- Added lane-local `SQLitePragmaLockingMode` handling and connected it to the PRAGMA snapshot/preflight surface.
- Added focused assertions for current/default `locking_mode`, switching to `exclusive`, restoring `normal`, and temp schema reporting.
- Updated libsqlite manifest/status and notes with accepted, bounded evidence only.

## Verification

- `php -l lanes/libsqlite/src/SQLitePragmaLockingMode.php`: pass
- `php -l lanes/libsqlite/src/SQLitePragmaSnapshot.php`: pass
- `php -l lanes/libsqlite/tests/SQLiteHeaderTest.php`: pass
- `php -l lanes/libsqlite/examples/wordpress-pragma-preflight.php`: pass
- `jq empty lanes/libsqlite/lane-status.json lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`: pass
- `git diff --check -- lanes/libsqlite`: pass
- `php lanes/libsqlite/examples/wordpress-pragma-preflight.php /tmp/libsqlite-lockmode-smoke.sqlite`: pass
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php`: `1 test files, 9570 assertions, 0 failures`
- `TMPDIR="$PWD/.tmp-root" php -d memory_limit=512M tools/run-tests.php`: `215 test files, 34850 assertions, 0 failures`

## Decision

Accepted for source integration. This moves libsqlite focused coverage from `9516` to `9570` assertions and root coverage from `34796` to `34850` assertions with no root failures.
