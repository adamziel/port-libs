# Partial Clone Promisor Hydration Refresh - 2026-05-31

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260531T131911Z`

Accepted base: `27153c38e7cef55880aa33fb66fba5f5470c1f89`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix/src/remote/connection/fetch/receive_pack.rs`
- `gix-odb/src/store_impls/dynamic/load_index.rs`
- `src/plumbing/progress.rs`

Mapped behavior:

- Gitoxide's fetch receive path writes fetched pack bundles into the repository
  object database under `objects/pack`.
- `gix-odb` dynamic store loading can consolidate disk state and collect a new
  snapshot of pack indices, pack data, alternates, and loose stores after the
  underlying object database changes.
- `remote.<name>.promisor` and `remote.<name>.partialCloneFilter` are planned
  Gitoxide configuration surfaces for large partial clones and sparse-index
  workflows.

## Native PHP Delta

- `ObjectDatabase::read()` and `readHeader()` now retry local object lookup once
  after a promisor resolver runs and returns `null`, refreshing pack, MIDX,
  promisor-pack, alternate, and loose-store caches first.
- Resolvers can now hydrate by side effect, such as writing a fetched promisor
  pack/index pair into `objects/pack`, instead of always returning an in-memory
  `GitObject` for loose-object persistence.
- The WordPress lazy-promisor example now models a blobless media fetch that
  writes a fresh promisor pack and then reads the object through the refreshed
  object database.

## Verification

- Red-first focused partial-clone run:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - failed as expected on `object database refreshes pack indexes after promisor resolver hydrates on disk` with `Object promised by partial clone filter but not present locally`.
- Post-edit focused partial-clone run:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 44 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 4642 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - all reported no syntax errors.
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - exited 0.

Root harness status: `not run - isolated micro-slice`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
pack builder, pack/index readers, loose-object store, promisor resolver
interface, and object database cache structures. Full Cargo workspace parity
remains excluded for this isolated worker because it would hydrate/build the
large feature-heavy upstream workspace.

## Non-overlap

This extends the accepted partial-clone/promisor resolver coverage without
repeating filter-spec parsing, blob:none inclusion checks, in-memory resolver
object returns, promisor pack inventory, loose-object allocation limits,
pack-delta guards, sparse checkout/pathspec behavior, transport, or reference
transaction work. The new behavior is limited to Gitoxide-style object database
refresh after lazy promisor hydration writes new pack data on disk.
