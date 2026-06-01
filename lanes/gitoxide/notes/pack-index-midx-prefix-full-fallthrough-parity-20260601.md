# Pack-Index/MIDX Prefix Full-Fallthrough Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T022255Z`

Base: `28ec15ab9aa5188bc23d7c22caf22b5083cf6e4e`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs`
  at upstream commit `87433ed33eee9ba974111d20b854f6acb07cd4a6` increments a
  disambiguation candidate one hex nibble at a time while each shorter prefix
  remains ambiguous. If the loop reaches the object hash's full hex length, it
  returns the full candidate prefix; exact existence is checked only when the
  caller starts at full length.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/access.rs`
  is the shared pack-index prefix lookup implementation. It reports ambiguity
  from adjacent candidate ranges and resets missing candidate ranges to `0..0`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/multi_index/access.rs`
  delegates MIDX prefix lookup to the same pack-index helper, so the
  disambiguation fallthrough must be consistent for direct pack indexes,
  multi-pack indexes, and dynamic object-database lookups.

## Native PHP Delta

- `PackIndex::disambiguatePrefix()`, `MultiPackIndex::disambiguatePrefix()`,
  and `ObjectDatabase::disambiguatePrefix()` now return the full candidate
  object id when every shorter prefix remains ambiguous, matching upstream
  `gix-odb`.
- Starting at the full object-id length still checks exact existence and
  returns `null` for a missing object, preserving the existing full-prefix
  boundary.
- `examples/wordpress-object-database-multi-pack.php` now exposes this
  WordPress deployment repository edge: a synthetic missing candidate is
  returned as a full disambiguated prefix after two loose candidates keep all
  shorter prefixes ambiguous, while `contains()` remains false.

## Verification

- Red-first focused gate before the source change:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 382 assertions, 3 failures`.
- After source change focused gate:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 390 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`
  -> `40 test files, 6855 assertions, 0 failures`.
- PHP lint passed for changed source, test, and example PHP files.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-object-database-multi-pack.php`
  exited `0`.
- Diff check:
  `git diff --check -- lanes/gitoxide` exited `0`.

## Non-Overlap

This extends accepted pack-index/MIDX prefix candidate ranges, binary-search
midpoint expansion, odd-length missing prefix handling, full-id candidate
ranges, object-database candidate aggregation, stale MIDX offset validation,
and SHA-256 MIDX object-database prefix parity. It is limited to the upstream
dynamic disambiguation fallthrough after all shorter prefixes remain ambiguous.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
pack-index, MIDX, loose-object, object-database, and WordPress fixture helpers.
Full upstream Cargo workspace execution remains excluded for this isolated
micro-slice.
