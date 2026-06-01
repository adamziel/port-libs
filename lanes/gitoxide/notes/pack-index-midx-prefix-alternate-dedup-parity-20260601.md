# Pack-Index/MIDX Prefix Alternate De-Dup Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T065724Z`

Base accepted HEAD: `cc9294ac19877407e3f202dbdfd54b6a9a8fb67d`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs`
  collects requested prefix candidates in a `HashSet<ObjectId>` while it walks
  every loaded index and loose object database, so duplicate sightings of the
  same object id across repositories do not make the prefix ambiguous.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/handle.rs`
  maps pack-index and multi-pack-index candidate ranges into full object ids
  before inserting them into that set.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/multi_index/access.rs`
  delegates MIDX prefix lookup to the shared pack-index prefix helper, so this
  de-duplication boundary must hold for primary and alternate MIDX files.

## Native PHP Delta

- `ObjectDatabaseTest.php` now covers a WordPress content object present in a
  primary MIDX and repeated through an alternate object directory MIDX. Prefix
  lookup with candidates remains `found` with one object id instead of becoming
  ambiguous from duplicate storage visibility.
- The same test adds a distinct loose object candidate in the alternate store
  and verifies ambiguity contains exactly the distinct object ids, not repeated
  MIDX copies of the same object.
- `examples/wordpress-object-database-multi-pack.php` now reports the
  alternate-MIDX duplicate candidate set and the later alternate loose-candidate
  ambiguity in its smoke summary.

## Verification

- PHP lint:
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php` -> no syntax errors.
  - `php -l lanes/gitoxide/examples/wordpress-object-database-multi-pack.php`
    -> no syntax errors.
- Focused object database:
  - `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `1 test files, 275 assertions, 0 failures`
- Adjacent pack/MIDX/object database gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `3 test files, 458 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`
  - `40 test files, 7888 assertions, 0 failures`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-database-multi-pack.php`
  - exited `0`

Root harness status: `not run - isolated micro-slice`.

## Non-Overlap

This does not repeat accepted pack-index/MIDX candidate ranges, midpoint
expansion, odd-length missing prefixes, full-prefix fallthrough, absent
full-candidate disambiguation, SHA-256 object-database prefix loading, stale
MIDX offset validation, same-store candidate de-duplication, loose path
candidates, promisor refresh, or transport/protocol slices. It is limited to
the dynamic object-database prefix boundary where the same MIDX object id is
visible through both the primary store and an alternate object directory.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
object database, alternates parser, pack-index, multi-pack-index, loose-object,
and WordPress fixture helpers. Full upstream Cargo workspace execution remains
excluded for this isolated micro-slice.
