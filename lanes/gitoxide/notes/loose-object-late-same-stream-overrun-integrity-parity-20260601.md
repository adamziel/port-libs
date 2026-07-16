# Loose Object Late Same-Stream Overrun Integrity Parity - 2026-06-01

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/find.rs` at upstream commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read `gix-features/src/zlib/stream/inflate.rs`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.

Mapped behavior:

- `gix_odb::loose::Store::try_find()` first inflates into a fixed
  `HEADER_MAX_SIZE` 64-byte buffer.
- If a complete object plus extra bytes is visible before that first buffer
  fills, `try_find()` requires the decompressed length to equal the declared
  header-plus-body length.
- If the declared object extends beyond that fixed buffer, gix allocates only
  the remaining advertised body bytes and `zlib::stream::inflate::read()`
  returns once that output buffer is full, even if the deflate stream has more
  bytes available.
- `verify_integrity()` reuses `try_find()`, so a canonical loose object with
  same-stream overrun bytes after the advertised body and after the fixed
  header window verifies, while a small overrun visible in the first window
  remains rejected.

## Native PHP Delta

- `LooseObjectStore::inflateStorageBytesExactly()` now mirrors the upstream
  fixed-header-window boundary.
- Objects whose advertised storage length ends before byte 64 still require an
  exact stream end and still reject same-stream overrun bytes.
- Larger objects return the advertised header-plus-body bytes once available,
  ignoring later same-stream overrun bytes like gix-odb.
- `GitObjectTest.php`, `ObjectDatabaseTest.php`, and
  `wordpress-object-header.php` now cover the loose store, primary object
  database, alternate object database, and WordPress block-content example.

## Verification

- Red-first probe before implementation:
  - `php -r 'require "tools/bootstrap.php"; ... gzcompress($object->storageBytes()."late-overrun") ... $store->read($oid);'`
  - failed with `RuntimeException: Loose object inflated size mismatch: expected 104, got 106`
- Focused object/database gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 416 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 7721 assertions, 0 failures`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-header.php`
  - exited 0.
- PHP lint:
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-header.php`
  - `php -l lanes/gitoxide/fixtures/wordpress-object-header.php`
  - all reported no syntax errors.
- JSON/whitespace:
  - `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR);'`
  - `git diff --check -- lanes/gitoxide`
  - both exited 0.

Root aggregate and full upstream Cargo workspace runners were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses PHP zlib stream
inflation, existing native loose-object hashing, object decoders, object
database alternates, and the existing WordPress object-header example.

## Non-Overlap

This does not repeat accepted loose-object SHA-1/SHA-256 hashing, structured
SHA-256 decoding, allocation limits, empty-file rejection, directory and nested
iterator candidates, broken symlink handling, traversal-error handling,
case-normalized duplicate counting, interruption callbacks, signed-size
canonicalization, first-window inflated-size mismatch rejection, or trailing
compressed-stream handling. The new mapped behavior is limited to late
same-stream overrun bytes after the advertised loose-object body and after the
fixed 64-byte gix header inflate window.
