# Loose Object Header Parity - 2026-05-31

## Upstream Source Truth

- Re-read `gix-odb/src/store_impls/loose/find.rs`.
- Re-read `gix-odb/src/store_impls/loose/mod.rs`.
- Re-read `gix-odb/src/store_impls/dynamic/header.rs`.

Mapped behavior:

- Loose object header lookup uses a bounded 64-byte inflated header window.
- Header lookup returns kind and declared size without requiring the full loose body to be present.
- Full loose reads and integrity verification still reject short or malformed bodies.
- Dynamic object-database header lookup applies replacement refs and searches packed objects before loose stores.

## Native PHP Delta

- `LooseObjectStore::readHeader()` and `tryReadHeader()` inflate only the loose header boundary and reject invalid zlib or overlong headers.
- `ObjectDatabase::readHeader()` returns object type, size, and source for packed, loose, and promisor objects while honoring replacement refs and `withReplacementsIgnored()`.
- `examples/wordpress-object-database.php` now records deployment commit header metadata and replacement-aware draft header metadata for WordPress package/deploy preflight code.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 127 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `37 test files, 3342 assertions, 0 failures`
- `php -l` on changed PHP source, test, and example files
  - all changed PHP files reported no syntax errors
- `php lanes/gitoxide/examples/wordpress-object-database.php`
  - exited 0
- `git diff --check -- lanes/gitoxide`
  - exited 0
- JSON validation for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`
  - exited 0

Root aggregate and full Cargo workspace runners were not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses PHP zlib streaming inflate plus existing native Git object, pack, replacement-ref, and object-database primitives.
