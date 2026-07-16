# Loose Object First-Window Header Corruption Parity - 2026-06-01

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/find.rs` at upstream commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.
- Re-read `gix-features/src/zlib/stream/inflate.rs`.

Mapped behavior:

- `gix_odb::loose::Store::try_header()` inflates into the fixed
  `HEADER_MAX_SIZE` 64-byte window before decoding the loose header.
- A corrupt small loose-object zlib stream that fails before that first window
  completes is rejected before exposing the declared type or size.
- Larger objects still only validate the bounded first header window for
  header reads; later zlib bytes remain part of full object reads and integrity
  checks.

## Native PHP Delta

- `LooseObjectStore::inflateHeaderBytes()` no longer returns immediately at the
  first NUL byte. It now keeps inflating until the zlib stream ends or the
  fixed 64-byte header window is filled, matching the upstream header preflight
  boundary.
- `readHeader()` and `tryReadHeader()` reject corrupt first-window streams
  before returning size metadata.
- The WordPress loose-object header example now records that corrupt
  first-window loose object headers are rejected without shelling out to
  `git fsck`.

## Verification

- Red-first probe before the implementation:
  - `php -r 'require "tools/bootstrap.php"; ... corrupt last zlib byte for "blob 3\0abc" ... $store->readHeader($oid);'`
  - returned `array("type" => "blob", "size" => 3, "headerLength" => 7)` before the fix.
- PHP lint:
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-header.php`
  - all reported no syntax errors.
- Focused loose-object/ObjectDatabase gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 457 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 8260 assertions, 0 failures`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-header.php`
  - exited 0.
- Whitespace/JSON:
  - `git diff --check -- lanes/gitoxide`
  - exited 0.
  - `lanes/gitoxide/lane-status.json` and
    `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` decoded with
    `JSON_THROW_ON_ERROR`.

Root aggregate and full upstream Cargo workspace runners were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses PHP zlib streaming APIs, the
existing native loose-object store, object hashing, object database alternates,
and the existing WordPress object-header example.

## Non-Overlap

This does not repeat accepted loose-object SHA-1/SHA-256 hashing, structured
SHA-256 decoding, allocation limits, empty-file rejection, directory and nested
iterator candidates, broken symlink handling, traversal-error handling,
case-normalized duplicate counting, interruption callbacks, signed-size
canonicalization, first-window truncation rejection, inflated body-size
mismatch rejection, trailing compressed-stream handling, or late same-stream
overrun handling. The new mapped behavior is limited to corrupt zlib streams
inside the fixed first loose-object header inflate window.
