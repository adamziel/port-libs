# Loose Object Positive Size Integrity Parity - 2026-05-31

## Upstream Source Truth

- Re-read `gix-object/src/lib.rs`.
- Re-read `gix-utils/src/btoi.rs`.
- Re-read `gix-odb/src/store_impls/loose/find.rs`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.

Mapped behavior:

- `gix_object::decode::loose_header()` parses the `<type> <size>\0` size
  with `gix_utils::btoi::to_signed::<u64>()`.
- That parser accepts a leading `+` for positive sizes, rejects empty,
  non-digit, and negative sizes for the unsigned loose-object size boundary.
- Loose-object reads decode the accepted header to kind and payload bytes.
- `gix_odb::loose::Store::verify_integrity()` re-hashes the decoded kind and
  data through the store object hash sink, so a positive-size header stored at
  the canonical object id verifies, while the same bytes stored at the raw
  noncanonical-header hash fail integrity.

## Native PHP Delta

- `GitObject::decodeLooseHeader()` now accepts `+N` size fields while keeping
  canonical `GitObject::looseHeader()` and `storageBytes()` output as `N`.
- `LooseObjectStore::readHeader()`, `read()`, and `verifyIntegrity()` inherit
  this behavior for compressed loose objects.
- The WordPress loose-object header example now proves a block-content object
  with an upstream-accepted positive-size header decodes to the canonical
  object id without shelling out to `git cat-file`.

## Verification

- Pre-edit focused object/database run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 170 assertions, 0 failures`
- Post-edit focused object/database run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 191 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 4469 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/GitObject.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/fixtures/wordpress-object-header.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-header.php`
  - all reported no syntax errors.
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-header.php`
  - exited 0.

Root aggregate and full Cargo workspace runners were not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses existing PHP integer
parsing, zlib loose-object reads, and native object database integrity
verification.

## Non-Overlap

This does not repeat the accepted loose-object header, SHA-256 hash-kind
integrity, or directory-candidate integrity slices. The new mapped behavior is
the upstream positive signed size boundary and its effect on canonical
loose-object integrity verification.
