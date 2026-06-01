# Pack-Index/MIDX Prefix De-Dup Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T032839Z`

Base: `639880c48c54d40c3ed0188758af6aee8d8d2712`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs`
  collects prefix candidates in a `HashSet<ObjectId>` when callers request
  candidates, so duplicate sightings of the same object id do not make a
  prefix ambiguous.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/handle.rs`
  maps both pack-index and multi-pack-index candidate entry ranges into object
  ids before inserting them into that set.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/multi_index/access.rs`
  delegates MIDX prefix lookup to the shared pack-index prefix helper, so a
  dynamic object database can see the same object through MIDX, standalone pack
  indexes, and loose stores while preserving a single candidate identity.

## Native PHP Delta

- `ObjectDatabaseTest.php` now covers a WordPress content object reachable from
  a MIDX, duplicated in an extra standalone pack index, and duplicated in loose
  storage. Prefix lookup with candidates remains `found` with one object id,
  and `disambiguatePrefix()` stays at the shortest unique prefix.
- `examples/wordpress-object-database-multi-pack.php` now reports the same
  MIDX plus loose duplicate-object prefix de-duplication in its smoke summary.

## Verification

- Before focused change:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 394 assertions, 0 failures`.
- Focused object database:
  `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `1 test files, 233 assertions, 0 failures`.
- Focused pack/MIDX/object database gate:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  -> `3 test files, 404 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`
  -> `40 test files, 7144 assertions, 0 failures`.
- PHP lint passed for `lanes/gitoxide/tests/ObjectDatabaseTest.php` and
  `lanes/gitoxide/examples/wordpress-object-database-multi-pack.php`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-object-database-multi-pack.php`
  exited `0`.

## Non-Overlap

This does not repeat pack-index/MIDX prefix candidate ranges, midpoint
expansion, odd-length missing prefixes, full-prefix fallthrough, stale MIDX
offset validation, or SHA-256 object-database prefix loading. The new surface
is limited to dynamic candidate de-duplication when the same object id is
visible through MIDX, a standalone pack index, and loose storage.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
pack builder, pack-index, MIDX, loose-object, object-database, and WordPress
fixture helpers. Full upstream Cargo workspace execution remains excluded for
this isolated micro-slice.
