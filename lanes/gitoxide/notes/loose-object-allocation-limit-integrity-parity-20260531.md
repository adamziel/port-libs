# Loose Object Allocation Limit Integrity Parity - 2026-05-31

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/mod.rs`.
- Re-read `gix-odb/src/store_impls/loose/find.rs`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.
- Re-read `gix-odb/src/store_impls/dynamic/load_index.rs`.

Mapped behavior:

- `gix_odb::loose::Store::at()` carries `alloc_limit_bytes`, documented as
  limiting allocations caused by loose object bodies declared on disk.
- `Store::try_find()` decodes the loose header, then calls
  `ensure_in_alloc_limit(size)` before allocating the full object body.
- `Store::verify_integrity()` reads every yielded loose object through
  `try_find()`, so allocation-limit failures stop integrity verification.
- The dynamic object database passes the same `alloc_limit_bytes` into each
  loose store loaded from the primary object directory and alternates.

## Native PHP Delta

- `LooseObjectStore` now accepts an optional allocation limit, exposes it for
  diagnostics, and rejects loose reads when the declared header size exceeds
  the configured limit before full-body inflate.
- `ObjectDatabase` now carries that limit into primary and alternate loose
  stores for reads, writes, promisor persistence, and `verifyLooseIntegrity()`.
- The WordPress loose-object header example now proves a block-content import
  preflight can read bounded headers while rejecting oversized loose-object
  declarations without invoking `git cat-file`.

## Verification

- Red-first focused object/database run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - failed as expected on unknown `allocationLimitBytes` and
    `looseObjectAllocationLimitBytes` parameters; existing tests still passed.
- Post-edit focused object/database run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 206 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 4588 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `php -l lanes/gitoxide/fixtures/wordpress-object-header.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-header.php`
  - all reported no syntax errors.
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-header.php`
  - exited 0.

Root aggregate and full Cargo workspace runners were not run for this isolated
micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses existing PHP zlib
header-inflate logic, native loose-object parsing, alternates resolution, and
object database integrity verification.

## Non-Overlap

This does not repeat accepted loose-object header parsing, SHA-256 hash-kind
integrity, directory-candidate verification, or positive signed size
canonicalization. The new mapped behavior is the upstream `alloc_limit_bytes`
guard on declared loose-object body sizes and its application to primary and
alternate loose stores during integrity verification.
