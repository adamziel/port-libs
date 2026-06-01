# Pack-Index MIDX Empty Object-Database Prefix Parity

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T154124Z`
Base: `4bde2909381e7674a2483c2ff6a823b5c492218d`

## Upstream Source Truth

- `gix-pack/src/multi_index/init.rs`: `from_data()` accepts zero-length `OIDL` and `OOFF` chunks when the fanout reports zero objects.
- `gix-pack/src/multi_index/access.rs` plus `gix-pack/src/index/access.rs`: prefix lookup over an empty index resets the search range and returns a miss with no candidates.
- `gix-pack/src/multi_index/verify.rs`: `verify_integrity_fast()` rejects an empty MIDX only for explicit integrity verification with the "claims to have no objects" diagnostic.
- `gix-odb/src/store_impls/dynamic/load_index.rs`: normal dynamic object database loading opens multi-pack-index files with `File::at()` and filters out pack indexes listed in the MIDX without running fast integrity verification.
- `gix-odb/src/store_impls/dynamic/prefix.rs`: normal prefix lookup walks available indexes/stores and returns a missing result when no object candidate is available.

## Native PHP Delta

- `ObjectDatabase::multiPackIndexes()` now checksum-verifies empty `MultiPackIndex` files during normal lookup instead of calling `verifyIntegrityFast()`.
- Non-empty MIDX files still use strict fast integrity verification, preserving existing referenced pack-offset checks.
- The object database test overwrites a WordPress multi-pack fixture with a checksum-valid empty MIDX that names one pack index. Prefix lookup for an object hidden by the empty MIDX now returns `missing` with no candidates, while the other pack remains discoverable through its standalone index.
- The WordPress multi-pack example records the same empty-MIDX lookup behavior for the lane smoke path.

## Verification

- Red before source fix: `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php` failed with `Multi-pack-index claims to have no objects`.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php` passed `3 test files, 653 assertions, 0 failures`.
- Full lane after fix: `php tools/run-tests.php lanes/gitoxide/tests/*Test.php` passed `40 test files, 10021 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-object-database-multi-pack.php` exited `0`.
- PHP lint passed for changed PHP files.

## Non-Overlap

This does not repeat accepted pack-index/MIDX duplicate-prefix, absent full-candidate, odd-length prefix, SHA-256 prefix, or referenced pack-offset validation slices. It targets the object-database loading boundary where normal lookup should allow empty MIDX files even though explicit MIDX integrity verification still rejects them.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP `MultiPackIndex`, `PackIndex`, `ObjectDatabase`, and pack fixture helpers. Full upstream Cargo workspace verification remains out of scope for this isolated micro-slice.
