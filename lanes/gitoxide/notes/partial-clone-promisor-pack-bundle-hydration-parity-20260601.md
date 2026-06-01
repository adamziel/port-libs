# Gitoxide Partial Clone Promisor Pack Bundle Hydration Parity

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T044813Z`

Accepted base: `5a7dc1daad24ba95a3c58d82c78018bfc7722899`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/remote/connection/fetch/receive_pack.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/bundle/write/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/bundle/write/types.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/find.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-protocol/src/fetch/types.rs`

Gitoxide writes received fetch packs through the object database as pack
bundles, creates a `.keep` sidecar before new pack/index materialization, and
refreshes dynamic object databases only when the handle is refresh-capable.
Fetch negotiation existence checks use refresh-never object database handles so
freshly hydrated packs do not mutate cached inventory during negotiation.

## Native PHP Delta

- `ObjectDatabase::writePromisorPackBundle()` now writes a received promisor
  pack bundle into the primary `objects/pack` directory with `.keep`,
  `.pack`, `.idx`, and `.promisor` sidecars.
- Duplicate already-present pack/index bundles preserve the existing pack and
  index, update the promisor note, and report `alreadyPresent=true` without
  creating another keep sidecar.
- Refresh-disabled object database handles can write the received pack files
  without refreshing their cached promisor inventory; a refresh-enabled handle
  sees and reads the hydrated object.
- The WordPress lazy promisor example now uses the native bundle writer for
  resolver, external, and direct-inventory hydration paths and records the
  produced keep sidecars.

## Focused Evidence

- Red-first focused check before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php` failed
  with `Call to undefined method PortLibs\Gitoxide\ObjectDatabase::writePromisorPackBundle()`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php` passed
  `1 test files, 234 assertions, 0 failures`.
- Full Gitoxide lane after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 7456
  assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php` exited 0.
- Root harness was not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
pack builder, pack index reader, pack data reader, object database inventory,
and promisor resolver flow. No live remote, credential store, upstream binary,
network service, or additional shared support-library activation gate is
required.

## Non-Overlap

This extends the accepted partial-clone/promisor hydration surface with
upstream-backed received-pack bundle writing and refresh-never inventory
behavior. It does not repeat accepted smart HTTP cookie/path handling,
protocol-v2 sideband parsing, send-pack empty unpack status, receive-pack
content-type/header/proxy handling, object parsing, pack index lookup,
multi-pack index prefix disambiguation, or prior manual promisor fixture
inventory checks.
