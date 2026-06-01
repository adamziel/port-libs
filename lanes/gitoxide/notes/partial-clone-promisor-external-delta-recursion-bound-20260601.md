# Partial Clone Promisor External Delta Recursion Bound

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T070121Z`

Base accepted HEAD: `cc9294ac19877407e3f202dbdfd54b6a9a8fb67d`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/find.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/header.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/handle.rs`

Gitoxide dynamic object database handles cap REF_DELTA base recursion through
`max_recursion_depth`, using the same bound for object lookup and header-only
lookup. This matters for partial-clone/promisor hydration because a received
thin pack can require recursive external-base lookup across local promisor
packs.

## Native PHP Delta

- `ObjectDatabase` now rejects external REF_DELTA base chains once the promisor
  hydration stack exceeds the gix-style bound of 32 unique objects.
- The guard is applied to both `read()` and `readHeader()` external-base
  hydration paths.
- `PartialCloneTest.php` builds a 32-delta promisor pack chain that still
  resolves and a 40-delta chain that proves both object and header hydration
  hit the recursion guard instead of walking an unbounded chain.
- `wordpress-lazy-promisor-fetch.php` records the same deep-chain guard in the
  WordPress partial-clone smoke.

## Red-First Evidence

- `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
- Before the source change, the new deep-chain case failed because header
  lookup did not hit a recursion guard: `1 test files, 243 assertions, 1
  failures`.

## Verification

- `php -l lanes/gitoxide/src/ObjectDatabase.php`
- `php -l lanes/gitoxide/tests/PartialCloneTest.php`
- `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
- `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php` -> `1
  test files, 253 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php` -> exit 0,
  no output
- `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 7886
  assertions, 0 failures`
- `git diff --check -- lanes/gitoxide` -> passed

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP pack
builder, pack reader, pack index reader, object database, and promisor resolver
flow. No live Git provider, credential store, upstream binary, network service,
or shared support-library activation gate is required.

## Non-Overlap

This does not repeat accepted promisor config hydration, numeric promisor
boolean parsing, pack bundle writing, direct inventory refresh,
refresh-never semantics, resolver-returned object persistence, loose-base
thin-delta hydration, or cross-pack promisor REF_DELTA lookup. It is limited
to the upstream recursion guard for long external REF_DELTA hydration chains.
