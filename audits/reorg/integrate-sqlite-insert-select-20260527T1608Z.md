# Integrate libsqlite INSERT SELECT

- Integrated slice: `insert-select-current`
- Original handoff base: `1fc5144a5bdf7ae89431df340dccb22064782068`
- Accepted integration base: `14b865a9515cbe408ee3c9beb17d0aca80df8e4d`
- Handoff: `.tmux-team/tmp/handoff-candidates/port-dev-sqlite-insert-sel-20260527T154815Z.ready`
- Patch: `.tmux-team/tmp/handoff-candidates/port-dev-sqlite-insert-sel-20260527T154815Z.patch`
- Patch sha256: `0c70ab4809f8e10382ac5e121b73a665321c29505ae9a4f0c0d3cfff10eb870c`
- Patch bytes: `62407`

## Review

- Scope stayed under `lanes/libsqlite/**`.
- The patch applied cleanly for code, tests, examples, and notes; only
  `lanes/libsqlite/lane-status.json` conflicted with the accepted PRAGMA
  `locking_mode` metadata.
- Conflict resolution preserved the accepted `locking_mode` evidence and
  updated the current lane status to the INSERT SELECT slice.
- Added lane-local `SQLiteInsertSelectSql` execution plus a WordPress
  `wp_options` archive/import staging smoke.

## Verification

- `php -l lanes/libsqlite/src/SQLiteInsertSelectSql.php`: pass
- `php -l lanes/libsqlite/tests/SQLiteHeaderTest.php`: pass
- `php -l lanes/libsqlite/examples/wordpress-insert-select-current.php`: pass
- `jq empty lanes/libsqlite/lane-status.json`: pass
- `git diff --check -- lanes/libsqlite`: pass
- `php lanes/libsqlite/examples/wordpress-insert-select-current.php`: pass
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php`: `1 test files, 9615 assertions, 0 failures`
- `TMPDIR="$PWD/.tmp-root" php -d memory_limit=512M tools/run-tests.php`: `215 test files, 34895 assertions, 0 failures`

## Decision

Accepted for source integration. This moves libsqlite focused coverage from
`9570` to `9615` assertions and root coverage from `34850` to `34895`
assertions with no root failures.
