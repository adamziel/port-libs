# Partial Clone Promisor Iteration Refresh - 2026-05-31

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260531T205434Z`

Accepted base: `7a6ad881ab7ec5dade7133aeca014b7a5e54577c`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-odb/src/store_impls/dynamic/iter.rs`
- `gix-odb/src/store_impls/dynamic/prefix.rs`
- `gix-odb/src/store_impls/dynamic/load_index.rs`
- `gix-odb/src/alternate/mod.rs`

Mapped behavior:

- `gix-odb` object iteration creates a fresh `AllObjects` iterator by calling
  `load_all_indices()` in the current thread.
- `packed_object_count()` similarly refreshes all currently available indices
  before counting packed objects.
- `load_all_indices()` consolidates disk state, including fresh pack indices,
  loose stores, and alternates, which is the object-store side of a lazy
  promisor fetch that wrote a new pack/index/`.promisor` bundle after an older
  object database handle had already loaded pack state.

## Native PHP Delta

- `ObjectDatabase::objectIds()` now clears cached pack, MIDX, promisor-pack,
  alternate, and loose-store snapshots before enumerating objects.
- `ObjectDatabase::packedObjectCount()` now refreshes the same object storage
  caches before counting, so a stale handle sees externally hydrated promisor
  packs without requiring a preceding `contains()` or prefix lookup.
- `examples/wordpress-lazy-promisor-fetch.php` now records object-iteration and
  packed-count refresh after an externally written WordPress template promisor
  pack.

## Verification

- Red-first focused partial-clone run:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - failed on `object database iterates refreshed promisor packs after external hydration` with `Expected: true`, `Actual: false`.
- Post-edit focused partial-clone run:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 95 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 5699 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - all reported no syntax errors.
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - exit `0`

Root harness status: `not run - isolated micro-slice`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP pack
builder, pack/index readers, promisor sidecar discovery, object database cache
refresh mechanism, and the existing lane-local WordPress partial-clone fixture.
Full upstream Cargo workspace parity remains excluded for this isolated worker
because it would hydrate/build the large feature-heavy workspace.

## Non-overlap

This deepens the accepted partial-clone/promisor hydration cluster without
repeating fetch filter parsing, blob:none inclusion checks, config-only
promisor remote state, resolver-returned object persistence, resolver-written
pack refresh through `read()`/`readHeader()`, external refresh through
`contains()`/prefix lookup, sparse checkout/pathspec behavior, transport,
send-pack status parsing, merge-base, or reference transaction work. The new
behavior is limited to Gitoxide-style object inventory/count refresh after
external promisor hydration writes new pack data on disk.
