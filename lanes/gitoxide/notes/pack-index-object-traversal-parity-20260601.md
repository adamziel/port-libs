# Pack Index Object Traversal Parity

Micro-slice: `gitoxide-pack-index-object-traversal-parity-20260601T193510Z`
Base accepted HEAD: `17d7fcad81b2831d9e7a6affe5ec8cee04f52d4f`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/access.rs`
  exposes index entry iteration and sorted pack offsets.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/util.rs`
  defines `index_entries_sorted_by_offset_ascending()` by collecting index
  entries and sorting them by `pack_offset`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/traverse/`
  traverses pack objects through the index and accumulates decoded object
  statistics.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/iter.rs`
  uses pack-index offset ordering for efficient object traversal across packs.

## PHP Delta

- `PackIndex::entriesSortedByPackOffset()` returns index entries in pack-offset
  order with a stable index tie-breaker for deterministic duplicate-offset
  diagnostics.
- `PackData::traverseObjectsWithIndex()` verifies the pack/index checksum and
  object count, walks entries in pack-offset order, decodes and resolves each
  object, verifies the resolved object id against the index entry, and returns
  upstream-style traversal rows plus object statistics.
- `wordpress-pack-data.php` now exposes traversal object ids, pack offsets, and
  traversal statistics while staying local and not invoking `git`.

## Verification

- `php -l lanes/gitoxide/src/PackIndex.php`: passed.
- `php -l lanes/gitoxide/src/PackData.php`: passed.
- `php -l lanes/gitoxide/tests/PackIndexTest.php`: passed.
- `php -l lanes/gitoxide/tests/PackDataTest.php`: passed.
- `php -l lanes/gitoxide/examples/wordpress-pack-data.php`: passed.
- `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/PackDataTest.php`: passed, 2 files / 275 assertions / 0 failures.
- `php lanes/gitoxide/examples/wordpress-pack-data.php`: passed.
- `php tools/run-tests.php lanes/gitoxide/tests`: passed, 40 files / 10719 assertions / 0 failures.

## Non-Overlap

This slice is limited to pack-index object traversal and decoded traversal
statistics. It does not repeat accepted MIDX prefix lookup, MIDX boundary
validation, loose-object integrity, partial-clone promisor hydration, transport,
reference transaction, tree merge, URL/refspec, or credential parity work.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
`PackIndex`, `PackData`, `GitObject`, zlib-backed pack decoding, and the local
WordPress pack fixture. The full upstream Cargo workspace was not run in this
isolated lane.
