# Loose Object Truncated Header Inflate Parity - 2026-06-01

Micro-slice: `gitoxide-loose-object-integrity-parity-20260601T071547Z`

Base accepted HEAD: `0c72e2d3dc6140f90e575fbd71aef1cf0d69e30f`

## Source Truth

- Upstream cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-odb/src/store_impls/loose/find.rs` inflates the loose-object header into the fixed `HEADER_MAX_SIZE` first window and treats a zlib `BufError` before a complete header/object stream as an object-read failure.
- `gix-odb/src/store_impls/loose/verify.rs` verifies loose-object integrity through `try_find()` before hashing/decoding, so truncated first-window streams fail integrity traversal instead of exposing the advertised size.
- `gix-features/src/zlib/stream/inflate.rs` allows body streaming to stop when the output buffer is filled; this patch only tightens the first-window case where the compressed stream ends before zlib reaches `ZLIB_STREAM_END`.

## Implementation

- `LooseObjectStore::inflateHeaderBytes()` now rejects truncated compressed input when zlib has not reached stream end and fewer than `HEADER_MAX_SIZE` bytes were inflated.
- `LooseObjectStore::inflateStorageBytesExactly()` applies the same first-window guard before trusting the advertised loose-object size for a full read or integrity verification.
- The WordPress loose-object header example now includes a truncated compressed-header fixture and records that the header is rejected before the size is exposed.

## Evidence

- Red-first probe before the source change showed `readHeader()` returning `array ('type' => 'blob', 'size' => 100, 'headerLength' => 9)` for a truncated compressed stream whose first bytes inflated to `blob 100\0`.
- After the patch, the same probe throws `RuntimeException: Unable to inflate loose object header: 7777777777777777777777777777777777777777`.
- Focused verification:
  - `php -l lanes/gitoxide/src/LooseObjectStore.php && php -l lanes/gitoxide/tests/GitObjectTest.php && php -l lanes/gitoxide/examples/wordpress-object-header.php && php -l lanes/gitoxide/fixtures/wordpress-object-header.php` passed.
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php` passed: `1 test files, 172 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php` passed: `1 test files, 261 assertions, 0 failures`.
  - `php lanes/gitoxide/examples/wordpress-object-header.php` passed.
  - `php tools/run-tests.php lanes/gitoxide/tests` passed: `40 test files, 7950 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The patch reuses PHP's native zlib streaming APIs and the existing native PHP loose-object parser.

## Non-Overlap

This is a loose-object first-window zlib truncation parity patch. It does not repeat the accepted signed-size canonicalization, allocation-limit rejection, empty-file integrity rejection, trailing compressed-stream acceptance, body-size mismatch rejection, interruption callback propagation, transport/protocol, reference-transaction, merge-base, pathspec, config, or pack-index slices.
