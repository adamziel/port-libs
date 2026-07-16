# Loose Object Directory Candidate Integrity Parity - 2026-05-31

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/iter.rs`.
- Re-read `gix-odb/src/store_impls/loose/find.rs`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.

Mapped behavior:

- `gix_odb::loose::Store::iter()` yields every path whose last two components
  look like an object id for the store hash kind.
- `verify_integrity()` then calls `try_find()` for each yielded id, so a valid
  object-id-shaped directory or otherwise unreadable path cannot be silently
  skipped as if the store were clean.
- Header and full-object reads distinguish missing objects from object paths
  that exist but are not regular readable loose-object files.

## Native PHP Delta

- `LooseObjectStore::verifyIntegrity()` now scans object-id-shaped path
  candidates separately from the public regular-file `objectIds()` iterator.
- `LooseObjectStore::read()`, `tryRead()`, and `tryReadHeader()` now surface a
  present non-regular object path as a hard read error instead of treating it
  as missing.
- `ObjectDatabase::verifyLooseIntegrity()` inherits the stricter behavior for
  primary and alternate object directories.
- `examples/wordpress-object-database.php` now proves WordPress deployment
  integrity preflight catches a loose-object directory blocker.

## Verification

- Pre-edit focused object/database run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 159 assertions, 0 failures`
- Post-edit focused object/database run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 167 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 4291 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-database.php`
  - all reported no syntax errors.
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-database.php`
  - exited 0.
- Whitespace and JSON checks:
  - `git diff --check -- lanes/gitoxide`
  - `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR);'`
  - both exited 0.

Root aggregate and full Cargo workspace runners were not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses PHP filesystem scanning,
existing native zlib loose-object reads, alternates resolution, and object
database integrity verification.

## Non-Overlap

This does not repeat the accepted loose-object header or SHA-256 hash-kind
integrity slices. The new mapped behavior is the upstream loose iterator and
verification boundary for object-id-shaped paths that exist but are not
readable loose-object files.
