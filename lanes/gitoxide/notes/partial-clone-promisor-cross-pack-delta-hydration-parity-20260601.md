# Partial Clone Promisor Cross-Pack Delta Hydration Parity

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T000521Z`

Base accepted HEAD: `9938ea0ca5f2430c11f7b91d23d2213507185488`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-odb/src/store_impls/dynamic/find.rs` handles `DeltaBaseUnresolved(base_id)` by recursively looking up the base through the object store instead of treating a REF_DELTA base as local to the current pack.
- `gix-odb/src/store_impls/dynamic/handle.rs` keeps MIDX intra-pack lookups pinned to the same pack index, so a cross-pack REF_DELTA base must be found through the outer store lookup path.
- `gix-pack/src/data/input/lookup_ref_delta_objects.rs` documents REF_DELTA resolution with one lookup per base id, which matches the PHP pack reader's external base callback.

## Native PHP Delta

- Added a focused `PartialCloneTest.php` case for a promisor base object stored in one `.promisor` pack and a thin REF_DELTA target stored in a second `.promisor` pack.
- Extended `wordpress-lazy-promisor-fetch.php` with the same cross-pack promisor delta path so the user-visible WordPress partial-clone smoke records base/target promisor state and decoded target body parity.
- Updated `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` with the exact full-lane evidence and conservative mapped coverage movement from `1675 / 2886` to `1676 / 2886`.

## Verification

- Before this slice: `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php` passed `1 test files, 149 assertions, 0 failures`.
- After this slice: `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php` passed `1 test files, 168 assertions, 0 failures`.
- Full lane focused gate: `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 6423 assertions, 0 failures`.
- Syntax and JSON checks passed:
  - `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - `php -r 'json_decode(... JSON_THROW_ON_ERROR)'` for the two touched JSON files
- Example smoke passed: `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`.

## Non-Overlap And Dependency Closure

- This slice does not repeat accepted config-only promisor hydration, refresh-never behavior, external pack refresh, inventory refresh, or resolver-hydrated thin-delta base behavior. It specifically covers the cross-pack REF_DELTA base lookup path.
- Existing bounded PHP components were reused: `ObjectDatabase`, `PackBuilder::buildWithRefDeltas()`, and the existing `PromisorObjectResolver` interface. No new support component is needed.
- Historical `port-gitoxide-*.needs-lane-rework.md` notes in the handoff directory concern stale receive-pack/request-status and commit-writer metadata conflicts from 2026-05-25; none target this partial-clone cross-pack hydration behavior.
- Full upstream Cargo workspace was not executed for this isolated micro-slice.
