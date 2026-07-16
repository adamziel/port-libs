# Partial Clone Promisor Thin Delta Hydration - 2026-05-31

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260531T220231Z`

Accepted base: `9ef60eb910c3006c081a236c1ec05f4d0e7024c4`

## Upstream Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-pack/src/bundle/write/mod.rs`
- `gix-pack/src/data/input/lookup_ref_delta_objects.rs`
- `gix-pack/src/data/entry/header.rs`
- `gix-pack/src/bundle/find.rs`

Mapped behavior:

- Gitoxide's pack bundle writer accepts a `thin_pack_base_object_lookup` when
  receiving a thin pack, and `LookupRefDeltaObjectsIter` resolves ref-delta
  bases by object id through that lookup before the pack is made usable.
- Ref-delta headers explicitly model bases found in the parent repository,
  which is the boundary partial clones hit when a lazy fetch receives a delta
  against an object already local or promised by the remote.
- The native PHP slice maps the object-store side of that boundary: pack object
  and header reads can now resolve an out-of-pack ref-delta base from local
  object storage, or hydrate the base through the existing
  `PromisorObjectResolver` before applying the delta.

## Native PHP Delta

- `ObjectDatabase` now reads packed objects and packed headers through
  `PackData` external-base callbacks.
- External ref-delta bases are resolved first from local pack/loose stores and
  then, when the repository has promisor state, through the configured promisor
  resolver.
- A recursion guard rejects cyclic cross-pack external-base lookups instead of
  looping.
- The WordPress lazy-promisor example now records a thin promisor delta pack
  whose missing base is hydrated through the resolver.

## Verification

- Baseline focused run before new tests:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 95 assertions, 0 failures`
- Red-first after adding tests, before source fix:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - failed on both thin-delta cases with `REF_DELTA base object not found in pack index`
- Post-edit focused partial-clone run:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 118 assertions, 0 failures`
- Adjacent object/pack gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php lanes/gitoxide/tests/PackDataTest.php lanes/gitoxide/tests/PackBuilderTest.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `4 test files, 520 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 5859 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - all reported no syntax errors.
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - exit `0`
- Whitespace:
  - `git diff --check -- lanes/gitoxide`
  - exit `0`

Root harness status: `not run - isolated micro-slice`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
pack builder, pack/index readers, external-base delta resolver API, loose
object store, promisor sidecar discovery, and promisor resolver interface. Full
upstream Cargo workspace parity remains excluded for this isolated worker
because it would hydrate/build the large feature-heavy workspace.

## Non-overlap

This deepens the accepted partial-clone/promisor hydration cluster without
repeating fetch filter parsing, blob:none inclusion checks, config-only
promisor remote state, resolver-returned whole-object persistence,
resolver-written pack refresh through `read()`/`readHeader()`, external refresh
through `contains()`/prefix lookup, object iteration refresh, promisor pack
inventory, sparse checkout/pathspec behavior, transport, send-pack status
parsing, merge-base, or reference transaction work. The old Gitoxide smart-HTTP
rework notes are stale for this slice because they target receive-pack
redirect/status metadata conflicts, not partial-clone promisor hydration.
