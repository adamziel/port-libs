# source-neutral-src-option-table-defaults-dynamic-20260601T184940Z-0

Status: ready for integration.

Source-neutral cleanup:

- Replaced remaining production-source VFS and JSON WAL default strings that referenced WordPress-shaped paths, connection names, URI filenames, and WAL page prefixes with generic application defaults.
- Extended the source-neutral dynamic default guard to cover the VFS default source files and assert the new generic defaults through their public planning APIs.
- Tightened the no-domain source guard to catch legacy `wp-content`, URI-encoded `wp%20`, `wp-import`, `wp-json-schema`, `/srv/wp`, and `wp-` source strings.
- Neutralized the directly coupled WAL SHM lock-byte fixture path and connection names so the nolock/default-source behavior still exercises the updated generic default path.
- Neutralized the directly coupled JSON schema WAL savepoint fixture table, column, transaction, and savepoint names while preserving the same rollback/savepoint assertions.

Verification:

- `php -l` for all changed PHP files
  - `14` files, no syntax errors detected
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `2 test files, 69 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsFileControlPersistenceSequenceTest.php lanes/libsqlite/tests/SQLiteVfsOpenFileControlLockingSequenceTest.php lanes/libsqlite/tests/SQLiteVfsLockByteUriShmCurrentSourceNext97Test.php lanes/libsqlite/tests/SQLiteVfsOpenLockFileControlCurrentSourceNext82Test.php lanes/libsqlite/tests/SQLiteVfsOpenUriLockCurrentSourceNext86Test.php lanes/libsqlite/tests/SQLiteVfsShmLockFileControlCurrentSourceNext85Test.php lanes/libsqlite/tests/SQLiteVfsShmFileControlLockCurrentSourceNext87Test.php lanes/libsqlite/tests/SQLiteVfsShmFileControlOpenCurrentSourceNext91Test.php lanes/libsqlite/tests/SQLiteVfsWalShmLockByteCurrentSourceTest.php lanes/libsqlite/tests/SQLiteApplicationJsonSchemaWalSavepointCurrentNext51Test.php`
  - `10 test files, 613 assertions, 0 failures`
- `rg -n "WordPress|wordpress|WP|wp-|wp_|wp/|wp-content|wp%20|/srv/wp|wp-import|wp-json-schema" lanes/libsqlite/src`
  - no source matches
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure: no new support component is needed. This reuses the existing VFS file-control persistence, URI/SHM lock-byte, open/file-control, SHM/current-source, WAL SHM lock-byte, and JSON schema WAL savepoint planners.

Root harness: not run - isolated micro-slice.
