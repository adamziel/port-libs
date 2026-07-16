# Reference Transaction Packed Update Parity - 2026-06-01

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260601T043234Z`

Base accepted HEAD: `a9f4989344098e67e1082ce806a8270acd26ace6`

## Upstream Source Truth

- Re-read pinned upstream `gix-ref/src/store/file/transaction/prepare.rs` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read pinned upstream `gix-ref/src/store/file/transaction/commit.rs`.
- Re-read pinned upstream
  `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs`.
- Mapped upstream prepared packed update behavior from
  `packed_refs_creation_with_packed_refs_mode_prune_removes_original_loose_refs`:
  object updates in packed-ref mode stage no loose leaf locks, hold a
  packed-refs transaction, write reflogs before committing packed refs, and
  prune loose object refs only after the packed-refs commit in remove-loose
  mode.

## Native PHP Delta

- `ReferenceStore::prepareLooseUpdateTransaction()` accepts packed-ref update
  mode and optional object database parameters, mirroring direct update mode.
- Prepared object updates in packed-ref modes now create packed-reference
  update plans, force `packed-refs.lock` creation even when `packed-refs` is
  absent, and skip loose leaf locks for direct-to-packed object writes.
- `PreparedReferenceTransaction` now has a packed-update action and a packed
  update commit phase that appends reflogs first, rewrites `packed-refs`, then
  prunes loose source refs for remove-loose mode.
- The WordPress reference transaction example now covers a namespaced prepared
  packed update that resolves from `packed-refs`, records a reflog, and removes
  the loose review source after commit.

## Verification

- `php -l lanes/gitoxide/src/PreparedReferenceTransaction.php`: no syntax errors.
- `php -l lanes/gitoxide/src/ReferenceStore.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php`: no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-reference-transaction.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php`: no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`:
  `1 test files, 642 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php`: exited `0`.
- `jq empty lanes/gitoxide/lane-status.json`: pass.
- `git diff --check -- lanes/gitoxide`: pass.

Full upstream Cargo workspace tests and the root PHP harness were not run for
this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP reference
store, packed-refs parser/writer, loose object store, object database peeling,
prepared transaction, reflog, namespace, and WordPress reference transaction
example surfaces. It does not shell out to Git, access live services, or read
credential-bearing inputs.

## Non-Overlap

This does not repeat prepared packed-ref delete parity, direct packed-ref
update/delete rewrites, packed-lock collision handling, unchanged object no-op
handling, dereferenced symbolic update/delete splits, reflog write-mode
behavior, tree/pathspec, URL/refspec, merge-base, object database, pack, or
transport slices. It is bounded to prepared packed object update transaction
ordering, packed lock ownership, reflog emission, peeled packed tag sidecars,
and loose-source pruning in remove-loose mode.
