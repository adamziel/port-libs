# Pack-Index/MIDX Duplicate Prefix Candidate-Mode Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T131212Z`

Base accepted HEAD: `a93e599b8ba28b765620aaefefa98a3cad05be92`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/access.rs`
  returns an immediate ambiguous result for `lookup_prefix(prefix, None)` when
  one pack index has more than one matching entry.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/multi_index/access.rs`
  applies the same direct-prefix semantics to multi-pack indexes.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs`
  treats `Some(Err(()))` from a direct index lookup as immediately ambiguous
  when callers did not request candidates.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/handle.rs`
  uses candidate collection for callers that pass a candidate set; those object
  ids are collected into a set, so repeated sightings of the same object id can
  still resolve to one candidate.

## Native PHP Delta

- `ObjectDatabase::lookupPrefix()` now has separate no-candidate and
  candidate-mode paths. Candidate mode keeps the existing de-duplicating scan.
  No-candidate mode walks MIDX, pack indexes, and loose stores source-by-source
  so a single direct pack/MIDX ambiguous result remains ambiguous instead of
  being collapsed by global object-id de-duplication.
- `ObjectDatabaseTest.php` builds a WordPress pack fixture with a valid v2
  index containing the same object entry twice. It verifies no-candidate
  prefix lookup is `ambiguous` while include-candidates lookup is `found` with
  one candidate object id.
- `examples/wordpress-object-database.php` reports the duplicate-index boundary
  in its object-database smoke summary.

## Verification

- Red-first focused check before the source fix:
  `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `1 test files, 320 assertions, 1 failures`; the new duplicate-index case
  expected `ambiguous` and observed `found`.
- Focused object database:
  `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `1 test files, 330 assertions, 0 failures`.
- Focused pack/MIDX/object database gate:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 621 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`
  -> `40 test files, 9471 assertions, 0 failures`.
- PHP lint passed for:
  `lanes/gitoxide/src/ObjectDatabase.php`,
  `lanes/gitoxide/tests/ObjectDatabaseTest.php`, and
  `lanes/gitoxide/examples/wordpress-object-database.php`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-object-database.php`
  -> exited `0`.
- Diff whitespace:
  `git diff --check -- lanes/gitoxide`
  -> exited `0`.

Root harness status: `not run - isolated micro-slice`.

## Non-Overlap

This does not repeat accepted pack-index/MIDX candidate ranges, midpoint
expansion, odd-length missing prefixes, full-prefix fallthrough, absent
full-candidate disambiguation, SHA-256 object-database prefix loading,
alternate-store de-duplication, loose path candidates, stale MIDX offset
validation, promisor refresh, or transport/protocol slices. The behavior is
limited to the boundary between direct no-candidate ambiguity and candidate
HashSet de-duplication when one pack index reports duplicate matching entries.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
object database, pack-index, multi-pack-index, loose-object, and WordPress pack
fixture helpers. Full upstream Cargo workspace execution remains excluded for
this isolated micro-slice.
