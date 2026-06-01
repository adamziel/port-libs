# Pack-Index/MIDX Generated Prefix Scan Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T092815Z`

Base accepted HEAD: `c5d5f0d16396d91eb61e17860e23daa5d67075e3`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/tests/pack/index.rs`
  iterates every pack-index entry, creates generated prefixes with
  `gix_hash::Prefix::new()`, and expects `lookup_prefix()` to return the entry
  index plus a one-entry candidate range.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/tests/pack/multi_index/access.rs`
  does the same for MIDX entries with both odd and even generated prefix
  lengths.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/multi_index/access.rs`
  delegates MIDX prefix lookup to the shared pack-index lookup helper, so the
  per-entry generated-prefix behavior must stay identical for direct pack
  indexes and MIDX files.

## Native PHP Delta

- `PackIndexTest.php` now round-trips every entry in a generated pack-index
  fixture through odd/even prefix lengths, verifying `found`, the matched
  object id, one-entry `candidateRange`, and `disambiguatePrefix()` output.
- `MultiPackIndexTest.php` adds the same generated prefix scan for MIDX
  entries, including pack id and pack offset preservation.
- The WordPress pack-index and MIDX examples now expose generated prefix range
  summaries for compacted content objects without invoking the `git` binary.

## Verification

- Before focused change:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php`
  -> `2 test files, 211 assertions, 0 failures`.
- Focused pack/MIDX gate:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php`
  -> `2 test files, 271 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`
  -> `40 test files, 8547 assertions, 0 failures`.
- PHP lint passed for changed test and example PHP files.
- Example smokes:
  `php lanes/gitoxide/examples/wordpress-pack-index.php` and
  `php lanes/gitoxide/examples/wordpress-multi-pack-index.php` exited `0`.

Root harness status: `not run - isolated micro-slice`.

## Non-Overlap

This does not repeat accepted candidate-range, midpoint-expansion, empty-index,
full-fallthrough, absent-candidate, duplicate-visibility, loose-path candidate,
SHA-256 object-database, stale MIDX offset-validation, promisor refresh, or
transport/protocol work. It is limited to the upstream per-entry generated
prefix scan shape for direct pack-index and MIDX access.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
pack-index, multi-pack-index, fixture builders, and WordPress example helpers.
Full upstream Cargo workspace execution remains excluded for this isolated
micro-slice.
