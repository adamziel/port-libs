# Reference Transaction Delete Write-Mode Reflog Parity - 2026-06-01

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260601T010602Z`

Base accepted HEAD: `e274bccd68de6d0be207ea53c6e2f938b9cd38dd`

## Upstream Source Truth

- Re-read pinned upstream `gix-ref/src/store/file/transaction/commit.rs`.
- Re-read pinned upstream `gix-ref/src/store/file/transaction/prepare.rs`.
- Re-read pinned upstream `gix-ref/tests/refs/file/transaction/prepare_and_commit/delete.rs`.
- Mapped upstream `store_write_mode_has_no_effect_and_reflogs_are_always_deleted`: prepared reflog-only deletes remove existing reflogs in both `WriteReflog::Normal` and `WriteReflog::Disable`.

## Mapped Behavior

- Prepared reflog-only deletes still stage and clean a lock file while preserving the reference file.
- Store-level disabled reflog write mode only suppresses writes; it does not protect existing reflogs from delete transactions.
- Non-dereferenced symbolic `HEAD` reflog deletion leaves the symbolic `HEAD` file and referent branch reflog untouched.
- The WordPress reference transaction smoke now models disabled write-mode audit cleanup for a tenant symbolic `HEAD`.

## Native Changes

- Added focused `ReferenceStoreTest` coverage for prepared reflog-only deletes under normal and disabled write modes.
- Extended `wordpress-reference-transaction.php` and its fixture with disabled write-mode symbolic `HEAD` audit cleanup.
- Updated `lane-status.json` with the focused/full-lane evidence and pending handoff state.

## Verification

- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php`: pass.
- `php -l lanes/gitoxide/examples/wordpress-reference-transaction.php`: pass.
- `php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php`: pass.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `1 test files, 574 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReflogTest.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `2 test files, 719 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 6619 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php`: exit `0`.

Full upstream Cargo workspace tests were not run; this slice used targeted pinned upstream source reads and native PHP focused/full-lane evidence.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP reference-store, prepared-transaction, reflog, namespace, and WordPress reference transaction example surfaces; no shell-out, live provider, credential store, or external Git process is required.

## Non-Overlap

This does not repeat prepared update write-mode behavior, prepared symbolic clone reflog accommodation, dereferenced symbolic update/delete splits, prepared unchanged object no-op handling, packed-lock collision handling, direct referent HEAD-log behavior, packed-ref peeled transaction work, smart HTTP/send-pack/protocol, pathspec, URL/refspec, merge-base, object, pack, or tree-merge slices. It is bounded to upstream delete semantics where reflog write mode has no effect on reflog deletion.
