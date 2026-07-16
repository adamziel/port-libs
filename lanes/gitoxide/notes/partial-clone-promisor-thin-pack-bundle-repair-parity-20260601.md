# Gitoxide Partial Clone Promisor Thin-Pack Bundle Repair Parity

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T105256Z`

Accepted base: `33333a56ebb8828822e56091b018c21a9ae7058c`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/remote/connection/fetch/receive_pack.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/bundle/write/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/bundle/write/types.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/data/input/lookup_ref_delta_objects.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/find.rs`

Gitoxide writes received fetch packs as pack bundles through the object
database. During bundle writing, `gix-pack` can repair received thin packs by
looking up REF_DELTA bases outside the incoming pack and injecting those base
objects into the final pack data before the new pack/index is used locally.
Partial-clone repositories rely on the dynamic object database and promisor
fetch path to make those bases available when the local object store did not
already contain them.

## Native PHP Delta

- `ObjectDatabase::writePromisorPackBundle()` now accepts an explicit
  `$repairThinPack` mode. Existing callers keep the previous validation-only
  behavior by default.
- In repair mode, a thin promisor bundle resolves missing REF_DELTA bases from
  local objects first, then through the configured `PromisorObjectResolver`,
  and rewrites the bundle through `PackData::repairThinPack()` before writing
  `.pack`, `.idx`, `.promisor`, and `.keep` sidecars.
- The repaired write reports a complete object inventory, a changed pack
  checksum, and a stored pack/index that can read both the external base and
  target objects from a fresh object database handle.
- `wordpress-lazy-promisor-fetch.php` now records a received thin-pack repair
  smoke with resolver requests, repaired object count, pack checksum change,
  stored pack/index counts, and fresh promisor-present target state.

## Focused Evidence

- `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `No syntax errors detected in lanes/gitoxide/src/ObjectDatabase.php`
- `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - `No syntax errors detected in lanes/gitoxide/tests/PartialCloneTest.php`
- `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - `No syntax errors detected in lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
- `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 345 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 8905 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php >/tmp/gitoxide-lazy-promisor-fetch.out && wc -c /tmp/gitoxide-lazy-promisor-fetch.out`
  - `0 /tmp/gitoxide-lazy-promisor-fetch.out`
- `git diff --check -- lanes/gitoxide`
  - passed with no output

Root harness was not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses existing native PHP pack
data parsing, pack-index parsing, pack repair, object database inventory, and
the promisor resolver interface. No live remote, credential store, network
provider, upstream Cargo workspace, or additional support-library activation
gate is required.

## Non-Overlap

This slice does not repeat accepted promisor config hydration, numeric boolean
config parsing, refresh-never behavior, external pack refresh, direct promisor
inventory refresh, alternate-base thin-pack reads, cross-pack delta hydration,
interrupted pack keep-sidecar handling, or external-delta recursion bounds. It
is limited to repairing a received thin REF_DELTA promisor pack bundle during
bundle writing so the stored pack is complete and readable through a fresh
object database.
