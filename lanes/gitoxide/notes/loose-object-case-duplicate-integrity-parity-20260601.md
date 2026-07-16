# Loose Object Case-Duplicate Integrity Parity - 2026-06-01

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/iter.rs` at upstream commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read `gix-odb/src/store_impls/loose/find.rs`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.
- Re-read `gix-hash/src/object_id.rs` and `faster-hex-0.10.0/src/decode.rs`.

Mapped behavior:

- `loose::Store::iter()` yields each filesystem path candidate from the object
  walk without de-duplicating normalized object IDs.
- `gix_hash::ObjectId::from_hex()` accepts uppercase and lowercase hex bytes,
  so case-variant loose-object path candidates normalize to the same object ID.
- `verify_integrity()` calls `try_find()` for every yielded candidate and
  increments `num_objects` per candidate, not per unique object ID.
- `try_find()` resolves the normalized object ID through the canonical
  lowercase hash path, so a case-variant duplicate candidate verifies the same
  canonical loose object again.

## Native PHP Delta

- `LooseObjectStore::integrityObjectIds()` now preserves duplicate normalized
  path candidates instead of using an associative set.
- `LooseObjectStore::verifyIntegrity()` therefore counts duplicate
  case-normalized iterator candidates like upstream gix-odb.
- `ObjectDatabase::verifyLooseIntegrity()` inherits the same behavior for the
  primary object directory and alternates.
- `examples/wordpress-object-database.php` now proves a WordPress deployment
  preflight reports duplicate case-variant loose-object candidates without
  shelling out to `git fsck`.

## Verification

- PHP lint:
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-database.php`
  - all reported no syntax errors.
- Focused loose-object/ObjectDatabase gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 326 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 6493 assertions, 0 failures`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-database.php`
  - exited 0.
- Whitespace/JSON:
  - `git diff --check -- lanes/gitoxide`
  - exited 0.
  - `lanes/gitoxide/lane-status.json` decoded with `JSON_THROW_ON_ERROR`.

Root aggregate and full upstream Cargo workspace runners were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses PHP zlib/hash support,
the existing native loose-object store, ObjectDatabase alternates, and the
existing WordPress object database example.

## Non-Overlap

This does not repeat accepted loose-object header parsing, SHA-1/SHA-256 hash
kind handling, allocation limits, positive signed size canonicalization,
negative-zero size canonicalization, inflated-size mismatch rejection,
directory candidate blockers, nested stale candidate rejection, empty-file
rejection, traversal-error handling, or SHA-256 structured object decoding.
The new mapped behavior is duplicate loose-object integrity candidate counting
after case normalization.
