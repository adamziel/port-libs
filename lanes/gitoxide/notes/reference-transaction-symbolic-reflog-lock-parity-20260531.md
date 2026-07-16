# Reference Transaction Symbolic Reflog Lock Parity - 2026-05-31

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260531T214845Z`

Source truth:

- Upstream `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs`
  has `symbolic_reference_writes_reflog_if_previous_value_is_set`, where a
  prepared symbolic ref update writes the symbolic lock file and records a
  reflog line using the object-valued `ExistingMustMatch` expectation.
- Upstream `gix-ref/src/store/file/transaction/commit.rs` maps the commit order:
  update reflog first, then publish the prepared reference lock.

Implemented behavior:

- `ReferenceStore::prepareLooseUpdateTransaction()` now carries the same
  object-valued reflog target used by direct updates when the prepared update
  writes a symbolic reference with an object `ExistingMustMatch` expectation.
- The prepared lock still publishes the symbolic `ref: ...` file, while the
  reflog records `0000... <peeled-object> <committer>\t<message>`.
- Existing object-update, deref, no-op, packed-lock, delete, and missing-committer
  behaviors remain covered by the same focused reference-store suite.

Verification:

- Red-first probe before the change returned `NULL` for
  `reflogContents('refs/heads/symbolic')` after the prepared symbolic update.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`
  passed `1 test files, 489 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  passed `39 test files, 5801 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php`
  exited `0`.

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  reference-store, prepared lock, reflog, namespace, and WordPress reference
  transaction example surfaces.

Non-overlap:

- This does not repeat accepted packed-lock collision, prepared object-update
  reflog append, delete reflog ordering, deref update/delete split, direct
  symbolic reflog accommodation, or packed-ref peeled transaction work. It is
  bounded to prepared symbolic update reflog parity while the lock still
  publishes a symbolic reference.
