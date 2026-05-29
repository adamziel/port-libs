# VFS Lock URI Temp File-Control Current Source Next137

This slice adds `SQLiteVfsLockUriTempFileControlCurrentSourceNextPlan`, a bounded native PHP VFS planner for the current-source edge where URI-opened main and temp databases share a connection, `temp_directory` file-control changes only future temp opens, URI file-controls read the currently selected handle, and temp locks/delete-on-close remain scoped to the active temp owner.

WordPress path: `wordpress-vfs-lock-uri-temp-filecontrol-current-source-next137.php` models a copied `wp_options` import that stages data through a URI memory/temp file, reads a `scratch` URI value with `xFileControl`, blocks a competing cron writer lock on the temp source, deletes the temp owner on close, and reopens the next temp file under the updated import temp directory.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsLockUriTempFileControlCurrentSourceNext137Test.php`
- `php -l lanes/libsqlite/src/SQLiteVfsLockUriTempFileControlCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsLockUriTempFileControlCurrentSourceNext137Test.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-lock-uri-temp-filecontrol-current-source-next137.php`
- `php lanes/libsqlite/examples/wordpress-vfs-lock-uri-temp-filecontrol-current-source-next137.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +58` from the focused TestRunner PASS lines. `benchmarkDenominator.mapped` is unchanged; this is additional current-source PHP behavior over existing VFS URI/temp/file-control coverage, not a newly mapped upstream inventory row.

Non-overlap: avoids accepted VFS lock-state, process file-lock, locked writer, sync plan/apply, file writer, URI temp locking next130, SHM/file-control next131/next134 conflict surfaces, rollback-journal apply/commit, WAL checkpoint/savepoint, JSON table, SELECT, B-tree, encoding, and suite evidence clusters. The new behavior is the combination of current-source URI file-control reads, temp-directory mutation for future temp opens, and temp owner lock/delete lifecycle.

Dependency closure: no new support component is needed. The slice reuses the lane-local `SQLiteFileUri` parser and native PHP VFS lock/current-source state; no extension, provider credentials, or upstream binary runner is required.
