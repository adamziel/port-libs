# Row-Value UPDATE/DELETE RETURNING Window Next249-252 After Current

## Behavior

- Adds focused after-current coverage that validates the prepared row-value `UPDATE`/`DELETE ... RETURNING` current-source candidates for next249, next250, next251, and next252 in order.
- The coverage composes the existing WordPress examples rather than introducing a new executor surface: next249 chunked yield windows, next250 EXCLUDE TIES frames, next251 source digest handoff, and next252 current-source high-water fences.
- WordPress path: copied `wp_options` imports can verify that retry rows remain behind the current-source window barriers until the prepared next249-252 handoff candidates all report their expected current-source status.

## Non-Overlap

- Avoids changing the accepted next249 chunking, next250 EXCLUDE TIES accounting, next251 source handoff, and next252 publication window fence behavior.
- Avoids row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, suite-runner, and dashboard/status surfaces.

## Dependency Closure

- No new support component needed; this reuses the existing native PHP row-value UPDATE/DELETE RETURNING window examples and focused tests for next249-252.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext249252AfterCurrentTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next249-252-after-current.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext249252AfterCurrentTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next249-252-after-current.php`
- `git diff --check`
