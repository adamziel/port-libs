# Loose Object Write Finalization Parity - 2026-06-01

## Source Truth

- Re-read `gix-odb/src/store_impls/loose/write.rs` at upstream commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read `gix-odb/src/store_impls/loose/find.rs` and `verify.rs` to keep
  the slice bounded to loose-object storage and integrity behavior.

## Upstream Behavior

- `gix_odb::loose::Store::write()` writes through a temporary file in the
  objects directory, finalizes the file as read-only on Unix, and persists it
  into the canonical loose-object path instead of streaming directly to the
  target path.
- An already occupied object path is not clobbered during finalization. This
  matters for integrity checks because a directory or symlink at the object
  path must remain a storage error instead of being overwritten by a later
  write.

## Native PHP Delta

- `LooseObjectStore::write()` now writes compressed bytes to an objects-dir
  temp file, marks the temp file `0444`, creates the fanout directory, and
  links the temp file into place without overwriting an occupied target.
- Existing regular object paths are preserved and returned idempotently.
  Existing directories, symlinks, or other non-regular object paths now raise a
  controlled `RuntimeException` before finalization.
- `examples/wordpress-object-header.php` now proves a WordPress export loose
  object is finalized read-only and a repeated write preserves the existing
  object bytes.

## Verification

Red-first focused test before implementation:

```sh
php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php
# 1 test files, 148 assertions, 2 failures
```

Passing focused and lane verification after implementation:

```sh
php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php
# 1 test files, 155 assertions, 0 failures

php tools/run-tests.php lanes/gitoxide/tests
# 40 test files, 7499 assertions, 0 failures

php lanes/gitoxide/examples/wordpress-object-header.php
# exit 0
```

## Dependency Closure

No new support component is needed. The slice reuses native PHP filesystem
temp-file, chmod, link/unlink, and zlib support already used by the Gitoxide
lane.

## Non-Overlap

This extends accepted loose-object integrity work without repeating SHA-256
hash-kind handling, structured SHA-256 decoding, allocation limits,
positive/negative signed size canonicalization, inflated-size mismatch
rejection, empty-file rejection, traversal-error handling, nested candidates,
case-normalized duplicates, broken symlink reads, interrupt propagation, or
trailing compressed stream handling. The new mapped behavior is write
finalization and non-clobbering of occupied loose-object paths.
