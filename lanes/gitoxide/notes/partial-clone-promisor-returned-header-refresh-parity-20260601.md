# Partial Clone Promisor Returned Header Refresh Parity

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T154957Z`

Base accepted HEAD: `3a5b6993a235a391c0843d9846854d33a932523d`

## Source Truth

Upstream checkout: `/home/claude/port-libs/.upstream-cache/gitoxide`

Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`

Relevant upstream files:

- `gix-odb/src/store_impls/dynamic/header.rs`
- `gix-odb/src/store_impls/dynamic/load_index.rs`
- `gix-odb/src/store_impls/dynamic/mod.rs`
- `gix-protocol/src/fetch/negotiate.rs`
- `gix/src/remote/connection/fetch/receive_pack.rs`

Upstream dynamic ODB header lookup keeps retrying after a miss by calling
`load_one_index()` on refresh-enabled handles. When a lazy promisor fetch
materializes a pack/index pair, a normal handle can observe the new on-disk pack
state and decode the header from pack storage rather than from a transient
fetch return value.

## Native Delta

`ObjectDatabase::readHeader()` now rechecks local object storage after
`PromisorObjectResolver::resolvePromisedObject()` returns a `GitObject`. If the
resolver hydrated a loose object or promisor pack as part of the fetch, the
header is reported from the persisted local source. Only when no local hydrated
header is visible does the method fall back to a synthetic `source =>
promisor` header from the returned object.

The focused test writes a promisor pack from inside the resolver, returns the
same object, and proves `readHeader()` reports `source => pack`, a fresh
database reads the same pack header, and the hydrated body bytes are durable.
The WordPress lazy promisor example now includes the same header-first returned
object path for block-style bytes.

## Red-First Evidence

- `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - before the source fix: `1 test files, 409 assertions, 1 failures`
  - failing assertion: expected `source => pack`, actual `source => promisor`

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 422 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `2 test files, 775 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php >/tmp/gitoxide-lazy-promisor-fetch.out && wc -c /tmp/gitoxide-lazy-promisor-fetch.out`
  - `0 /tmp/gitoxide-lazy-promisor-fetch.out`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 10070 assertions, 0 failures`
- `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `No syntax errors detected in lanes/gitoxide/src/ObjectDatabase.php`
- `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - `No syntax errors detected in lanes/gitoxide/tests/PartialCloneTest.php`
- `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - `No syntax errors detected in lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/gitoxide`
  - pass

Focused assertion delta: `PartialCloneTest.php` moved from `407` assertions to
`422` assertions for this slice.

Mapped denominator movement: unchanged at `1802 / 2886`; this is deeper parity
inside the existing partial-clone/promisor coverage unit.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
`ObjectDatabase`, `PromisorObjectResolver`, `GitObject`, `PackBuilder`, pack
index reader, promisor sidecar inventory, and WordPress lazy-promisor example
fixture.

## Non-Overlap

This does not repeat prior config-only promisor detection, numeric promisor
config parsing, refresh-never lookup behavior, refresh-never returned-object
behavior, direct inventory refresh, null-return resolver pack refresh, empty
promisor packs, interrupted pack/index sidecars, stale MIDX orphan handling,
thin ref-delta hydration, cross-pack deltas, alternate-base hydration, thin
pack bundle repair, or external delta recursion-bound coverage.
