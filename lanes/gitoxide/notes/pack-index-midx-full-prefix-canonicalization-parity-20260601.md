# Pack-Index/MIDX Full-Prefix Canonicalization Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T115928Z`

Base accepted HEAD: `a1aaad0dd5497f12aec9a824cc71bbc08cdd1a03`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-hash/src/prefix.rs`
  builds prefixes from object ids and renders them through `to_hex_with_len`,
  which produces canonical lowercase hex output independent of caller input
  casing.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/access.rs`
  uses `gix_hash::Prefix` for pack-index prefix lookup, and
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/multi_index/access.rs`
  delegates MIDX prefix lookup to that same access helper.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs`
  returns a `Prefix` from disambiguation, so full-length candidates should be
  exposed in the same canonical lowercase form as shorter prefixes.

## Native PHP Delta

- `PackIndexTest.php` now verifies full-length uppercase SHA-1 and SHA-256
  disambiguation inputs return canonical lowercase prefixes.
- `MultiPackIndexTest.php` adds the same full-length uppercase canonicalization
  checks for SHA-1 and SHA-256 MIDX lookups.
- The WordPress pack-index and MIDX examples now expose the full-prefix
  canonicalization boundary for deployment tooling that accepts uppercase
  object ids from user input or UI copy/paste paths.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` record the focused
  assertion growth and conservative mapped coverage delta.

## Verification

- Focused pack/MIDX gate:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php`
  -> `2 test files, 291 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`
  -> `40 test files, 9117 assertions, 0 failures`.
- PHP lint passed for changed test/example PHP files.
- Example smokes:
  `php lanes/gitoxide/examples/wordpress-pack-index.php` and
  `php lanes/gitoxide/examples/wordpress-multi-pack-index.php` exited `0`.
- `git diff --check -- lanes/gitoxide` exited `0`.

Root harness status: `not run - isolated micro-slice`.

## Non-Overlap

This does not repeat accepted candidate ranges, midpoint expansion,
generated-prefix scans, empty-index behavior, full-fallthrough behavior,
absent full-candidate disambiguation, loose path candidates, alternate
candidate de-duplication, SHA-256 alternate object-database handling, stale
MIDX offset validation, or transport/protocol work. It is limited to direct
pack-index and MIDX full-length prefix canonicalization for uppercase caller
input.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
pack-index, multi-pack-index, fixture builder, and WordPress example helpers.
Full upstream Cargo workspace execution remains excluded for this isolated
micro-slice.
