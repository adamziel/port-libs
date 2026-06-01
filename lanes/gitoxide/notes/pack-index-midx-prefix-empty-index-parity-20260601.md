# Pack-Index/MIDX Empty Prefix Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T080325Z`

Base accepted HEAD: `e8d67b690c8024e8e205d91998fb7a328f880cc7`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/init.rs`
  validates pack-index layout with explicit empty-index handling, so an empty
  v1/v2 pack index can still be opened and queried.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/access.rs`
  resets prefix candidates to `0..0` when lookup misses, including empty fanout
  buckets.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/multi_index/init.rs`
  accepts zero-length OIDL/OOFF chunk ranges when the MIDX fanout reports zero
  objects; `chunk::lookup::is_valid()` and `chunk::offsets::is_valid()` both
  size those chunks from `num_objects`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/multi_index/verify.rs`
  still rejects empty MIDX files during integrity verification, after parsing,
  with `The multi-index claims to have no objects`.

## Native PHP Delta

- `MultiPackIndex::readChunkTable()` now permits adjacent chunk offsets so
  zero-length lookup/offset chunks can be parsed, while the individual chunk
  readers continue to validate required sizes.
- `PackIndexTest.php` covers empty v1 and v2 pack indexes: no entries, no
  sorted offsets, missing prefix ranges reset to `0..0`, and disambiguation of
  a missing full candidate returns `null`.
- `MultiPackIndexTest.php` covers an empty MIDX with zero-length OIDL/OOFF
  chunks: prefix lookup reports missing with `candidateRange` `0..0`, direct
  lookup returns `null`, and fast integrity still rejects the empty index.
- The WordPress MIDX fixture/example now include an empty compacted-pack index
  case so deployment tooling can distinguish "valid but empty MIDX" from a
  malformed MIDX before integrity verification.

## Verification

- Red-first focused check after adding the test/example coverage and before
  the source fix:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php`
  -> `2 test files, 196 assertions, 2 failures`
  (`Multi-pack-index chunk offsets must be strictly increasing`).
- PHP lint passed:
  - `php -l lanes/gitoxide/src/MultiPackIndex.php`
  - `php -l lanes/gitoxide/tests/PackIndexTest.php`
  - `php -l lanes/gitoxide/tests/MultiPackIndexTest.php`
  - `php -l lanes/gitoxide/fixtures/wordpress-multi-pack-index.php`
  - `php -l lanes/gitoxide/examples/wordpress-multi-pack-index.php`
- Focused pack/MIDX gate:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php`
  -> `2 test files, 211 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`
  -> `40 test files, 8167 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-multi-pack-index.php`
  exited `0`.

Root harness status: `not run - isolated micro-slice`.

## Non-Overlap

This does not repeat accepted pack-index/MIDX candidate ranges, midpoint
expansion, odd-length missing prefixes, full-prefix fallthrough, absent
full-candidate disambiguation, SHA-256 object-database prefix loading, stale
MIDX offset validation, same-store or alternate candidate de-duplication, loose
path candidates, promisor refresh, or transport/protocol slices. The new
surface is limited to empty pack indexes and empty MIDX lookup/offset chunks
at the direct prefix lookup boundary.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
pack-index, multi-pack-index, WordPress fixture, and lane test helpers. Full
upstream Cargo workspace execution remains excluded for this isolated
micro-slice.
