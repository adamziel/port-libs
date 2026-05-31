# Pack Index MIDX Prefix Candidate Parity

Slice: `gitoxide-pack-index-midx-prefix-parity-20260531T215647Z`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs` at upstream commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`: `lookup_prefix(prefix, candidates)` keeps the normal missing/found/ambiguous result but, when candidates are requested, continues through all loaded indexes and loose stores to collect every matching object id.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/handle.rs`: a dynamic handle transforms either `gix_pack::index::File::lookup_prefix()` or `gix_pack::multi_index::File::lookup_prefix()` candidate entry ranges into full object ids.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/access.rs` and `gix-pack/src/multi_index/access.rs`: pack index and MIDX prefix lookup share the same candidate-range implementation, with MIDX delegating to pack-index access.

## Native PHP Delta

- `ObjectDatabase::lookupPrefix()` now accepts an optional `includeCandidates` flag. The default call shape is unchanged.
- When candidates are requested, the object database refreshes its object storage view and returns a de-duplicated, sorted `candidates` list for missing, found, and ambiguous prefix outcomes.
- Candidate collection now covers multi-pack-index entries, standalone pack indexes, and loose object stores. Ambiguous results keep the existing `matches` key and mirror it into `candidates`.
- `examples/wordpress-object-database-multi-pack.php` now demonstrates a WordPress deployment repository where an on-disk MIDX content object and a loose object share a short prefix, and PHP can report both candidates without invoking `git`.

## Verification

- Pre-slice accepted full-lane evidence in `lane-status.json`: `39 files / 5836 assertions / 0 failures`.
- Focused object database: `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php` -> `1 test files, 173 assertions, 0 failures`.
- Focused pack/MIDX/object-database gate: `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php` -> `3 test files, 299 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)` -> `39 test files, 5848 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-object-database-multi-pack.php` exited `0`.
- Expected native assertion movement: `5836 -> 5848` full-lane assertions.
- Expected mapped denominator movement: `1644 / 2886 -> 1645 / 2886`.

## Non-Overlap

This does not repeat accepted pack-index/MIDX candidate ranges, SHA-256 pack-index prefix behavior, object-database prefix disambiguation, stale MIDX pack-offset validation, loose-object integrity, or pack-delta work. It is limited to the upstream dynamic object-database candidate-set surface that collects object ids across pack indexes, multi-pack indexes, and loose object stores.

## Dependency Closure

No new support component is needed. The slice reuses native PHP pack-index, MIDX, loose-object, and object-database primitives plus existing lane-local WordPress fixtures. Full upstream Cargo workspace parity remains excluded for this isolated micro-slice because it would build the broad feature-heavy workspace beyond the focused behavior gate.
