# Reference Transaction Packed Delete Parity - 2026-06-01

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260601T032258Z`

Base accepted HEAD: `639880c48c54d40c3ed0188758af6aee8d8d2712`

## Upstream Source Truth

- Re-read pinned upstream `gix-ref/src/store/file/transaction/prepare.rs`.
- Re-read pinned upstream `gix-ref/src/store/file/transaction/commit.rs`.
- Re-read pinned upstream `gix-ref/tests/refs/file/transaction/prepare_and_commit/delete.rs`.
- Mapped upstream prepared delete behavior for:
  - `packed_refs_are_consulted_when_determining_previous_value_of_ref_to_be_deleted_and_are_deleted_from_packed_ref_file`
  - `a_loose_ref_with_old_value_check_and_outdated_packed_refs_value_deletes_both_refs`
  - `all_contained_references_deletes_the_packed_ref_file_too`

## Mapped Behavior

- Prepared delete transactions now remove matching packed references during the packed-ref commit phase.
- Packed-only deletes use packed refs for previous-value checks and remove the packed entry without creating a loose ref.
- Loose-over-packed deletes remove both the loose overlay and the stale packed entry.
- Deleting all packed entries removes `packed-refs` and cleans the prepared packed lock.

## Native Changes

- Added a packed-ref deletion plan to `PreparedReferenceTransaction`.
- `ReferenceStore::prepareLooseDeleteTransaction()` records packed names affected by `RefLog::AndReference` delete edits.
- `PreparedReferenceTransaction::commit()` now deletes reflogs, commits packed-ref deletions, then removes loose reference files and locks.
- Extended the WordPress reference transaction example with prepared packed review-ref pruning.

## Verification

- Red-first before implementation: `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php` failed because `refs/heads/main` still resolved from `packed-refs` after a prepared delete.
- `php -l lanes/gitoxide/src/PreparedReferenceTransaction.php`: no syntax errors.
- `php -l lanes/gitoxide/src/ReferenceStore.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php`: no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-reference-transaction.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php`: no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `1 test files, 615 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 7158 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php`: exited `0`.

Full upstream Cargo workspace tests were not run; this slice used targeted pinned upstream source reads and native PHP focused/full-lane evidence.

## Dependency Closure

No new support component is needed. The slice reuses native PHP reference-store, prepared-transaction, packed-refs, reflog, namespace, and WordPress reference transaction example surfaces; no shell-out, live provider, credential store, or external Git process is required.

## Non-Overlap

This does not repeat prepared delete reflog phase ordering, prepared delete write-mode behavior, prepared unchanged object no-op handling, symbolic clone reflog accommodation, dereferenced symbolic write-mode behavior, direct-referent HEAD-log behavior, packed-lock collision handling, direct packed-ref delete/update rewrites, packed-ref peeled transaction work, smart HTTP/send-pack/protocol, pathspec, URL/refspec, merge-base, object, pack, or tree-merge slices. It is bounded to prepared transaction packed-ref deletion parity.
