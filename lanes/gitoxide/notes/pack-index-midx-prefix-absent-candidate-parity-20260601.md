# Pack-Index/MIDX Prefix Absent-Candidate Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T044012Z`

Base: `afcfa557a3b80f26793d8ccfde38278bad8d53e4`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs`
  grows a disambiguation candidate by hex nibble and returns the first prefix
  whose lookup is unique. It only checks exact object existence when the
  caller starts at the full object-id length.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/index/access.rs`
  performs the shared pack-index prefix lookup and reports unique candidate
  ranges for any prefix that maps to one object, regardless of whether the
  caller's full candidate object id exists.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/multi_index/access.rs`
  delegates MIDX prefix lookup to the same pack-index access helper.

## Native PHP Delta

- `PackIndexTest.php` and `MultiPackIndexTest.php` now cover the
  absent-full-candidate edge directly: a missing object id can still
  disambiguate to the shortest prefix that uniquely names a different indexed
  object, while full-length disambiguation still returns `null`.
- `ObjectDatabaseTest.php` adds the same behavior at the dynamic object
  database boundary through a WordPress multi-pack-index fixture.
- `examples/wordpress-object-database-multi-pack.php` now reports the absent
  media candidate's shortest unique MIDX prefix and confirms the full object id
  is not present.

## Verification

- Before focused change:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 409 assertions, 0 failures`.
- Focused pack/MIDX/object database:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 429 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`
  -> `40 test files, 7440 assertions, 0 failures`.
- PHP lint passed for changed tests and
  `examples/wordpress-object-database-multi-pack.php`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-object-database-multi-pack.php`
  exited `0`.
- `git diff --check -- lanes/gitoxide` exited `0`.
- Expected native assertion movement in the focused gate: `409 -> 429`.

## Non-Overlap

This does not repeat accepted prefix candidate ranges, binary-search midpoint
expansion, odd-length missing prefixes, full-prefix fallthrough, candidate
de-duplication, SHA-256 object-database prefix loading, stale MIDX offset
validation, or tree/pathspec work. It is limited to the upstream
disambiguation contract for absent full candidates whose current prefix is
already unique in a pack index, MIDX, or dynamic object database.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
pack-index, MIDX, pack builder, loose-object, object-database, and WordPress
fixture helpers. Full upstream Cargo workspace execution remains excluded for
this isolated micro-slice.
