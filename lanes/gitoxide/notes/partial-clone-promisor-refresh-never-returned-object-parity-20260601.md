# Partial Clone Promisor Refresh-Never Returned Object Parity

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T022523Z`

Base accepted HEAD: `aae30af0e20a252fbc6d49ffeaf4400dbc5a6747`

## Source Truth

Upstream checkout: `/home/claude/port-libs/.upstream-cache/gitoxide`

Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`

Relevant upstream files:

- `gix-odb/src/store_impls/dynamic/mod.rs`
- `gix-odb/src/store_impls/dynamic/handle.rs`
- `gix-odb/src/store_impls/dynamic/load_index.rs`
- `gix-protocol/src/fetch/negotiate.rs`
- `gix/src/remote/connection/fetch/receive_pack.rs`

The upstream behavior is that dynamic ODB handles created with `RefreshMode::Never`
do not refresh pack or multi-pack-index state from disk during negotiation-style
object lookups, even though the broader fetch flow may use a normal refreshed
store after pack receipt.

## Native Delta

`ObjectDatabase::resolvePromisedObject()` now persists a resolver-returned object
to the primary loose store without unconditionally refreshing object storage.
When `withObjectStorageRefreshDisabled()` is in effect, resolver side-effect
`.promisor` packs remain hidden from that handle until a fresh or refresh-enabled
database instance observes them.

This keeps the existing refresh-enabled hydration behavior intact while matching
upstream refresh-never semantics for failed/missing-object-heavy negotiation
paths.

## Verification

- `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `No syntax errors detected in lanes/gitoxide/src/ObjectDatabase.php`
- `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - `No syntax errors detected in lanes/gitoxide/tests/PartialCloneTest.php`
- `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - `No syntax errors detected in lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
- `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 198 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php >/tmp/gitoxide-lazy-promisor-example.out && wc -c /tmp/gitoxide-lazy-promisor-example.out`
  - `0 /tmp/gitoxide-lazy-promisor-example.out`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 6950 assertions, 0 failures`
- `git diff --check -- lanes/gitoxide`
  - pass

Focused assertion delta: `+18` over the prior lane-status count of `6932`.

Mapped denominator movement: unchanged at `1707 / 2886`; this is deeper parity
inside the existing partial-clone/promisor coverage unit.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
`ObjectDatabase`, `PromisorObjectResolver`, loose object store, `PackBuilder`,
pack index, and promisor inventory helpers.

## Non-Overlap

This does not repeat prior config-only promisor detection, refresh-enabled
resolver pack hydration, direct promisor inventory refresh, prefix/object-id
refresh paths, null-result refresh-never side effects, thin ref-delta base
hydration, or cross-pack promisor delta coverage. Stale May 25 Gitoxide rework
notes for receive-pack and commit-writer metadata were checked and do not apply
to this partial-clone slice.
