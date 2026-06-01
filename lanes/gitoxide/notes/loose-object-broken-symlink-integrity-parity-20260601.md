# Loose Object Broken Symlink Integrity Parity - 2026-06-01

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/iter.rs` at upstream commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read `gix-odb/src/store_impls/loose/find.rs`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.
- Re-read `gix-odb/tests/odb/store/loose.rs`.

Mapped behavior:

- Loose object iteration walks two-hex fanout paths with `follow_links(false)`
  and maps path components to object IDs without first requiring a regular
  file.
- Direct loose-object lookup opens the canonical object path and returns
  missing for an `io::ErrorKind::NotFound` path.
- `verify_integrity()` retries/fails candidates yielded by iteration when
  `try_find()` returns missing, so a broken symlink at a valid object path is
  a direct lookup miss but an integrity failure candidate.

## Native PHP Delta

- `LooseObjectStore::objectPathExists()` now follows PHP `file_exists()`
  semantics only, so broken symlink object paths are treated as missing for
  `contains()`, `tryReadHeader()`, and `tryRead()`.
- Integrity traversal still yields the valid loose-object candidate path and
  reports it through the existing `verifyIntegrity()` missing-object wrapper.
- `ObjectDatabase::verifyLooseIntegrity()` now has alternate-store coverage for
  the same broken-symlink failure boundary.
- `wordpress-object-database.php` records a WordPress deployment object store
  smoke for broken symlink rejection.

## Verification

- PHP lint:
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-database.php`
  - all reported no syntax errors.
- Focused object gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 383 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 7256 assertions, 0 failures`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-database.php`
  - exited 0.
- JSON/whitespace:
  - `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - `git diff --check -- lanes/gitoxide`
  - both exited 0.

Root aggregate and full upstream Cargo workspace runners were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native filesystem symlink
handling, existing loose-object traversal, object database alternate-store
lookup, zlib object inflation, and object hashing/verification paths.

## Non-Overlap

This extends accepted loose-object integrity work without repeating empty-file
rejection, directory candidates, nested iterator candidates, traversal-error
handling, allocation-limit guards, inflated-size mismatch rejection,
positive/negative signed-size canonicalization, SHA-256 structured decode,
trailing compressed stream handling, case-normalized duplicate candidates, or
interruption callback propagation. The new behavior is limited to broken
symlink candidates at valid loose-object paths.
