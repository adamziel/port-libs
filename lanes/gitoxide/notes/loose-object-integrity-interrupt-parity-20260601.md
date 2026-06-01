# Loose Object Integrity Interrupt Parity - 2026-06-01

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/verify.rs` at upstream commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `Store::verify_integrity()` verifies and decodes each loose object, updates
  progress, then checks `should_interrupt`; if set, it returns
  `integrity::Error::Interrupted`.
- Re-read `gix-odb/src/store_impls/loose/find.rs` to keep this slice bounded
  to verification flow rather than the already accepted zlib/header/size paths.

## Native PHP Delta

- `LooseObjectStore::verifyIntegrity()` now accepts an optional interruption
  callback with the verified object id and verified count. A truthy callback
  result aborts after the object has been fully read, re-hashed, and decoded.
- `ObjectDatabase::verifyLooseIntegrity()` propagates an optional interruption
  callback across primary and alternate loose stores, adding the current object
  directory path to the callback.
- The WordPress loose-object header example now proves a deployment-style
  loose-object audit can abort without shelling out to `git fsck`.

## Verification

- Red-first probe before the implementation:
  - `php -r 'require "tools/bootstrap.php"; ... $store->verifyIntegrity(static fn () => true);'`
  - returned `unexpected-pass`, proving the previous API ignored the
    interruption intent.
- Focused object gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php`
  - `1 test files, 139 assertions, 0 failures`
- Focused object-database gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `1 test files, 216 assertions, 0 failures`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-header.php`
  - exited 0.
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 6943 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-header.php`
  - all reported no syntax errors.
- JSON/whitespace:
  - `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); ...'`
  - `git diff --check -- lanes/gitoxide`
  - both exited 0.

Full upstream Cargo workspace and root aggregate harness were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP callables,
existing loose-object zlib inflation, object hashing, and object decoders.

## Non-Overlap

This does not repeat accepted loose-object SHA-256 hashing, structured SHA-256
decoding, allocation limits, inflated-size mismatch rejection, empty-file
rejection, directory/nested iterator candidates, traversal-error handling,
case-normalized duplicates, signed-size canonicalization, or trailing
compressed-stream handling. It is bounded to upstream interruption behavior in
loose-object integrity verification.
