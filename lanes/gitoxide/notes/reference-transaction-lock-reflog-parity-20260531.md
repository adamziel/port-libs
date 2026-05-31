# Gitoxide Reference Transaction Lock/Reflog Parity

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260531T153101Z`

Base accepted HEAD: `a7ecc1c03f47b919bbd97dfd951b936133999f9f`

## Upstream Source Truth

- Read `gix-ref/src/store/file/transaction/prepare.rs`.
- Read `gix-ref/src/store/file/transaction/commit.rs`.
- Read `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs`.
- Read `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/collisions.rs`.
- Existing lane inventory records the exact upstream probes for:
  - `reference_with_must_not_exist_constraint_may_exist_already_if_the_new_value_matches_the_existing_one`
  - `collisions::non_conflicting_creation_without_packed_refs_work`

## Mapped Behavior

- Prepared object updates whose target already matches the existing object are no-op edits: no loose reference lock is acquired, no reflog is appended, and pre-existing lock sidecars are left untouched.
- Loose reference iteration skips `.lock` sidecars so an in-flight or stale prepared lock is not surfaced as an invalid reference name.
- A prepared transaction for a non-conflicting ref can commit its reference and reflog while another prepared lock remains open, matching the upstream collision test.

## Native Changes

- Added `PreparedReferenceTransaction::ACTION_NOOP` for prepared edits that should be reported but do not own a lock file.
- Extended `ReferenceStore::prepareLooseUpdateTransaction()` with optional previous-value constraints and no-op detection for unchanged object updates.
- Updated `LooseReferenceStore::prefixed()` to ignore `.lock` sidecars.
- Extended the WordPress reference transaction example with an idempotent tenant review ref update that preserves a held lock and avoids reflog noise.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `1 test files, 418 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReflogTest.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `2 test files, 563 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `39 test files, 4831 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php`: exit `0`.

Full upstream Cargo workspace tests were not run; this slice used targeted upstream source reads plus the existing bounded upstream runner evidence recorded in `lanes/gitoxide/notes/upstream-inventory.md`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP reference-store, loose-reference, prepared-transaction, reflog, and commit-signature helpers; no shell-out, live provider, credential store, or external Git process is required.

## Non-Overlap

This does not repeat accepted packed-ref lock collision, prepared commit publication, reflog message byte validation, deref update/delete splitting, sparse checkout, pathspec, URL/refspec, merge-base, or transport slices. It narrows the remaining prepared reference transaction lock/reflog parity gap around unchanged object updates and `.lock` sidecar visibility.
