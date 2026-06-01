# Pack-Index/MIDX SHA-256 Alternate Prefix Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T105047Z`

Base accepted HEAD: `e78f87c2f7c92d5cffd9a2382b41cda8d5262de2`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs`
  collects prefix candidates into a `HashSet<ObjectId>` across every loaded
  pack index and loose object store. Duplicate object ids from another index
  remain a single candidate, while a distinct object with the same short
  prefix makes the result ambiguous.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs`
  grows disambiguation candidates one hex nibble at a time until lookup is
  unique, returns `None` for a full object id that is absent when checked
  directly, and otherwise returns the shortest unique prefix.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/multi_index/access.rs`
  delegates MIDX prefix lookup to the shared pack-index lookup helper, and
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix-hash/src/prefix.rs`
  applies the same minimum length and hash-kind width to SHA-1 and SHA-256
  prefixes.

## Native PHP Delta

- `ObjectDatabaseTest.php` now covers SHA-256 dynamic prefix collection where
  the same content object appears in the primary MIDX and an alternate MIDX,
  proving candidate de-duplication remains hash-kind agnostic.
- The same test adds a real loose SHA-256 object in the alternate store with
  the same four-hex prefix, proving the candidate set becomes ambiguous only
  for distinct object ids and that disambiguation grows past the ambiguous
  prefix.
- `examples/wordpress-object-database-multi-pack-sha256.php` now demonstrates
  the WordPress SHA-256 MIDX path with an alternate object directory and a
  loose prefix candidate, without invoking the `git` binary.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` record the focused
  assertion movement and conservative mapped coverage delta.

## Verification

- Before focused change:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 562 assertions, 0 failures`.
- Focused pack/MIDX/object database:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 589 assertions, 0 failures`.
- Focused object database:
  `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `1 test files, 304 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`
  -> `40 test files, 8831 assertions, 0 failures`.
- Changed PHP lint passed for `lanes/gitoxide/tests/ObjectDatabaseTest.php`
  and `lanes/gitoxide/examples/wordpress-object-database-multi-pack-sha256.php`.
- JSON syntax checks passed for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-object-database-multi-pack-sha256.php`
  exited `0`.
- `git diff --check -- lanes/gitoxide` exited `0`.

Root harness status: `not run - isolated micro-slice`.

## Non-Overlap

This does not repeat accepted prefix candidate ranges, midpoint expansion,
generated prefix scans, empty-index handling, full-fallthrough behavior,
absent SHA-1 candidate behavior, stale MIDX offset validation, loose path
candidate handling, SHA-256 primary MIDX prefix reads, or SHA-1 alternate
candidate de-duplication. It is limited to the remaining SHA-256 dynamic
object-database boundary where primary MIDX, alternate MIDX, and loose SHA-256
stores participate in one prefix candidate set.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
pack-index, multi-pack-index, object-database, loose-object, alternate-store,
and SHA-256 fixture helpers. Full upstream Cargo workspace execution remains
excluded for this isolated micro-slice.
