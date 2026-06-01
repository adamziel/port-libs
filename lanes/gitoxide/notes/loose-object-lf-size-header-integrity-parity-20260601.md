# Loose Object LF Size Header Integrity Parity - 2026-06-01

## Upstream Source Truth

- Re-read `gix-object/src/lib.rs` at upstream commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read `gix-utils/src/btoi.rs`.
- Re-read `gix-odb/src/store_impls/loose/find.rs`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.

Mapped behavior:

- `gix_object::decode::loose_header()` slices the size bytes between the first
  space and the NUL terminator, then parses the entire slice with
  `gix_utils::btoi::to_signed::<u64>()`.
- That parser rejects LF bytes in the size field as invalid digits.
- `gix_odb::loose::Store::try_header()` and `try_find()` both use that strict
  header decoder, so a compressed `blob 3\n\0abc` loose object is invalid even
  if it is stored under the canonical `blob 3\0abc` object id.
- Loose integrity verification surfaces the same invalid-header failure before
  treating the store as clean.

## Native PHP Delta

- `GitObject::decodeLooseHeader()` now anchors the type/size parser with
  `\A...\z` instead of PHP's `$` newline-tolerant end anchor.
- `LooseObjectStore::readHeader()`, `read()`, and `verifyIntegrity()` now reject
  LF-tailed loose-object size headers.
- `ObjectDatabase::readHeader()`, `read()`, and `verifyLooseIntegrity()` inherit
  the same rejection across alternate loose stores.
- The WordPress loose-object header fixture/example now records that a
  block-content loose object with an LF-tailed size header is rejected without
  invoking `git cat-file`.

## Verification

- Red-first probe before the source change:
  - `GitObject::decodeLooseHeader("blob 3\n\0abc")` returned
    `{"type":"blob","size":3,"headerLength":8}`.
- Post-edit focused run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 485 assertions, 0 failures`
- Full lane run:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 8911 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/GitObject.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `php -l lanes/gitoxide/fixtures/wordpress-object-header.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-header.php`
  - all reported no syntax errors.
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-header.php`
  - exited 0.
- Diff check:
  - `git diff --check -- lanes/gitoxide`
  - passed.

Root aggregate and full upstream Cargo workspace runners were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses existing PHP zlib
loose-object reads, native loose-object header parsing, alternate object store
resolution, and integrity verification.

## Non-Overlap

This does not repeat accepted loose-object positive/negative-zero size parsing,
allocation-limit, SHA-1/SHA-256 integrity, inflated-size mismatch, trailing
stream, truncated/corrupt first-window inflate, case-duplicate, broken symlink,
directory-candidate, or CRLF structured commit/tag body-header slices. The new
mapped behavior is the upstream LF byte rejection inside the loose-object size
field itself.
