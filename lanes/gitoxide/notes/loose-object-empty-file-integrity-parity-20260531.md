# Loose Object Empty File Integrity Parity - 2026-05-31

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/find.rs` at upstream commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.

Mapped behavior:

- `gix_odb::loose::Store::map_loose_object()` opens/maps a loose object path
  before zlib decoding and rejects an empty mapped file as an I/O error.
- `try_header()` and `try_find()` therefore fail empty loose object files
  before loose-header parsing or full object inflation.
- `verify_integrity()` reads every iterator-yielded object through
  `try_find()`, so an empty primary or alternate loose object file prevents
  the store from being reported as clean.

## Native PHP Delta

- `LooseObjectStore::readHeader()` and `read()` now reject zero-byte loose
  object files with a dedicated corruption message before invoking zlib.
- `LooseObjectStore::verifyIntegrity()` and
  `ObjectDatabase::verifyLooseIntegrity()` inherit that boundary for primary
  and alternate object directories.
- `examples/wordpress-object-database.php` now proves a deployment integrity
  preflight rejects empty loose object files before trusting zlib/header
  decoding.

## Verification

- Red-first focused object/database run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - failed as expected on empty loose object files falling through to zlib
    header inflate errors: `2 test files, 256 assertions, 2 failures`
- Post-edit focused object/database run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 262 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 5726 assertions, 0 failures`
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

No new support component is needed. The slice reuses PHP filesystem reads,
existing zlib loose-object decoding, native loose object integrity traversal,
and object-database alternate resolution.

## Non-Overlap

This does not repeat accepted loose-object SHA-256, positive signed size,
directory-candidate, nested-iterator, allocation-limit, inflated-size mismatch,
or traversal-error integrity slices. The new mapped behavior is the upstream
zero-byte loose object file rejection before zlib/header decoding and its
effect on primary and alternate integrity verification.
