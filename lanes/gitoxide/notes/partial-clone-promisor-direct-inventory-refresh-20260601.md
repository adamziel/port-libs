# Partial Clone Promisor Direct Inventory Refresh - 2026-06-01

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T011834Z`

Base accepted HEAD: `6025aa0c35dc17d20b1c6c068ec52bbef5bf715c`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-odb/src/store_impls/dynamic/mod.rs` documents the default
  `RefreshMode::AfterAllIndicesLoaded` behavior and the `refresh_never()`
  opt-out for handles that should not learn about pack changes.
- `gix-odb/src/store_impls/dynamic/prefix.rs` documents that object database
  prefix lookup may refresh disk files unless refresh mode is `Never`.
- `gix-odb/src/store_impls/dynamic/iter.rs` and
  `gix-odb/src/store_impls/dynamic/load_index.rs` are the selected source
  truth for refreshing loaded pack/index snapshots after lazy promisor fetches
  write new pack/index files under `objects/pack`.

## Native PHP Delta

- `ObjectDatabase::promisorPackNames()`, `hasPromisorPacks()`,
  `promisorObjectIds()`, and `isPromisorObject()` now refresh the object
  storage snapshot when the handle is in normal refresh-on-miss mode.
- Refresh-disabled handles keep the accepted stale-snapshot behavior and do
  not discover external promisor packs through these inventory methods.
- `examples/wordpress-lazy-promisor-fetch.php` now records a direct promisor
  inventory refresh after a WordPress deployment manifest blob is hydrated as a
  new `.promisor` pack.

## Verification

- Red-first focused run after adding the new test and before the source change:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - failed on `object database refreshes promisor inventory after external hydration`
    with `Expected: 2`, `Actual: 1`; run summary was
    `1 test files, 172 assertions, 1 failures`.
- Post-edit focused run:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 180 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 6624 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - all reported no syntax errors.
- JSON checks:
  - `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - `json ok`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - exited `0`
- Lane diff check:
  - `git diff --check -- lanes/gitoxide`
  - exited `0`

Root harness status: `not run - isolated micro-slice`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP object
database, pack/index readers, pack builder fixture support, promisor sidecar
discovery, and refresh-disabled handle mode. No live Git provider, credential
store, upstream binary, or shared support-library activation gate is required.

## Non-Overlap

Historical `port-gitoxide-*.needs-lane-rework.md` notes in the handoff
directory are stale May 25 receive-pack/commit-writer metadata conflicts and
do not target this partial-clone behavior.

This slice does not repeat accepted filter-spec parsing, config-only promisor
hydration, resolver-returned object persistence, resolver side-effect read/
header refresh, external `contains()` or prefix refresh, object iteration/count
refresh, refresh-never behavior, resolver-hydrated thin-delta bases, or
cross-pack promisor REF_DELTA lookup. It is limited to direct promisor sidecar
inventory calls (`promisorPackNames()`, `promisorObjectIds()`,
`isPromisorObject()`, and `hasPromisorPacks()`) discovering a newly hydrated
promisor pack when refresh is enabled.

Mapped coverage remains conservatively unchanged at `1691 / 2886`; the patch
adds PHP behavior evidence and raises full-lane native assertions from `6612`
to `6624`.
