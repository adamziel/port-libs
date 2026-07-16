# Loose Object Inflated Size Integrity Parity - 2026-05-31

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/find.rs` at upstream commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.
- Re-read `gix-object/tests/object/object_ref.rs`.

Mapped behavior:

- `gix_odb::loose::Store::try_find()` decodes the loose-object header from a
  bounded inflate buffer, then compares decompressed byte counts to the header
  size before returning object data.
- Overrun and underrun loose-object bodies fail as inflated-size mismatches
  before canonical hash verification.
- `try_header()` remains a bounded header read and can return kind/size even
  when the full loose object later fails exact-body inflation.
- `verify_integrity()` reads each yielded loose object through `try_find()`, so
  the same mismatch stops primary or alternate loose-store verification.

## Native PHP Delta

- `LooseObjectStore::read()` now uses a bounded streaming inflate helper that
  stops when the decompressed bytes exceed the declared loose header length and
  rejects underruns after the stream ends.
- Existing allocation-limit behavior is preserved: declared-size limits are
  checked before full-object inflation.
- `GitObject::fromStorageBytes()` remains the direct in-memory exact parser;
  the new mismatch boundary is specific to compressed loose-object reads.
- `ObjectDatabase::verifyLooseIntegrity()` now inherits the stricter mismatch
  failure for both primary and alternate object stores.
- `examples/wordpress-object-database.php` now proves a WordPress deployment
  preflight rejects a loose-object body-size mismatch without invoking
  `git cat-file`.

## Verification

- Pre-edit focused object database baseline:
  - `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `1 test files, 117 assertions, 0 failures`
- Post-edit focused object/database gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 223 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 4757 assertions, 0 failures`
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

No new support component is needed. The slice reuses PHP zlib streaming inflate,
existing native loose-object parsing, alternates resolution, and object
database integrity verification.

## Non-Overlap

This does not repeat accepted loose-object SHA-256, positive signed size,
directory-candidate, or allocation-limit integrity slices. The new mapped
behavior is the upstream inflated-size overrun/underrun boundary during
compressed loose-object read and integrity verification.
