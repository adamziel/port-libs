# Pack Index and MIDX Prefix Newline Byte Parity

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T171104Z`

Base accepted HEAD: `a7e4507f91add4e6fd74f6fd6165d39670d41514`

## Source Truth

- `gix-hash/src/prefix.rs` rejects prefixes with invalid hex bytes through `Prefix::from_hex()` / `Prefix::new()`. A final LF byte is not accepted as part of a hex object-id prefix.
- `gix-pack/src/index/access.rs` and `gix-pack/src/multi_index/access.rs` route direct pack-index and multi-pack-index prefix lookups through the same `gix_hash::Prefix` validation boundary.
- `gix-odb/src/store_impls/dynamic/prefix.rs` applies that validated prefix behavior across loose, pack-index, and multi-pack-index object database lookups.

## Port Delta

- Tightened `PackIndex`, `MultiPackIndex`, and `ObjectDatabase` object-id and prefix validators from `$`-anchored regexes to absolute `\A...\z` regexes so PHP PCRE does not accept a final newline byte.
- Added direct pack-index, MIDX, and object database assertions for newline-tailed full object ids and prefixes.
- Extended the WordPress multi-pack object database example to expose newline-tailed prefix/object-id rejection through the user-visible MIDX path.

## Evidence

- `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php` -> `3 test files, 681 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 10289 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-object-database-multi-pack.php` -> exited `0`.
- `php -l` passed for all changed PHP files.

## Non-Overlap

This slice does not repeat accepted candidate-range, odd/full-prefix, empty MIDX, SHA-256 MIDX, duplicate-candidate, or loose-object integrity prefix work. It is limited to final-newline byte rejection at the pack-index, multi-pack-index, and object database prefix/full-id validation boundary.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP pack-index, multi-pack-index, object database, loose-store, and WordPress multi-pack fixture support.
