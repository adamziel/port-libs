# Pack-Index/MIDX Full and Missing Prefix Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T011012Z`

Base: `6025aa0c35dc17d20b1c6c068ec52bbef5bf715c`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/tests/pack/index.rs`
  verifies that missing odd-length prefixes built from the null object reset
  candidate ranges to `0..0`, and that full-object prefix lookups return a
  one-entry range for v1/v2 pack indexes.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/tests/pack/multi_index/access.rs`
  applies the same missing-prefix and one-entry candidate-range contract to
  MIDX access, with `gix-pack/src/multi_index/access.rs` delegating prefix
  lookup to `gix-pack/src/index/access.rs`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs`
  continues through all pack indexes, multi-pack indexes, and loose stores
  when callers request candidates, while `disambiguate_prefix()` grows a
  prefix one hex nibble at a time until the lookup is unique.

## Native PHP Delta

- `PackIndexTest.php` adds the upstream-shaped odd-length missing prefix and
  full-object prefix one-entry range checks for direct pack-index lookup.
- `MultiPackIndexTest.php` adds the same checks for SHA-1 MIDX lookup and a
  SHA-256 full-object prefix range case.
- `ObjectDatabaseTest.php` adds dynamic object-database coverage proving that a
  MIDX object colliding with a loose object at four hex chars is reported as
  ambiguous with candidates, then disambiguates to a longer unique prefix.
- `examples/wordpress-object-database-multi-pack.php` now exposes the
  WordPress deployment MIDX content object's shortest unique prefix after a
  loose collision and its full-prefix candidate set.

## Verification

- Before focused change:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 340 assertions, 0 failures`.
- After focused change:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 373 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`
  -> `40 test files, 6645 assertions, 0 failures`.
- PHP lint passed for changed test/example PHP files.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-object-database-multi-pack.php`
  exited `0`.
- Diff check:
  `git diff --check -- lanes/gitoxide` exited `0`.

## Non-Overlap

This is additive to the accepted prefix candidate-range, SHA-256 prefix,
object-database candidate collection, stale MIDX offset validation, and
binary-search midpoint expansion slices. It does not change pack/MIDX parsing,
offset validation, object reads, loose-object integrity, transport, or
send-pack behavior. The new surface is limited to upstream access-test parity
for odd-length missing prefixes, full-id candidate ranges, and dynamic
MIDX-plus-loose disambiguation.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
pack-index, MIDX, loose-object, object-database, and lane-local WordPress
fixture helpers. Full upstream Cargo workspace execution remains excluded for
this isolated micro-slice.
