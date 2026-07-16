# Pack-Index/MIDX Boundary Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-boundary-parity-20260601T091922Z`

Base accepted HEAD: `5d3833db5349181ff1e32c459b4c7ae4edd1837e`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/encode.rs`
  defines `LARGE_OFFSET_THRESHOLD` as `0x7fff_ffff`; pack-index writers store
  offsets greater than that threshold through the 64-bit offset table.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/access.rs`
  dereferences high-bit v2 pack-index offsets through the large-offset table.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/multi_index/chunk.rs`
  creates a MIDX `LOFF` chunk only when at least one offset is greater than
  `u32::MAX`, but once the chunk exists it writes every offset greater than
  `0x7fff_ffff` as a large-offset reference. Without `LOFF`, high-bit MIDX
  offsets remain raw 32-bit offsets.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/multi_index/access.rs`
  applies the same read boundary: high-bit offsets are dereferenced only when
  the MIDX has a large-offset chunk.

## Native PHP Delta

- `PackIndexTest.php` now pins the exact v2 pack-index offset threshold:
  `0x7fffffff` remains raw, while `0x80000000` and `0xffffffff` round-trip
  through the 64-bit offset table and remain prefix-addressable.
- `MultiPackIndexTest.php` now pins both MIDX branches: raw high-bit offsets
  when no `LOFF` chunk is present, and `LOFF` dereferencing for
  `0x80000000`, `0xffffffff`, and `0x100000000` when one entry forces the
  large-offset chunk.
- The WordPress pack-index and MIDX examples now expose the large-offset
  threshold and whether their compacted media object crosses the 64-bit offset
  boundary.

## Verification

- Before focused change:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php`
  -> `2 test files, 211 assertions, 0 failures`.
- Focused pack/MIDX gate after the change:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php`
  -> `2 test files, 225 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`
  -> `40 test files, 8420 assertions, 0 failures`.
- PHP lint passed for changed PHP files:
  `php -l lanes/gitoxide/tests/PackIndexTest.php`;
  `php -l lanes/gitoxide/tests/MultiPackIndexTest.php`;
  `php -l lanes/gitoxide/examples/wordpress-pack-index.php`;
  `php -l lanes/gitoxide/examples/wordpress-multi-pack-index.php`.
- `jq empty lanes/gitoxide/lane-status.json` and
  `jq empty lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/gitoxide` passed.
- Example smokes:
  `php lanes/gitoxide/examples/wordpress-pack-index.php` and
  `php lanes/gitoxide/examples/wordpress-multi-pack-index.php` both exited 0.

Root harness status: `not run - isolated micro-slice`.

## Non-Overlap

This does not repeat accepted pack/MIDX prefix candidate ranges, midpoint
expansion, odd-length missing prefixes, full-prefix fallthrough, absent
full-candidate disambiguation, empty-index parsing, SHA-256 object-database
loading, stale MIDX offset validation, or object-database candidate
de-duplication. The slice is limited to exact large-offset threshold behavior
at direct pack-index and MIDX access boundaries.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP
pack-index, multi-pack-index, WordPress fixture, and lane test helpers. Full
upstream Cargo workspace execution remains excluded for this isolated
micro-slice.
