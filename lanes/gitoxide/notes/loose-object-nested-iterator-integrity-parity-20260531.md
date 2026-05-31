# Loose Object Nested Iterator Integrity Parity - 2026-05-31

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/iter.rs` at upstream commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read `gix-odb/src/store_impls/loose/find.rs`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.

Mapped behavior:

- `gix_odb::loose::Store::iter()` walks the loose object directory with
  `min_depth(2)`, `max_depth(3)`, and `follow_links(false)`.
- The iterator converts any entry whose last two normal path components look
  like `<2 hex>/<hash remainder>` into an object id, regardless of whether the
  entry was found at the canonical fanout depth or one level below another
  directory.
- `verify_integrity()` then calls `try_find()` for every yielded id. If the
  canonical loose object cannot be found or read, verification fails instead of
  reporting the store as clean.

## Native PHP Delta

- `LooseObjectStore::verifyIntegrity()` now uses a dedicated candidate walker
  matching the upstream loose iterator depth boundary for integrity checks.
- The public `LooseObjectStore::objectIds()` iterator remains a direct
  regular-file iterator for stable object listing.
- `ObjectDatabase::verifyLooseIntegrity()` inherits the stricter behavior for
  primary and alternate loose object stores.
- `examples/wordpress-object-database.php` now proves a WordPress deployment
  preflight rejects a nested stale loose-object candidate without invoking
  `git fsck` or `git cat-file`.

## Verification

- Red-first focused object/database run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - failed as expected on skipped nested loose-object candidates:
    `2 test files, 226 assertions, 3 failures`
- Post-edit focused object/database run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 229 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 4878 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-database.php`
  - all reported no syntax errors.
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-database.php`
  - exited 0.
- Whitespace/JSON:
  - `git diff --check -- lanes/gitoxide`
  - exited 0.
  - `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json` decoded with
    `JSON_THROW_ON_ERROR`.

Root aggregate and full upstream Cargo workspace runners were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses PHP filesystem traversal,
existing zlib loose-object reads, alternates resolution, and native object
database integrity verification.

## Non-Overlap

This does not repeat accepted loose-object header parsing, SHA-256 hash-kind
integrity, directory-candidate verification, positive signed size
canonicalization, allocation-limit checks, or inflated body-size mismatch
rejection. The new mapped behavior is the upstream loose iterator depth-3
candidate boundary and its effect on primary/alternate integrity verification.
