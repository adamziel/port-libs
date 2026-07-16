# Gitoxide Reference Transaction Held-Lock Precedence Parity

Date: 2026-06-02 00:17 UTC
Base accepted HEAD: ce8cd6d1ec6823f7aed57d156dd36b048ab6f47a
Micro-slice: gitoxide-reference-transaction-lock-reflog-parity-20260602T000622Z

## Source Truth

Upstream gitoxide source at `/home/claude/port-libs/.upstream-cache/gitoxide`:

- `gix-ref/src/store/file/transaction/prepare.rs`
  - `lock_ref_and_apply_change()` acquires the loose update lock before applying previous-value checks.
  - Delete preparation acquires the loose marker lock before applying previous-value checks.
- `gix-ref/src/store/file/transaction/commit.rs`
  - Reflog writes remain intentionally before reference publication/deletion, so this slice only changes prepare-time lock/error precedence.

## Ported Behavior

`ReferenceStore::prepareLooseUpdateTransaction()` now checks effective loose update lock availability before stale previous-value validation, while preserving the accepted upstream-like no-op path that skips held locks and reflogs.

`ReferenceStore::prepareLooseDeleteTransaction()` now builds the delete edit set, rejects duplicate prepared edits, then checks the corresponding loose `.lock` files before stale previous-value validation. Held locks therefore report `A lock could not be obtained...` before `Reference is out of date...`, matching upstream acquisition order and leaving refs/reflogs unchanged.

## Focused Coverage

Added direct PHP coverage:

- prepared update with existing `refs/heads/main.lock`, stale expected object, and a pre-existing reflog.
- prepared delete with existing `refs/heads/main.lock`, stale expected object, and a pre-existing reflog.
- both cases assert lock error precedence, lock preservation, ref preservation, and reflog preservation.

Updated the WordPress reference transaction smoke to cover a namespaced tenant review ref with a held prepared lock and stale expected target. The example verifies the held lock, tenant ref, and audit reflog are preserved.

## Verification

- `php -l lanes/gitoxide/src/ReferenceStore.php` -> no syntax errors.
- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php` -> no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-reference-transaction.php` -> no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php` -> no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php` -> 1 file, 909 assertions, 0 failures.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php` -> exit 0.
- `php tools/run-tests.php lanes/gitoxide/tests` -> 41 files, 11073 assertions, 0 failures.
- `git diff --check -- lanes/gitoxide` -> passed.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP loose-reference store, prepared transaction, reflog, packed-ref lock, and WordPress fixture/example components.

## Non-Overlap

This is not Cargo workspace runner evidence, URL/refspec behavior, receive-pack/send-pack transport behavior, object/tree integrity, packed-ref update/delete phase parity, deref reflog split parity, no-op prepared transaction parity, packed lock acquisition parity, Windows device ref protection, or nested rename rollback parity. It specifically covers the remaining prepare-time loose-lock vs stale-expected-value ordering for effective loose update/delete transactions.
