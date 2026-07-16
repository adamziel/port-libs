# Partial Clone Promisor Config Hydration Parity - 2026-05-31

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260531T184916Z`

Accepted base: `0c0eec061390da3a2185ec8623476b5865dd4a49`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `src/plumbing/progress.rs`
- `gix-odb/src/store_impls/dynamic/find.rs`
- `gix-odb/src/store_impls/dynamic/header.rs`
- `gix-odb/src/store_impls/dynamic/load_index.rs`

Mapped behavior:

- Gitoxide tracks `remote.<name>.promisor` and
  `remote.<name>.partialCloneFilter` as planned configuration surfaces for
  partial clones and sparse-index workflows.
- The dynamic object database keeps retrying object/header lookup through disk
  refreshes by default, which is the object-store side of lazy promisor
  hydration after a fetch writes new object storage.
- A repository with true promisor remote configuration can treat absent
  filtered objects as promised before the first local `.promisor` sidecar pack
  is present.

## Native PHP Delta

- `ObjectDatabase::promisorRemotes()` now reads local Git config remote
  sections and exposes true `remote.<name>.promisor` remotes with their URL and
  `partialCloneFilter` value.
- `ObjectDatabase::read()`, `readHeader()`, and `objectState()` now treat either
  a `.promisor` pack or promisor remote config as promised-object state.
- Config-only promised objects can now be hydrated by a
  `PromisorObjectResolver` before the first promisor pack exists; false
  promisor remotes are ignored.
- `examples/wordpress-lazy-promisor-fetch.php` now records the WordPress
  deployment remote promisor config alongside lazy pack hydration.

## Verification

- Baseline focused run before adding tests:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 63 assertions, 0 failures`
- Red-first focused run after adding tests, before source fix:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - failed on missing `ObjectDatabase::promisorRemotes()` and on config-only
    object state returning `missing` instead of `promised-missing`.
- Post-edit focused partial-clone run:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 76 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 5113 assertions, 0 failures`
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

No new support component is needed. The slice reuses the existing native Git
config parser, fetch filter value object, promisor resolver interface,
loose-object store, pack/index readers, and object database cache refresh
mechanism. Full upstream Cargo workspace parity remains excluded for this
isolated worker because it would hydrate/build the large feature-heavy
workspace.

## Non-overlap

This extends accepted partial-clone/promisor behavior without repeating
filter-spec parsing, blob:none inclusion checks, in-memory resolver writes,
resolver-written pack refresh on `read()`/`readHeader()`, external promisor pack
refresh through `contains()`/prefix lookup, promisor pack inventory, sparse
checkout/pathspec behavior, transport, send-pack status parsing, merge-base, or
reference transaction work. The new behavior is limited to config-only
promisor remote state and resolver hydration before a first `.promisor` pack is
present.
