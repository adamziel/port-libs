# Loose Object SHA-256 Integrity Parity - 2026-05-31

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/mod.rs`.
- Re-read `gix-odb/src/store_impls/loose/write.rs`.
- Re-read `gix-odb/src/store_impls/loose/find.rs`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.
- Re-read `gix-object/src/lib.rs` and `gix-object/src/encode.rs`.

Mapped behavior:

- `gix_odb::loose::Store` is initialized with an object hash kind, and that hash kind controls write hashing, loose path length, iteration IDs, `contains()`, header reads, and integrity verification.
- `verify_integrity()` re-hashes each decoded loose object using the store hash kind before decoding the object payload.
- `gix_object::encode::loose_header()` remains the canonical `<kind> <size>\0` header for both SHA-1 and SHA-256 object stores.

## Native PHP Delta

- `LooseObjectStore` now accepts an explicit `sha1` or `sha256` object hash, defaults to SHA-1, writes the correct object ID length, iterates the matching loose path suffix length, and verifies loose object integrity with the selected hash.
- `ObjectDatabase` now carries the selected object hash through loose stores, replacements, promisor verification, prefix lookup bounds, and primary loose writes.
- `examples/wordpress-object-database.php` now includes a SHA-256-addressed WordPress deployment loose-object smoke path.

## Verification

- Pre-edit focused object/database run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 127 assertions, 0 failures`
- Post-edit focused object/database run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 159 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-database.php`
  - all reported no syntax errors.
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-database.php`
  - exited 0.
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `38 test files, 4060 assertions, 0 failures`
- Whitespace and JSON checks:
  - `git diff --check -- lanes/gitoxide`
  - `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, flags: JSON_THROW_ON_ERROR);'`
  - `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR);'`
  - all exited 0.

Root aggregate and full Cargo workspace runners were not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses PHP `hash()` SHA-1/SHA-256 support, zlib compression/inflate, and the existing native Git object, alternate-store, replacement-ref, and object-database primitives.

## Non-Overlap

This extends the accepted loose object integrity and loose object header slices without repeating them. The new mapped behavior is the upstream `object_hash` parameter's effect on SHA-256 loose object paths, writes, lookup, prefix bounds, headers, and integrity verification.
