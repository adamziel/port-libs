# Reference Transaction Pseudo-Ref Packed Lock Parity - 2026-06-01

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260601T054045Z`

Base accepted HEAD: `663e16b4022673e2529b925ce20b45f0a578189e`

## Upstream Source Truth

- Re-read pinned upstream `gix-ref/src/store/file/transaction/prepare.rs`.
- Re-read pinned upstream `gix-ref/src/store/file/transaction/commit.rs`.
- Re-read pinned upstream
  `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs`.
- Re-read pinned upstream
  `gix-ref/tests/refs/file/transaction/prepare_and_commit/delete.rs`.
- Mapped upstream `possibly_adjust_name_for_prefixes()` behavior: pseudo refs,
  main pseudo refs, linked pseudo refs, bisect refs, rewritten refs, and
  worktree-private refs are excluded from packed-ref transactions even when the
  caller requests packed object updates or packed deletions.

## Mapped Behavior

- Prepared packed object updates to `HEAD` stay loose, stage `HEAD.lock`, write a
  `HEAD` reflog, and do not acquire or require `packed-refs.lock`.
- A held `packed-refs.lock` from another packed-ref transaction is preserved
  when the prepared update only touches an un-packable pseudo ref.
- The prepared pseudo-ref update does not create `packed-refs` and does not
  route the pseudo ref through the packed-ref rewrite phase.
- The same packability filter is shared by direct update/delete and prepared
  update/delete paths so packed transactions use adjusted names only for refs
  that upstream considers packable.

## Native PHP Delta

- Added `ReferenceStore::packedTransactionPhysicalName()` and namespace-aware
  helpers to mirror upstream packed-transaction eligibility.
- Updated prepared packed update/delete planning to acquire the packed refs lock
  only when at least one packable ref participates.
- Updated direct packed update/delete paths to skip pseudo/worktree-private refs
  and to rewrite adjusted packed names for packable namespaced refs.
- Added focused `ReferenceStoreTest` coverage for prepared packed update mode
  leaving `HEAD` loose while a stale packed refs lock exists.
- Extended the WordPress reference transaction fixture/example with a detached
  tenant `HEAD` preview that remains loose during packed-ref compaction.

## Verification

- Red-first before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php` failed
  with `1 test files, 642 assertions, 1 failures` because `HEAD` tried to
  acquire the held `packed-refs.lock`.
- `php -l lanes/gitoxide/src/ReferenceStore.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php`: no
  syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-reference-transaction.php`: no
  syntax errors.
- `jq empty lanes/gitoxide/lane-status.json`: pass.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`:
  `1 test files, 658 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php`: exited
  `0`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 7618
  assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide`: pass.

Full upstream Cargo workspace tests and the root PHP harness were not run for
this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP reference-store,
prepared transaction, packed-refs, reflog, namespace, and WordPress reference
transaction example surfaces. It does not shell out to Git, access live
services, or read credential-bearing inputs.

## Non-Overlap

This does not repeat prepared packed-ref update/delete parity, direct
packed-ref update/delete rewrites, prepared delete reflog phase ordering,
prepared delete write-mode behavior, dereferenced symbolic update/delete
splits, direct-referent HEAD-log behavior, packed-lock collision handling,
packed-ref peeled transaction work, tree/pathspec, URL/refspec, merge-base,
object database, pack, or transport slices. It is bounded to upstream
pseudo/worktree-private ref exclusion from packed reference transaction lock and
reflog behavior.
