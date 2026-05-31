# Pack Index MIDX Offset Validation Parity

Slice: `gitoxide-pack-index-midx-prefix-parity-20260531T204645Z`

## Upstream Source Truth

- `gix-pack/src/multi_index/verify.rs::verify_integrity_fast()` validates the
  MIDX checksum, fanout, object ordering, and each object entry against the
  referenced pack index before the multi-pack index is considered trustworthy.
- The fast path opens each referenced pack index with the MIDX object hash,
  looks up the MIDX object id in that pack index, and rejects pack-offset
  mismatches before callers rely on the MIDX for prefix lookup or object reads.
- `gix-pack/src/index/access.rs::lookup_prefix()` assumes the index data being
  searched is internally consistent, so stale MIDX object offsets must be
  rejected at the object-database boundary instead of being surfaced as found
  prefixes.

## Native PHP Delta

- `ObjectDatabase` now cross-checks every loaded `MultiPackIndexEntry` against
  the referenced `PackIndex` by object id and pack offset after the MIDX file
  and referenced pack names have been validated.
- A stale or rewritten `multi-pack-index` whose object id still exists but whose
  stored pack offset no longer matches the referenced pack index now fails
  before `lookupPrefix()`, `contains()`, `read()`, or `readHeader()` can trust
  that MIDX entry.
- The WordPress object-database multi-pack example now records that the MIDX
  referenced-pack offsets were verified as part of the native object database
  load.

## Verification

- Red check before source fix:
  `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php` ->
  `1 test files, 152 assertions, 1 failures`, failing the stale-offset MIDX
  test because prefix lookup still trusted the corrupted offset.
- Focused object database after fix:
  `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php` ->
  `1 test files, 155 assertions, 0 failures`.
- Focused pack/MIDX/object database gate:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 281 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`
  -> `39 test files, 5422 assertions, 0 failures`.
- PHP lint passed for `lanes/gitoxide/src/ObjectDatabase.php`,
  `lanes/gitoxide/tests/ObjectDatabaseTest.php`, and
  `lanes/gitoxide/examples/wordpress-object-database-multi-pack.php`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-object-database-multi-pack.php`
  exited `0`.

## Non-Overlap

This is additive to the accepted pack/MIDX prefix candidate-range and SHA-256
prefix slices. It does not repeat pack-index parsing, MIDX parsing, MIDX prefix
candidate ranges, object-database duplicate prefix disambiguation, loose-object
integrity, pack-delta behavior, or the recent merge-base/pathspec work. The new
behavior is limited to rejecting stale MIDX object offset metadata before prefix
lookup or object reads trust the MIDX.

## Dependency Closure

No new support component is needed. The slice reuses native PHP pack-index,
MIDX, pack-data, object-database, and WordPress multi-pack fixtures. Full
upstream Cargo workspace execution remains excluded for this isolated
micro-slice due workspace breadth.
