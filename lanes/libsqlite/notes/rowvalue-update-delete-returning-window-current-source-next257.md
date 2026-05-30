# rowvalue-update-delete-returning-window-current-source-next257

Status: focused PHP behavior growth for current-source row-value `UPDATE`/`DELETE ... RETURNING` window execution.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext257Plan`. It layers a DELETE RETURNING tombstone gate over accepted next253 chunk-token admission: current-source DELETE RETURNING tombstones are published before next-source retry tombstones, and retry tombstones stay held when current-source yield tickets or window chunk acknowledgements are incomplete.

Application path: `application-rowvalue-returning-window-current-source-next257.php` models copied `wp_options` imports where deleted transient rows must be published as current-source tombstones before retry/delete rows from the next source are exposed.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext257Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext257Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next257.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext257Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next257.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +63` from the new focused test file. `benchmarkDenominator.mapped` remains unchanged; this is current-source PHP behavior over already mapped row-value DML, RETURNING, savepoint retry, and window inventory.

Dependency closure: no new support component is needed; this reuses native PHP UPDATE/DELETE RETURNING execution and next253 current-source window chunk admission.

Non-overlap: avoids accepted next253 chunk construction, next252/next251 source fences, next248 publication cursor, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces. The new surface is DELETE RETURNING tombstone ordering before next-source retry publication.
