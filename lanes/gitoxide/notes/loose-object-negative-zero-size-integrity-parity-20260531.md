# Loose Object Negative-Zero Size Integrity Parity - 2026-05-31

## Upstream Source Truth

- Re-read `gix-object/src/lib.rs` at upstream commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read `gix-utils/src/btoi.rs`.
- Re-read `gix-odb/src/store_impls/loose/find.rs`.
- Re-read `gix-odb/src/store_impls/loose/verify.rs`.

Mapped behavior:

- `gix_object::decode::loose_header()` parses the `<type> <size>\0` size with
  `gix_utils::btoi::to_signed::<u64>()`.
- That parser accepts positive signs and also accepts negative zero forms such
  as `-0` and `-000`, because subtracting zero from unsigned zero does not
  underflow.
- Non-zero negative sizes still underflow and are rejected.
- Loose-object integrity re-encodes the parsed kind/body through the canonical
  loose header, so a `blob -0\0` file verifies only when stored under the
  canonical empty-blob object id.

## Native PHP Delta

- `GitObject::decodeLooseHeader()` now accepts `-0` and `-000` size fields and
  canonicalizes them to integer zero.
- Non-zero negative sizes such as `-4` and `-04` remain invalid.
- `LooseObjectStore::readHeader()`, `read()`, and `verifyIntegrity()` inherit
  the signed-zero behavior for compressed loose objects.
- `examples/wordpress-object-header.php` now proves signed zero loose headers
  canonicalize without invoking `git cat-file`.

## Verification

- Red-first focused run after adding the new assertions:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php`
  - failed on `blob -0` parsing and the updated example:
    `1 test files, 86 assertions, 3 failures`.
- Post-edit focused run:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php`
  - `1 test files, 118 assertions, 0 failures`.
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 5924 assertions, 0 failures`.
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-header.php`
  - exited 0.

Root aggregate and full upstream Cargo workspace runners were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses PHP integer parsing,
existing zlib loose-object reads, and native object database integrity
verification.

## Non-Overlap

This does not repeat accepted loose-object header, SHA-256, allocation-limit,
inflated-size, directory-candidate, nested-iterator, traversal-error, empty-file,
or positive-size integrity slices. The new mapped behavior is the upstream
negative-zero size boundary and its effect on canonical loose-object integrity
verification.
