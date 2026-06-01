# Partial-clone promisor orphan-index hydration parity

Slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T120216Z`

Accepted base: `5b3a92fac14e00372ad9ece599226a1c8024ea79`

## Source Truth

- Upstream Gitoxide `gix-odb/src/store_impls/dynamic/load_index.rs::collect_indices_and_mtime_sorted_by_size()` only admits `.idx` files when the sibling `.pack` exists.
- The same dynamic store path logs failed index loads and continues, so interrupted pack discovery should not poison later object lookup.
- This lane keeps the existing PHP port's stricter generic incomplete-pack guard, but applies the upstream skip behavior to orphan `.idx` files with a `.promisor` marker.

## Native Delta

- `ObjectDatabase::packBundles()` now ignores orphan promisor indexes instead of throwing while scanning object packs.
- Ordinary incomplete non-promisor pack pairs still throw, and MIDX entries that reference missing packs still fail through existing MIDX validation.
- `PartialCloneTest.php` covers an interrupted `.idx/.promisor` without `.pack` beside a later valid promisor pack, proving the orphan is omitted from promisor pack names/object ids and does not block hydrated object reads.
- `wordpress-lazy-promisor-fetch.php` records the same WordPress lazy-promisor interruption boundary in the example smoke.

## Verification

- Red-first: `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php` failed before the production change with `Pack data file not found for index` in the new orphan-promisor cases.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php` passed `1 test files, 358 assertions, 0 failures`.
- Adjacent guard: `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php` passed `1 test files, 309 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 9162 assertions, 0 failures`.
- Full upstream Cargo workspace was not executed for this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native pack/index parsing, promisor sidecar inventory, pack bundle writing, and lazy object database refresh behavior already present under `lanes/gitoxide`.
