# Reference Transaction Nested Rename Lock/Reflog Parity

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260601T181230Z`

Base accepted HEAD: `768ad0b1513491461c94fabf9febd72f56baba34`

## Upstream Source Truth

Upstream manifest commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.

Targeted files:

- `gix-ref/src/store/file/transaction/prepare.rs`
- `gix-ref/src/store/file/transaction/commit.rs`
- `gix-ref/src/transaction/mod.rs`
- `gix-ref/tests/refs/file/transaction/prepare_and_commit/delete.rs`

Mapped upstream behavior: `rename_a_to_a_slash_b_in_one_transaction()`.

The upstream test proves a same-transaction delete of `refs/heads/old` and
create of `refs/heads/old/new` fails during prepare because `old` is still a
loose ref file while the nested lock directory is needed. The failed prepare
must roll back staged delete locks and avoid reflog side effects. A sequential
delete commit followed by a create commit succeeds and writes only the new
reference reflog.

## Native Port Delta

- Added `ReferenceStore::prepareLooseRenameTransaction()` for bounded loose
  reference rename preparation.
- Prepared delete locks are staged before create locks, matching upstream lock
  ordering and preserving the file/directory collision.
- Failed prepare rolls back any staged locks before returning the lock-directory
  error.
- The sequential delete then create path removes the old loose ref and reflog,
  creates the nested loose ref, and writes the new reflog.
- The WordPress reference-transaction fixture/example now covers the deployment
  branch-review case.

## Verification

- Baseline before edit: `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`
  passed `1 test files, 869 assertions, 0 failures`.
- Changed PHP lint:
  - `php -l lanes/gitoxide/src/ReferenceStore.php`
  - `php -l lanes/gitoxide/tests/ReferenceStoreTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-reference-transaction.php`
  - `php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php`
- Focused test after edit: `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`
  passed `1 test files, 894 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-reference-transaction.php`
  exited `0`.
- JSON validation: `jq empty lanes/gitoxide/lane-status.json lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`
  exited `0`.
- Diff check: `git diff --check -- lanes/gitoxide` passed.

Focused assertion delta: `+25`.

Mapped coverage delta: `1809 -> 1810`, one conservative gix-ref transaction
row.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The implementation reuses the existing
loose reference store, prepared transaction, reflog, and packed-refs lock
helpers.

## Non-Overlap

This slice does not repeat accepted packed-shadow reference transaction parity,
reflog write-mode parity, prepared reflog-only delete behavior, packed-ref lock
collision handling, or no-op prepared transaction semantics. It is limited to
the upstream same-transaction nested loose rename lock/reflog collision.
