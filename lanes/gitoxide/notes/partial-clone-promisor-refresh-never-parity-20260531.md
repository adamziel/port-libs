# Partial Clone Promisor Refresh-Never Parity - 2026-05-31

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260531T230148Z`

Accepted base: `292ada6b86cc431f7b1537075eacedfb4e905cf4`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-odb/src/store_impls/dynamic/mod.rs`
- `gix-odb/src/store_impls/dynamic/handle.rs`
- `gix-odb/src/store_impls/dynamic/find.rs`
- `gix-odb/src/store_impls/dynamic/prefix.rs`
- `gix-protocol/src/fetch/types.rs`
- `gix/src/repository/object.rs`

Mapped behavior:

- Gitoxide object database handles default to refreshing disk pack state after
  misses, but `Handle::refresh_never()` switches to `RefreshMode::Never`.
- `contains()` returns false when no more cached indices/loose objects match
  and refresh mode forbids disk refreshes.
- Prefix lookup documents the same boundary: unless refresh mode is `Never`,
  a non-ambiguous lookup can trigger a disk refresh.
- Fetch negotiation explicitly requires `exists()` calls not to trigger ODB
  pack refreshes because many object probes are expected to miss in partial
  clones.

## Native PHP Delta

- `ObjectDatabase` now carries a refresh-on-miss flag, exposed through
  `withObjectStorageRefreshDisabled()`, `withObjectStorageRefreshEnabled()`,
  and `objectStorageRefreshesOnMiss()`.
- `contains()`, `lookupPrefix()`, `objectIds()`, `packedObjectCount()`,
  `read()`, `readHeader()`, and external ref-delta base refresh paths now
  honor the flag.
- A warmed refresh-disabled handle keeps externally hydrated promisor packs
  invisible until a refreshed/default handle is used.
- A resolver that writes a promisor pack as a side effect but returns `null`
  no longer gets consumed through an implicit disk refresh when refresh is
  disabled.
- `examples/wordpress-lazy-promisor-fetch.php` now records both the default
  hydrated view and the refresh-disabled stale promised-missing view for a
  WordPress block-template partial-clone fetch.

## Verification

- Baseline focused partial-clone run before this slice:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 118 assertions, 0 failures`
- Red-first after adding the first refresh-disabled test, before source fix:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - failed with `Call to undefined method PortLibs\Gitoxide\ObjectDatabase::withObjectStorageRefreshDisabled()`
- Post-edit focused partial-clone run:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 149 assertions, 0 failures`
- Adjacent object/pack gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php lanes/gitoxide/tests/PackDataTest.php lanes/gitoxide/tests/PackBuilderTest.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `4 test files, 563 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 6107 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - all reported no syntax errors.
- JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - `json ok`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php >/tmp/gitoxide-lazy-promisor-example.out`
  - exit `0`
- Whitespace:
  - `git diff --check -- lanes/gitoxide`
  - exit `0`

Root harness status: `not run - isolated micro-slice`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
object database, pack/index readers, loose object store, promisor resolver
interface, and WordPress lazy-promisor fixture. Full upstream Cargo workspace
parity remains excluded for this isolated worker because it would hydrate/build
the large feature-heavy workspace.

## Non-overlap

This deepens the accepted partial-clone/promisor hydration cluster without
repeating fetch filter parsing, config-only promisor remote state,
resolver-returned object persistence, default resolver-written pack refresh on
`read()`/`readHeader()`, external refresh through `contains()`/prefix lookup,
object iteration refresh, promisor thin-delta base hydration, promisor pack
inventory, sparse checkout/pathspec behavior, transport, send-pack status
parsing, merge-base, or reference transaction work. The old Gitoxide smart HTTP
rework notes remain stale for this slice because they target receive-pack
redirect/status metadata conflicts, not object database refresh mode.
