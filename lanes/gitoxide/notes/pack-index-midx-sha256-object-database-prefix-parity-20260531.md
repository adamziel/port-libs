# Pack Index MIDX SHA-256 Object Database Prefix Parity

Slice: `gitoxide-pack-index-midx-prefix-parity-20260531T225502Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/init.rs`: pack-index files do not store their object-hash kind, so callers pass the repository object hash when opening or mapping index bytes.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/data/file/init.rs`: pack data files are also opened with the repository object hash; the trailing checksum length follows that hash kind.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/load_index.rs`: dynamic object-database loading only promotes a `multi-pack-index` when its embedded object hash matches the store hash, and then removes referenced standalone pack indexes from the lookup set.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs`: object-database prefix lookup delegates to pack index or multi-pack-index prefix lookup after the matching hash-kind indexes have been loaded.

## Native PHP Delta

- `PackData::fromBytes()` and `PackData::open()` now accept an object hash kind, size the pack trailer from that hash, verify SHA-1 or SHA-256 pack checksums, and validate read object IDs against the paired `PackIndex` hash kind.
- `ObjectDatabase` now opens pack indexes and pack data with its configured object hash, and skips a MIDX whose embedded object hash does not match the database hash.
- Added a SHA-256 WordPress multi-pack fixture and example showing native PHP lookup/read/header prefix behavior through a SHA-256 MIDX without shelling out to `git`.

## Verification

- Red check before source fix: `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php` -> `2 test files, 268 assertions, 2 failures` (`Pack index size is incorrect... got 1136 bytes`; `Pack index checksum does not match pack data checksum`).
- Focused pack/MIDX/object database gate: `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/PackDataTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php` -> `4 test files, 421 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)` -> `39 test files, 6103 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-object-database-multi-pack-sha256.php` exited `0`.

## Non-Overlap

This is additive to the accepted pack-index/MIDX prefix candidate-range, SHA-256 `PackIndex`, MIDX SHA-256 lookup, object-database candidate-set, and stale MIDX offset-validation slices. It specifically covers the remaining upstream boundary where a dynamic object database must load SHA-256 pack data and SHA-256 pack indexes through a matching SHA-256 MIDX before prefix lookup and object reads.

## Dependency Closure

No new support component is needed. The slice reuses native PHP pack-index, MIDX, pack-data, object-database, and fixture helpers. Full upstream Cargo workspace execution remains excluded for this isolated micro-slice due workspace breadth.
