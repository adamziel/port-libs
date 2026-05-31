# Pack Index SHA-256 Prefix Parity

Slice: `gitoxide-pack-index-midx-prefix-parity-20260531T153157Z`

## Upstream Source Truth

- `gix-pack/src/index/init.rs::File::from_data()` accepts an explicit object
  hash kind and sizes pack-index object IDs, pack checksum, and index checksum
  from that hash length.
- `gix-pack/src/index/access.rs::lookup_prefix()` is hash-length generic and
  shares the prefix candidate-range logic used by normal pack indexes.
- `gix-pack/src/multi_index/access.rs::lookup_prefix()` delegates to the same
  helper, so SHA-256 pack-index prefix lookup must have the same found,
  missing, ambiguous, and candidate-range behavior that the PHP MIDX parser
  already exposed.

## Native PHP Delta

- `PackIndex::fromBytes()` and `PackIndex::open()` now accept an optional
  object hash kind, defaulting to `sha1` for existing callers.
- `PackIndex` now sizes v1/v2 object-id tables, pack/index checksums, full-id
  lookup validation, prefix validation, checksum verification, and
  disambiguation from the selected hash kind.
- `PackIndexEntry` accepts SHA-1 and SHA-256 object IDs, matching
  `MultiPackIndexEntry`.
- The WordPress pack-index fixture/example now records and reports the pack
  index hash kind used for compacted repository content.

## Verification

- Before focused change: `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php` -> `2 test files, 106 assertions, 0 failures`.
- After focused change: `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php` -> `2 test files, 126 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)` -> `39 test files, 4826 assertions, 0 failures`.
- PHP lint passed for changed PHP files.
- Example smoke: `php lanes/gitoxide/examples/wordpress-pack-index.php` exited 0.

## Non-Overlap

This is additive to the accepted pack/MIDX candidate-range and disambiguation
slice. It does not repeat SHA-1 prefix ranges, MIDX SHA-256 lookup,
pack/MIDX validation, object-database de-duplication, loose-object integrity,
or pack-delta behavior. The new behavior is limited to pack-index hash-kind
parity so SHA-256 pack indexes and SHA-256 MIDX files expose the same prefix
lookup surface.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
pack-index parsing and MIDX prefix tests. Full upstream Cargo workspace parity
remains excluded for this isolated worker because it would hydrate/build the
large feature-heavy workspace beyond the current micro-slice.
