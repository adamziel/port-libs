# Reference Transaction Broken Deref Delete Parity - 2026-06-01

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260601T080204Z`

Base accepted HEAD: `924608cb5d0660a91dc7f34f65c3d602f24fd8e6`

Source truth:

- Upstream `gix-ref/src/store/file/transaction/prepare.rs` prepares deletes by
  dereferencing references before lock acquisition, but carries delete-specific
  previous-value checks.
- Upstream `gix-ref/tests/refs/file/transaction/prepare_and_commit/delete.rs`
  has `delete_broken_ref_that_may_not_exist_works_even_in_deref_mode` and
  `delete_broken_ref_that_must_exist_fails_as_it_is_no_valid_ref`.
- Upstream `gix-ref/src/store/file/transaction/commit.rs` publishes prepared
  delete locks after preparation succeeds.

Implemented behavior:

- `ReferenceStore::prepareLooseDeleteTransaction()`, direct delete, and
  delete-with-report now use a delete-specific dereference path.
- In deref mode, a malformed loose reference can still be deleted when the
  previous value is `PREVIOUS_ANY`, matching Gitoxide's unconstrained broken-ref
  cleanup behavior.
- Strict delete modes preserve the broken loose reference and fail before a lock
  is left behind.
- The WordPress reference transaction smoke covers an interrupted checkout that
  leaves a broken tenant `HEAD`: unconstrained cleanup removes it, while strict
  cleanup preserves it.

Red-first evidence:

- Before the source change, the new focused ReferenceStore case failed with
  `Loose reference content could not be parsed` for
  `prepareLooseDeleteTransaction(['HEAD'], PREVIOUS_ANY, null, true)`.
- Red-first command: `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`
  reported `1 test files, 680 assertions, 1 failures`.

Verification:

- `php -l lanes/gitoxide/src/ReferenceStore.php`
- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php`
- `php -l lanes/gitoxide/examples/wordpress-reference-transaction.php`
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`
  passed `1 test files, 696 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  passed `40 test files, 8127 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php` exits `0`.
- `git diff --check -- lanes/gitoxide` passes.

Dependency closure:

- No new support component is needed. The slice reuses the native PHP reference
  store, loose reference reader, prepared transaction locks, namespace handling,
  and reflog/report edit surfaces.

Non-overlap:

- This does not repeat packed-reference transaction update/delete parity,
  packed-lock collision behavior, reflog-only delete behavior, symbolic reflog
  write-mode parity, no-op update lock handling, object database, transport,
  protocol, sparse-checkout, tree/pathspec, or URL/refspec slices. It is bounded
  to broken loose-reference delete preparation in deref mode.
