# Reference Transaction Packed Shadow Reflog Parity - 2026-06-01

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260601T064545Z`

Base accepted HEAD: `0beac79ced31a7dd838adc7168578a431ce35af2`

## Upstream Source Truth

- Re-read pinned upstream `gix-ref/src/store/file/transaction/prepare.rs` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read pinned upstream `gix-ref/src/store/file/transaction/commit.rs`.
- Re-read pinned upstream
  `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs`.
- Mapped upstream
  `packed_refs_are_looked_up_when_checking_existing_values`: default prepared
  updates use packed refs for previous-value checks, hold `packed-refs.lock`,
  write a loose ref that shadows the packed value, append a reflog from the
  packed old id to the loose new id, and leave `packed-refs` unchanged.

## Native PHP Delta

- Added focused `ReferenceStoreTest` coverage for prepared default updates of
  packed-only refs, including packed-lock staging, loose lock publication,
  packed-file preservation, loose-over-packed lookup precedence, and reflog
  old/new object ids.
- Extended the WordPress reference-transaction fixture/example with a tenant
  review ref prepared as a loose overlay over a packed production baseline.

## Verification

- Before this slice, `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`
  passed `1 test files, 658 assertions, 0 failures`.
- After this slice, `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`
  passed `1 test files, 680 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 7875
  assertions, 0 failures`.

Full upstream Cargo workspace tests and the root PHP harness were not run for
this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP reference-store,
packed-refs parser, prepared-transaction, reflog, namespace, and WordPress
example surfaces; no shell-out, live provider, credential store, or external
Git process is required.

## Non-Overlap

This does not repeat direct loose-over-packed updates, prepared packed update
mode, prepared packed deletes, packed-lock collision handling, unchanged object
no-op handling, symbolic deref write-mode behavior, transport/protocol,
pathspec, URL/refspec, merge-base, object database, pack, or tree-merge slices.
It is bounded to the default prepared update behavior where packed refs provide
the previous value but the committed update remains loose and reflogged.
