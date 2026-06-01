# Partial-clone promisor kept-orphan index parity

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T171633Z`

Base accepted HEAD: `fda1cd3d5dbdd3d6917df87baa4dec19998fdab2`

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/gitoxide`
- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-odb/src/store_impls/dynamic/load_index.rs::collect_indices_and_mtime_sorted_by_size()` admits standalone `.idx` files only when the sibling `.pack` exists.
- `gix-odb/src/store_impls/dynamic/load_one.rs::load_pack()` treats stale pack ids as recoverable and tells callers to retry with a fresh index snapshot.
- `gix/src/remote/connection/fetch/receive_pack.rs` documents `.keep` sidecars around received fetch packs so interrupted pack writes can be protected during ref updates.

## Native Delta

- `ObjectDatabase::packBundles()` now skips `.idx + .keep` entries without `.pack` data only when the repository has promisor remote configuration.
- `ObjectDatabase::multiPackIndexes()` now treats MIDX references to those kept orphan indexes as stale and falls back to the valid standalone promisor packs.
- Ordinary incomplete non-promisor pack pairs and generic missing MIDX packs still fail through `ObjectDatabaseTest.php`.
- `PartialCloneTest.php` adds direct kept-orphan inventory/read coverage and a stale-MIDX variant.
- `wordpress-lazy-promisor-fetch.php` records the WordPress partial-clone kept-index interruption boundary in the local smoke.

## Verification

- Red-first:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - failed on the two new kept-orphan cases with `Pack data file not found for index`
  - summary: `1 test files, 422 assertions, 2 failures`
- Focused after fix:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 447 assertions, 0 failures`
- Adjacent guard:
  - `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `1 test files, 378 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 10302 assertions, 0 failures`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - exited `0`
- PHP lint:
  - `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - all reported no syntax errors
- JSON and diff checks:
  - `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
  - `git diff --check -- lanes/gitoxide`
  - exited `0`

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native pack/index
parsing, multi-pack-index parsing, promisor remote config parsing, pack bundle
fixtures, and the existing WordPress partial-clone example. No live Git
provider, network service, credential store, upstream binary, or shared
support-library activation gate is required.

## Non-overlap

This does not repeat accepted config-only promisor hydration, numeric promisor
booleans, external pack refresh, refresh-never behavior, empty/interrupted
promisor pack bundle handling, alternate-base thin packs, resolver-repaired
thin packs, cross-pack REF_DELTA hydration, direct promisor inventory refresh,
orphan `.idx + .promisor` skipping, stale `.promisor` MIDX recovery, or generic
pack/MIDX missing-pack validation. It is limited to interrupted partial-clone
fetch state where `.keep` protects an orphan `.idx` until a later valid promisor
pack is hydrated.
