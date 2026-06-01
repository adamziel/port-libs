# Reference Transaction Delete Reflog Phase Parity - 2026-06-01

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260601T021331Z`

Base accepted HEAD: `3c98e2d72930fd8255ad75c07fd62902f4065530`

## Upstream Source Truth

- Re-read pinned upstream `gix-ref/src/store/file/transaction/commit.rs`.
- Re-read pinned upstream `gix-ref/src/store/file/transaction/prepare.rs`.
- Re-read pinned upstream `gix-ref/tests/refs/file/transaction/prepare_and_commit/delete.rs`.
- Mapped the commit ordering in `Transaction::commit_inner()`: update refs first, delete targeted reflogs for all delete edits, commit packed refs, then delete reference files and release locks.

## Mapped Behavior

- Prepared delete transactions now run all reflog deletions before deleting any loose reference file.
- If a later reflog deletion fails, earlier reflogs may already be pruned, but all reference files and delete locks remain in place.
- Existing single-delete reflog failure behavior is preserved: the reference file is not deleted and the prepared lock remains for manual recovery.
- The WordPress reference-transaction smoke now models tenant review pruning where a later corrupt reflog path blocks deletion without removing the review refs.

## Native Changes

- Split `PreparedReferenceTransaction` delete commit into reflog and reference phases.
- Kept update commits ahead of delete phases to match upstream's "updates first" transaction ordering.
- Added focused `ReferenceStoreTest` coverage for multi-delete reflog-phase failure ordering.
- Extended `wordpress-reference-transaction.php` and its fixture with the tenant review delete-failure smoke.
- Updated lane status and manifest evidence for the proposed mapped coverage move.

## Verification

- Red-first before implementation: one-off PHP probe reported `failed: Unable to delete prepared reflog: refs/heads/b` and `a_ref=missing`, proving the first reference was deleted before a later reflog failure.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `1 test files, 591 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 6816 assertions, 0 failures`.
- `php -l lanes/gitoxide/src/PreparedReferenceTransaction.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php`: no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-reference-transaction.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php`: no syntax errors.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php`: exited 0.
- `jq empty lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` and `jq empty lanes/gitoxide/lane-status.json`: exited 0.
- `git diff --check -- lanes/gitoxide`: exited 0.
- Full upstream Cargo workspace tests were not run; this slice used targeted pinned upstream source reads and native PHP focused/full-lane evidence.

## Dependency Closure

No new support component is needed. The slice reuses native PHP reference-store, prepared-transaction, reflog, namespace, and WordPress reference transaction example surfaces; no shell-out, live provider, credential store, or external Git process is required.

## Non-Overlap

This does not repeat prepared unchanged object no-op handling, symbolic clone reflog accommodation, dereferenced symbolic write-mode behavior, direct-referent HEAD-log behavior, prepared delete write-mode handling, packed-lock collision handling, packed-ref peeled transaction work, smart HTTP/send-pack/protocol, pathspec, URL/refspec, merge-base, object, pack, or tree-merge slices. It is bounded to the upstream delete commit ordering where all reflogs are handled before any reference file deletion.
