# Partial-clone promisor stale-MIDX orphan parity

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T143358Z`

Accepted base: `21fac6666e98983013fb034373beb2882bf12a1c`

## Source Truth

- Upstream `gix-odb/src/store_impls/dynamic/load_index.rs::collect_indices_and_mtime_sorted_by_size()` admits standalone `.idx` files only when the sibling `.pack` exists, and promotes a matching `multi-pack-index` separately.
- Upstream `gix-odb/src/store_impls/dynamic/load_one.rs::load_pack()` treats a referenced pack that disappeared as recoverable stale state and asks callers to retry with a fresh index snapshot instead of returning data from the wrong pack.
- Upstream `gix-odb/src/store_impls/dynamic/find.rs` retries object lookup after stale pack ids are invalidated. This is the relevant partial-clone boundary when an interrupted promisor fetch leaves stale MIDX metadata beside a later valid promisor pack.

## Native Delta

- `ObjectDatabase::multiPackIndexes()` now skips a stale `multi-pack-index` when it references an orphan promisor `.idx` that still has a `.promisor` marker but no `.pack` data.
- The stricter generic validation remains intact: ordinary missing MIDX-referenced packs still throw through `ObjectDatabaseTest.php`.
- `PartialCloneTest.php` adds a focused fixture where a stale MIDX names one valid promisor pack and one interrupted orphan promisor index; the database falls back to the valid standalone pack, omits the orphan from promisor inventory, and still reads the hydrated object.
- `wordpress-lazy-promisor-fetch.php` records the same WordPress partial-clone deployment boundary in the example smoke.

## Verification

- `php -l lanes/gitoxide/src/ObjectDatabase.php` -> no syntax errors
- `php -l lanes/gitoxide/tests/PartialCloneTest.php` -> no syntax errors
- `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php` -> no syntax errors
- `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php` -> `1 test files, 407 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php` -> `1 test files, 338 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php` -> exit `0`
- `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 9798 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` -> `lane-status json ok`
- `git diff --check -- lanes/gitoxide` -> exit `0`

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native pack/index parsing, multi-pack-index parsing, promisor sidecar inventory, pack bundle writing, lazy object database refresh, and the existing WordPress partial-clone example. No live Git provider, credential store, network service, upstream binary, or shared support-library activation gate is required.

## Non-Overlap

This does not repeat accepted config-only promisor hydration, numeric promisor booleans, external pack refresh, refresh-never behavior, empty/interrupted promisor pack bundle handling, alternate-base thin packs, resolver-repaired thin packs, cross-pack REF_DELTA hydration, direct promisor inventory refresh, orphan standalone promisor index skipping, or generic stale MIDX offset validation. It is limited to stale MIDX recovery when the missing referenced pack is specifically an orphan promisor index from an interrupted partial-clone hydration.
