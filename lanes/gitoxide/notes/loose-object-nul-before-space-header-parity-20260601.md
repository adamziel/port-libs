# Loose Object NUL-Before-Space Header Parity - 2026-06-01

Micro-slice: `gitoxide-loose-object-integrity-parity-20260601T173218Z`
Base accepted HEAD: `9b7f72e7da02721a548034c2c01c4d151fbb5234`

## Upstream Source Truth

- Re-read upstream `gix-object/src/lib.rs` at manifest commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read upstream `gix-object/src/kind.rs`.
- Re-read upstream `gix-odb/src/store_impls/loose/find.rs`.
- Re-read upstream `gix-odb/src/store_impls/loose/verify.rs`.

Mapped behavior:

- `gix_object::decode::loose_header()` finds the first space delimiter before
  it searches for the NUL terminator.
- `Kind::from_bytes()` is then applied to all bytes before that first space.
- A malformed loose header such as `blob\0 3abc` is therefore an unknown object
  kind (`blob\0`) rather than a missing `<type> <size>` delimiter.
- `gix_odb::loose::Store::try_header()`, `try_find()`, and
  `verify_integrity()` all route the first inflated header window through that
  same decoder.

## Native Delta

- `LooseObjectStore::inflateHeaderBytes()` no longer truncates at an early NUL
  when no space delimiter has appeared before it.
- `LooseObjectStore::inflateStorageBytesExactly()` now keeps the same ordering
  for full loose-object reads and integrity checks.
- `GitObjectTest.php` covers direct header decoding, loose header/body reads,
  `tryReadHeader()`, and `verifyIntegrity()`.
- `ObjectDatabaseTest.php` covers primary and alternate loose stores.
- `wordpress-object-header.php` includes a WordPress block-content smoke path
  for the same malformed header ordering.

## Verification

- Red-first probe before implementation:
  - `php -r 'require "tools/bootstrap.php"; ... gzcompress("blob\0 3abc") ... $store->readHeader($oid); $store->read($oid);'`
  - output:
    - `InvalidArgumentException: Expected '<type> <size>'`
    - `InvalidArgumentException: Expected '<type> <size>'`
- PHP lint:
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-header.php`
  - `php -l lanes/gitoxide/fixtures/wordpress-object-header.php`
  - all reported no syntax errors.
- Focused object/database gate:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 672 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 10337 assertions, 0 failures`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-header.php`
  - exited 0.
- Diff check:
  - `git diff --check -- lanes/gitoxide`
  - passed.

Root aggregate and full upstream Cargo workspace runners were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses PHP zlib inflation, the
existing native loose-object store, object database alternates, structured
object decode checks, and the existing lane test harness.

## Non-Overlap

This does not repeat accepted loose-object positive, negative-zero, or
zero-padded size canonicalization; LF-tailed size rejection; missing-NUL or
missing type-size delimiter ordering; ordinary unknown-kind ordering;
allocation limits; empty-file rejection; inflated-size mismatches; trailing
compressed streams; late same-stream overruns; SHA-256 structured decoding;
directory candidates; nested candidates; broken symlinks; case-duplicate
candidates; traversal-error handling; interruption callbacks; or CRLF
structured commit/tag body-header rejection. The new mapped behavior is limited
to malformed loose-object headers whose first NUL byte appears before the first
space delimiter.
