# Partial Clone Promisor External Refresh - 2026-05-31

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260531T153657Z`

Accepted base: `f19de273d07b6a4933953049cdd208ef1fd51490`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-odb/src/store_impls/dynamic/mod.rs`
- `gix-odb/src/store_impls/dynamic/find.rs`
- `gix-odb/src/store_impls/dynamic/prefix.rs`
- `gix-odb/src/store_impls/dynamic/load_index.rs`
- `gix-odb/src/lib.rs`

Mapped behavior:

- `gix-odb` dynamic handles refresh from disk after loaded indices and loose
  stores miss an object, unless the handle is explicitly configured never to
  refresh.
- Prefix lookup similarly keeps loading/refeshing disk state for missing or
  single-candidate prefixes so newly written packs can be discovered and can
  change a missing/found result into the current object database answer.
- This matters for partial clones because a lazy promisor fetch can write a
  pack/index/`.promisor` bundle to `objects/pack` outside a stale object
  database snapshot.

## Native PHP Delta

- `ObjectDatabase::contains()` now retries after clearing cached pack, MIDX,
  promisor-pack, alternate, and loose-store snapshots when the first local
  lookup misses.
- `ObjectDatabase::lookupPrefix()` now collects candidates through a helper,
  refreshes on missing or single-candidate results, and then returns the
  current missing/found/ambiguous outcome.
- `examples/wordpress-lazy-promisor-fetch.php` now records a blobless
  WordPress media resolver fetch plus an externally written template promisor
  pack that becomes visible through `contains()` and prefix lookup.

## Verification

- Baseline focused run before adding tests:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 44 assertions, 0 failures`
- Red-first after adding tests, before source fix:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - failed on `object database contains refreshes after external promisor pack hydration` with `Expected: true`, `Actual: false`
  - failed on `object database prefix lookup refreshes after external promisor pack hydration` with `Expected: 'found'`, `Actual: 'missing'`
- Post-edit focused partial-clone run:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 63 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 4825 assertions, 0 failures`

Root harness status: `not run - isolated micro-slice`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP pack
builder output, pack/index readers, promisor sidecar discovery, object-database
cache structures, and the WordPress partial-clone fixtures. Full Cargo
workspace parity remains excluded for this isolated worker because it would
hydrate/build the large feature-heavy upstream workspace.

## Non-overlap

This extends accepted partial-clone/promisor behavior without repeating fetch
filter parsing, blob:none inclusion checks, in-memory resolver object returns,
resolver-written pack refresh on `read()`/`readHeader()`, promisor pack
inventory, loose-object allocation limits, pack-delta guards, sparse checkout,
transport, reference transaction, stale-queue merge-base, directory-type,
parent-escape, or empty-SSH-port work. The new behavior is limited to
Gitoxide-style object database refresh after an external lazy promisor
hydration writes pack data on disk.
