# Loose Object SHA-256 Structured Integrity Parity - 2026-05-31

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/verify.rs` at upstream commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read `gix-odb/src/store_impls/loose/find.rs`.
- Re-read `gix-object/src/data.rs`.
- Re-read `gix-object/src/object/mod.rs`.
- Re-read `gix-object/src/tree/ref_iter.rs`, `gix-object/src/commit/decode.rs`,
  and `gix-object/src/tag/decode.rs`.

Mapped behavior:

- `gix_odb::loose::Store::try_find()` returns `gix_object::Data` with the
  store object hash kind attached to the decompressed object body.
- `verify_integrity()` calls `Data::decode()`, and `Data::decode()` passes the
  object hash kind into tree, commit, and tag decoders.
- SHA-256 loose stores therefore decode tree entry object IDs as 32-byte object
  names and parse commit/tag target IDs as 64 hexadecimal characters during
  integrity verification.

## Native PHP Delta

- `Tree::parse()` and `Tree::fromObject()` now accept a hash algorithm and use
  it to choose SHA-1 versus SHA-256 tree entry object-id width.
- `TreeEntry` now accepts canonical SHA-256 tree-entry object IDs in addition
  to SHA-1 IDs.
- `LooseObjectStore::verifyIntegrity()` now passes the store hash algorithm
  through structured tree, commit, and tag decoding.
- `examples/wordpress-object-database.php` now proves a SHA-256-addressed
  WordPress deployment graph containing blob, tree, commit, and tag loose
  objects verifies without shelling out to `git fsck` or `git cat-file`.

## Verification

- Red-first focused run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php`
  - failed as expected before the implementation:
    `1 test files, 118 assertions, 1 failures`
  - failure: `Tree entry is missing mode/name delimiter`
- Post-edit focused gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/TreeTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `3 test files, 326 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 6116 assertions, 0 failures`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-database.php`
  - exited 0.
- PHP lint:
  - `php -l lanes/gitoxide/src/Tree.php`
  - `php -l lanes/gitoxide/src/TreeEntry.php`
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-database.php`
  - all reported no syntax errors.
- JSON/whitespace:
  - `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status.json ok\n";'`
  - `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "UPSTREAM_TEST_MANIFEST.json ok\n";'`
  - `git diff --check -- lanes/gitoxide`
  - all exited 0.

Root aggregate and full upstream Cargo workspace runners were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses PHP hash/zlib support,
the existing native loose-object store, and the existing commit/tag parsers.
The only support boundary added is hash-kind-aware tree entry decoding inside
the Gitoxide lane.

## Non-Overlap

This does not repeat accepted loose-object SHA-256 blob/path/header behavior,
allocation-limit checks, positive/negative signed size canonicalization,
inflated-size mismatch rejection, nested iterator candidates, directory
candidate blockers, traversal-error handling, or empty-file rejection. It is
bounded to structured object decoding during SHA-256 loose-store integrity
verification.
