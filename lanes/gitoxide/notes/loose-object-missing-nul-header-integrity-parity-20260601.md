# Loose Object Missing-NUL Header Integrity Parity - 2026-06-01

## Source truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-object/src/lib.rs` `decode::loose_header()` reports `Did not find 0 byte in header` when the inflated loose-object header buffer has no NUL separator.
- `gix-odb/src/store_impls/loose/find.rs` and `gix-odb/src/store_impls/loose/verify.rs` route loose reads and integrity verification through that header decoder before full object decode/hash checks.
- `gix-features/src/zlib/stream/inflate.rs` exposes stream end separately from interrupted or corrupt inflate, which keeps a complete zlib stream with a short no-NUL header distinct from a truncated stream.

## Native delta

- `LooseObjectStore::inflateHeaderBytes()` now reports `Did not find 0 byte in header` when a complete compressed stream ends before the 64-byte loose header cap and no NUL separator is present.
- `LooseObjectStore::inflateStorageBytesExactly()` applies the same missing-NUL diagnostic for complete streams before body-size or hash integrity checks.
- `GitObjectTest` covers direct loose header/read/tryReadHeader/integrity paths.
- `ObjectDatabaseTest` covers primary and alternate object databases, including `verifyLooseIntegrity()` wrapping.
- `wordpress-object-header.php` now smokes the missing-NUL loose-object size-header path used by WordPress/Playground backup integrity preflight.

## Verification

- `for f in lanes/gitoxide/src/LooseObjectStore.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php lanes/gitoxide/examples/wordpress-object-header.php lanes/gitoxide/fixtures/wordpress-object-header.php; do php -l "$f" || exit 1; done` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php` passed: 2 files / 567 assertions / 0 failures.
- `php lanes/gitoxide/examples/wordpress-object-header.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests` passed: 40 files / 9592 assertions / 0 failures.
- Full upstream Cargo workspace was not run for this isolated slice.
- Root harness was not run; this is an isolated Gitoxide micro-slice.

## Dependency closure

No new support component is needed. The slice reuses the existing PHP zlib streaming path, `LooseObjectStore`, `ObjectDatabase`, and lane-local fixtures/tests.

## Non-overlap and next work

This does not repeat accepted loose-object LF-size, allocation-limit, trailing compressed-stream, empty-file, SHA-256, interruption callback, or body-size mismatch integrity slices. The next loose-object worker should move to a distinct remaining integrity gap rather than another missing-NUL header variant.
