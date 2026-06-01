# Loose Object Missing Type-Size Delimiter Integrity Parity - 2026-06-01

Micro-slice: `gitoxide-loose-object-integrity-parity-20260601T144906Z`
Base accepted HEAD: `0af7c1558eab56b0c7f231815cf34222c9e56c0d`

## Upstream Source Truth

- Re-read upstream `gix-object/src/lib.rs` at manifest commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read upstream `gix-odb/src/store_impls/loose/find.rs` and
  `gix-odb/src/store_impls/loose/verify.rs`.
- `gix_object::decode::loose_header()` first looks for the space delimiter in
  `<type> <size>` and only then looks for the NUL header terminator.
- `gix_odb::loose::Store::try_header()`, `try_find()`, and
  `verify_integrity()` all route compressed loose-object headers through that
  decoder, so a complete inflated byte string such as `blob14wordpressblock`
  reports the missing type-size delimiter instead of the missing-NUL header
  error.

## Native PHP Delta

- `GitObject::decodeLooseHeader()` now reports `Expected '<type> <size>'`
  when the inflated header bytes contain no space delimiter.
- `LooseObjectStore::inflateHeaderBytes()` and
  `inflateStorageBytesExactly()` now preserve that upstream error ordering for
  complete compressed loose streams before falling back to `Did not find 0 byte
  in header` for strings like `blob 4`.
- `GitObjectTest.php` covers direct header decoding, strict storage decoding,
  loose header reads, body reads, `tryReadHeader()`, and integrity wrapping.
- `ObjectDatabaseTest.php` covers the same boundary through primary and
  alternate loose object stores.
- `wordpress-object-header.php` records the WordPress deployment preflight
  smoke for malformed loose-object headers with no type-size delimiter.

## Verification

- Red-first probe before implementation:
  - `php -r 'require "tools/bootstrap.php"; use PortLibs\Gitoxide\GitObject; try { GitObject::decodeLooseHeader("blob123"); } catch (Throwable $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }'`
  - output: `InvalidArgumentException: Did not find 0 byte in header`
- PHP lint:
  - `php -l lanes/gitoxide/src/GitObject.php`
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `php -l lanes/gitoxide/fixtures/wordpress-object-header.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-header.php`
  - all reported no syntax errors.
- Focused object/database gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 594 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 9871 assertions, 0 failures`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-header.php`
  - exited 0.
- JSON/whitespace:
  - `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - `git diff --check -- lanes/gitoxide`
  - both exited 0.

Root aggregate and full upstream Cargo workspace runners were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses PHP zlib inflation, the
existing native loose-object store, object database alternates, and the
existing object-header example/test harnesses.

## Non-Overlap

This does not repeat accepted loose-object positive/negative/zero-padded size
canonicalization, LF-tailed size rejection, missing-NUL headers with a valid
type-size delimiter, allocation limits, empty-file rejection, inflated-size
mismatches, trailing compressed streams, late same-stream overruns, SHA-256
structured decoding, directory candidates, nested candidates, broken symlinks,
case-duplicate candidates, traversal-error handling, or CRLF structured
commit/tag header rejection. The new mapped behavior is limited to complete
inflated loose-object headers with no `<type> <size>` delimiter.
