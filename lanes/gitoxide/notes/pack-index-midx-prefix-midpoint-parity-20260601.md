# Pack-Index/MIDX Prefix Midpoint Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T000441Z`

Base: `9938ea0ca5f2430c11f7b91d23d2213507185488`

## Upstream Source Truth

- `gix-pack/src/index/access.rs::File::lookup_prefix()` bounds the initial
  search with the first prefix byte's fanout bucket, binary-searches with
  `Prefix::cmp_oid()`, and when candidate reporting is requested expands
  backward and forward from the matched midpoint to collect the contiguous
  equal-prefix range.
- `gix-pack/src/multi_index/access.rs::File::lookup_prefix()` delegates to
  the same pack-index lookup routine, so MIDX candidate ranges must follow the
  same midpoint-expansion behavior.
- `gix-hash/src/prefix.rs` keeps the minimum hex prefix length at four and
  performs nibble-aware prefix comparison, which the existing PHP prefix
  normalization and odd-nibble tests already cover.

## Native PHP Delta

- `PackIndex::matchingPrefixIndexes()` now uses the upstream-shaped
  binary-search path and then expands the contiguous candidate range around
  the midpoint hit before returning matches and `candidateRange`.
- `MultiPackIndex::matchingPrefixIndexes()` now follows the same algorithm,
  preserving the existing lookup result shape for full-id, found, ambiguous,
  and missing prefixes.
- Focused tests add non-edge midpoint cases where the matching prefix spans
  both sides of the binary-search hit, proving candidate expansion is not just
  a linear prefix scan or first-hit range.

## Verification

- `php -l lanes/gitoxide/src/PackIndex.php`: no syntax errors.
- `php -l lanes/gitoxide/src/MultiPackIndex.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/PackIndexTest.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/MultiPackIndexTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php`: `2 test files, 140 assertions, 0 failures`.
- `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`: `40 test files, 6418 assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide`: passed.

No examples were touched, so no example smoke was needed. Full upstream Cargo
workspace tests were not run for this isolated PHP lane slice.

## Non-Overlap

This is additive to the already accepted pack-index/MIDX prefix candidate,
SHA-256 prefix, object-database prefix, and stale-MIDX-offset validation
slices. It does not change object-database candidate-set aggregation, SHA-256
loading, pack offset validation, receive-pack packet boundaries, or transport
status parsing.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
pack-index and multi-pack-index parsers plus current prefix normalization.
