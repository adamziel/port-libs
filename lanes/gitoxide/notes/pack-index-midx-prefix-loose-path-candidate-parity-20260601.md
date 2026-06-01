# Pack-Index/MIDX Prefix Loose Path Candidate Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T054535Z`

Base accepted HEAD: `06912dc408a93b4423231b55bdd13f99aa431658`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs`
  combines prefix lookup outcomes across loaded pack indexes, multi-pack
  indexes, and loose object stores.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/handle.rs`
  maps pack-index and MIDX prefix candidate entry ranges into full object ids.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/loose/find.rs`
  scans the loose two-hex directory for prefix lookup and treats valid
  object-id path names as candidates before object contents are read.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/loose/iter.rs`
  derives loose object ids from path components without requiring the path to
  be a regular readable object file at prefix lookup time.

## Native PHP Delta

- `LooseObjectStore::prefixObjectIds()` now performs a prefix-specific loose
  path scan under the two-hex object directory and returns valid object-id path
  candidates, including directory or symlink candidates, without reading
  object contents.
- `ObjectDatabase::prefixMatches()` now uses that prefix-specific loose scan
  while combining MIDX, standalone pack-index, and loose candidates.
- `ObjectDatabaseTest.php` covers a WordPress multi-pack-index content object
  whose four-hex prefix is shared by a loose directory candidate. Prefix
  lookup now reports ambiguity and candidate ids before `contains()` confirms
  the directory candidate is not a readable loose object.
- `examples/wordpress-object-database-multi-pack.php` exposes the same
  deployment repository edge in its smoke summary.

## Verification

- Red-first focused check after adding the new assertion, before the source
  change:
  `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php` failed
  on `object database treats loose path candidates as MIDX prefix candidates
  like gix-odb` with `Expected: 'ambiguous'`, `Actual: 'found'`; summary
  `1 test files, 247 assertions, 1 failures`.
- Focused object database after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php` passed
  `1 test files, 255 assertions, 0 failures`.
- Focused pack/MIDX/object-database gate:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  passed `3 test files, 438 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)`
  passed `40 test files, 7638 assertions, 0 failures`.
- PHP lint passed for `lanes/gitoxide/src/LooseObjectStore.php`,
  `lanes/gitoxide/src/ObjectDatabase.php`,
  `lanes/gitoxide/tests/ObjectDatabaseTest.php`, and
  `lanes/gitoxide/examples/wordpress-object-database-multi-pack.php`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-object-database-multi-pack.php`
  exited `0`.
- `git diff --check -- lanes/gitoxide` exited `0`.

Root harness status: `not run - isolated micro-slice`.

## Non-Overlap

This does not repeat accepted pack-index/MIDX candidate ranges, midpoint
expansion, odd-length missing prefixes, full-prefix fallthrough, absent
full-candidate disambiguation, SHA-256 object-database prefix loading, stale
MIDX offset validation, candidate de-duplication across MIDX/standalone
pack/loose stores, loose-object integrity, or partial-clone promisor refresh
work. The new surface is limited to the dynamic object-database prefix
boundary where upstream loose path candidates participate in MIDX prefix
disambiguation before object contents are read.

## Dependency Closure

No new support component is needed. This reuses the native PHP loose-object
store, pack-index, multi-pack-index, object database, and existing WordPress
multi-pack fixtures. No live remote, credential store, upstream binary, or
shared support-library activation gate is required.
