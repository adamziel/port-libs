# Loose Object Trailing Stream Integrity Parity - 2026-06-01

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/find.rs` at upstream commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.
- Re-read `gix-features/src/zlib/stream/inflate.rs`.

Mapped behavior:

- `gix_odb::loose::Store::try_find()` inflates a loose object until the zlib
  stream ends and only checks that the declared object body was produced.
- It does not require all remaining bytes in the loose object file to be
  consumed after `StreamEnd`.
- `verify_integrity()` uses that same `try_find()` path before re-hashing and
  decoding the object, so a trailing compressed stream after a complete object
  is ignored, while a same-stream body overrun still fails as a size mismatch.

## Native PHP Delta

- `LooseObjectStore::inflateStorageBytesExactly()` now returns as soon as
  `inflate_get_status()` reports `ZLIB_STREAM_END` with the exact advertised
  header-plus-body length.
- Same-stream overrun and underrun checks are preserved.
- The WordPress loose-object header example now proves a block-content loose
  object with a trailing compressed stream still reads and verifies through the
  native store.

## Verification

- Red-first probe before the implementation:
  - `php -r 'require "tools/bootstrap.php"; ... gzcompress($object->storageBytes()).gzcompress("blob 3\0def") ... $store->read($oid);'`
  - failed with `RuntimeException: Unable to inflate loose object: f2ba8f84ab5c1bce84a7b441cb1959cfc7093b7f`
- Focused object gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php`
  - `1 test files, 132 assertions, 0 failures`
- Focused object-database gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `1 test files, 212 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 6726 assertions, 0 failures`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-header.php`
  - exited 0.
- PHP lint:
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-header.php`
  - `php -l lanes/gitoxide/fixtures/wordpress-object-header.php`
  - all reported no syntax errors.
- JSON/whitespace:
  - `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status.json ok\n"; json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "UPSTREAM_TEST_MANIFEST.json ok\n";'`
  - `git diff --check -- lanes/gitoxide`
  - `git diff --check --no-index /dev/null lanes/gitoxide/notes/loose-object-trailing-stream-integrity-parity-20260601.md`
  - all exited 0.

Root aggregate and full upstream Cargo workspace runners were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses PHP zlib stream status,
the existing native loose-object store, object hashing, and structured decode
checks.

## Non-Overlap

This extends accepted loose-object integrity work without repeating SHA-256
store hashing, structured SHA-256 decoding, allocation limits, inflated-size
mismatch rejection, empty-file rejection, directory candidates, nested iterator
candidates, traversal-error handling, case-normalized duplicates, or
positive/negative signed-size canonicalization. The new behavior is limited to
upstream trailing compressed bytes after the first completed loose-object zlib
stream.
