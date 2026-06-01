# Partial Clone Promisor Interrupted Pack Keep Parity

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T093715Z`

Base accepted HEAD: `9495523910adeabd01c9bc2c77431af9d8027200`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/remote/connection/fetch/receive_pack.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/bundle/write/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/bundle/write/types.rs`

Gitoxide's fetch receive path documents that a `.keep` sidecar is created
before any new pack-related file, including `.pack` or `.idx`, is written so a
freshly received pack is protected from garbage collection until refs are
updated or the caller handles the keep path.

## Native PHP Delta

- `ObjectDatabase::writePromisorPackBundle()` now creates a `.keep` sidecar
  whenever either the target `.pack` or `.idx` file still needs to be
  materialized, not only when the `.pack` file is absent.
- `PartialCloneTest.php` covers an interrupted partial-clone resume where the
  `.pack` exists but the `.idx` and `.promisor` sidecars are missing. The
  resumed bundle now reports and writes the `.keep`, completes the index and
  promisor marker, and remains readable from the promisor pack.
- `wordpress-lazy-promisor-fetch.php` records the same interrupted WordPress
  filtered-pack resume path in the local smoke fixture.

## Red-First Evidence

- `php -r 'require "tools/bootstrap.php"; ... ObjectDatabase::writePromisorPackBundle(...) ...'`
  - Current base output: `red-first: keepName=null when pack exists but idx is new`

## Verification

- `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `No syntax errors detected in lanes/gitoxide/src/ObjectDatabase.php`
- `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - `No syntax errors detected in lanes/gitoxide/tests/PartialCloneTest.php`
- `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - `No syntax errors detected in lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
- `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 314 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php >/tmp/gitoxide-lazy-promisor-fetch.out && wc -c /tmp/gitoxide-lazy-promisor-fetch.out`
  - `0 /tmp/gitoxide-lazy-promisor-fetch.out`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 8552 assertions, 0 failures`

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP pack
builder, pack index reader, pack data reader, object database, filesystem
writer, and WordPress promisor smoke fixture. No live Git provider, credential
store, upstream binary, network service, or shared support-library activation
gate is required.

## Non-Overlap

This deepens the accepted partial-clone/promisor received-pack bundle surface
with interrupted pack/index materialization keep protection. It does not repeat
accepted promisor config hydration, numeric boolean parsing, direct inventory
refresh, refresh-never semantics, resolver-returned object persistence,
alternate-base thin pack hydration, cross-pack REF_DELTA hydration, pack bundle
duplicate handling, external delta recursion bounds, protocol-v2 sideband
parsing, send-pack receive status, reference transactions, sparse checkout, or
transport/auth slices.
