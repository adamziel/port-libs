# libsqlite Insert Or Replace Conflict Integration

- Accepted handoff: `.tmux-team/tmp/handoff-candidates/port-dev-sqlite-replace-20260527T152207Z.ready`
- Slice: `insert-or-replace-conflict-current`
- Base accepted source: `f61617b26c4ba5e0e2d05a6fc0b23b39849829d7`
- Worker patch: `59977` bytes, sha256 `67be0f7aa64450bdbfd6dac856729b1bc3df05b29328782bd5f6ee940f9835c4`
- Apply check: clean with `git apply --check` against the accepted source.

## Accepted Behavior

The patch adds a bounded native PHP planner for WordPress `wp_options` `INSERT OR REPLACE`
behavior where a current row conflicts with the incoming unique `option_name`.
The accepted behavior deletes conflicting current rows and stale index entries
before planning the incoming row insert, preserving the resulting unique
`option_name` index lookup.

## Verification

- `php -l` passed for `SQLiteDatabase.php`, `SQLiteWordPressOptionInsertOrReplacePlan.php`, `SQLiteHeaderTest.php`, and `wordpress-insert-or-replace-conflict-current.php`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php` passed with `1 test files, 9476 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-insert-or-replace-conflict-current.php` passed and emitted the expected replaced `home` option and updated index record.
- `jq empty lanes/libsqlite/lane-status.json` passed.
- `git diff --check -- lanes/libsqlite` passed before commit staging.
- `TMPDIR="$PWD/.tmp-root" php -d memory_limit=512M tools/run-tests.php` passed with `215 test files, 34756 assertions, 0 failures`.

## Decision

Accepted as one source-moving libsqlite behavior slice. This moves focused
libsqlite assertions from the previous accepted `9452` count to `9476` and root
assertions from `34732` to `34756` without new failures.
