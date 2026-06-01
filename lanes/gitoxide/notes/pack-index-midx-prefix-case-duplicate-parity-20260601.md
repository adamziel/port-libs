# Pack-Index/MIDX Prefix Case-Duplicate Parity - 2026-06-01

Slice: `gitoxide-pack-index-midx-prefix-parity-20260601T142619Z`

Base accepted HEAD: `a5614704e60ea0cab87726a10629a257ac3e49fd`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/prefix.rs`
  dispatches no-candidate prefix lookup across packs, multi-pack indexes, and
  loose stores, returning ambiguity as soon as one backing store reports more
  than one matching object-id path.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/dynamic/handle.rs`
  converts pack-index and MIDX candidate ranges into object ids before dynamic
  prefix candidate collection merges results.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/loose/find.rs`
  scans loose object path names for prefix matches before reading object
  contents.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-hash/src/prefix.rs`
  backs candidate-mode prefix collection with object-id uniqueness, so
  duplicate loose path spellings normalize to one candidate id.

## Native PHP Delta

- `LooseObjectStore::prefixObjectIds()` now accepts a duplicate-preserving
  mode for dynamic no-candidate prefix lookup while preserving the existing
  de-duplicated default for candidate collection.
- `ObjectDatabase::lookupPrefixWithoutCandidates()` uses the
  duplicate-preserving loose scan so case-variant loose object path spellings
  for the same object id remain ambiguous beside MIDX, matching upstream
  gix-odb behavior.
- Candidate-mode prefix lookup still de-duplicates repeated MIDX, pack-index,
  and loose object ids.
- `ObjectDatabaseTest.php` covers a WordPress multi-pack fixture where the
  content object exists in MIDX and as two case-normalized loose path
  candidates.
- `examples/wordpress-object-database-multi-pack.php` exposes the same edge in
  the local smoke summary.

## Verification

- Red-first focused check after adding the new assertion, before the source
  change:
  `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php` failed
  on `object database preserves loose case-duplicate ambiguity beside MIDX
  without candidate collection like gix-odb`; expected `ambiguous`, actual
  `found`; summary `1 test files, 341 assertions, 1 failures`.
- Focused object database after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php` passed
  `1 test files, 345 assertions, 0 failures`.
- Focused pack/MIDX/object-database gate:
  `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  passed `3 test files, 636 assertions, 0 failures`.
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

This does not repeat accepted pack-index/MIDX candidate-range expansion,
midpoint expansion, odd-length missing prefixes, full-prefix fallthrough,
absent full-candidate disambiguation, SHA-256 object-database prefix loading,
stale MIDX offset validation, loose path candidate scanning, MIDX/pack/loose
candidate de-duplication, loose-object integrity, or partial-clone promisor
refresh work. The new surface is limited to the no-candidate dynamic lookup
boundary where upstream loose path candidates can be ambiguous even when they
normalize to the same object id.

## Dependency Closure

No new support component is needed. This reuses the native PHP loose-object
store, pack-index, multi-pack-index, object database, and existing WordPress
multi-pack fixtures. No live remote, credential store, upstream binary, or
shared support-library activation gate is required.
