# Pack-Index/MIDX Prefix Invalid-Byte Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T182439Z`

Base accepted HEAD: `46132b002aae86d77139b7f5e361edf24e0035ba`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-hash/src/prefix.rs`
  rejects non-hex bytes through `Prefix::from_hex()` and enforces the minimum
  four-hex prefix length.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/access.rs`
  and `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/multi_index/access.rs`
  route pack-index and MIDX prefix lookup through that validated `Prefix`
  boundary.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs`
  applies the same validated prefix behavior across pack-index, MIDX, and
  loose object-database lookup.

## Native PHP Delta

- `PackIndexTest.php`, `MultiPackIndexTest.php`, and `ObjectDatabaseTest.php`
  now pin non-hex object-id bytes, NUL/control bytes inside prefixes, too-short
  prefixes, and overlong full-id prefixes at the direct pack-index, MIDX, and
  object-database lookup boundaries.
- `examples/wordpress-object-database-multi-pack.php` now exposes the
  WordPress deployment repository path for MIDX prefix and object-id invalid
  byte rejection.

## Evidence

- Before focused change:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 693 assertions, 0 failures`.
- After focused change:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 713 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-object-database-multi-pack.php`
  exited `0`.

Root harness status: `not run - isolated micro-slice`.

## Non-Overlap

This does not repeat accepted candidate-range, midpoint-expansion, odd-length
missing prefix, full-prefix fallthrough, absent full-candidate, SHA-256,
stale MIDX offset, duplicate-candidate, loose-path candidate, empty-MIDX, or
newline-only prefix slices. It is limited to invalid non-hex/control bytes and
prefix length bounds at the validated hash-prefix boundary shared by pack
indexes, MIDX files, and dynamic object database lookup.

## Dependency Closure

No new support component is needed. This reuses the native PHP pack-index,
multi-pack-index, object database, loose-store, and existing WordPress
multi-pack fixture support. Full upstream Cargo workspace execution remains
excluded for this isolated micro-slice.
