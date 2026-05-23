# Gitoxide Packed-Ref Lock Slice - 2026-05-23T03:51:48Z

## Scope

- Lane: `lanes/gitoxide`
- Native slice: bounded `gix-ref` packed-reference transaction lock/failure atomicity.
- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Hard exclusion honored: no shell-backed Git commands are counted as native implementation progress; external merge-driver shell execution remains excluded.

## Upstream Evidence

- Re-inspected local upstream `gix-ref` sources:
  - `gix-ref/src/store/file/packed.rs`
  - `gix-ref/src/store/packed/transaction.rs`
  - `gix-ref/src/store/file/transaction/prepare.rs`
  - `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/collisions.rs`
- Exact upstream runner:
  - `cargo test --locked --offline --color never -p gix-ref --features gix-ref/sha1,gix-ref/sha256,gix-ref/parallel file::transaction::prepare_and_commit::create_or_update::collisions::packed_refs_lock_is_mandatory_for_multiple_ongoing_transactions_even_if_one_does_not_need_it -- --exact --nocapture`
  - Result: `1` passed, `0` failed, `144` filtered out.
- Broader existing upstream evidence retained:
  - `audits/gitoxide-rust-wide6-evidence-20260523T0324Z.md`: `gix-ref` package passed `162` tests, `0` failed.

## Native Decision

- `ReferenceStore::rewritePackedReferences()` now acquires `packed-refs.lock`, writes sorted packed contents to the lock file, commits by rename, and removes the lock after successful packed updates or all-packed deletions.
- Stale `packed-refs.lock` collisions fail with an upstream-shaped packed-lock message before packed contents or loose refs are changed.
- `ReferenceStore::prepareLooseUpdateTransaction()` now refuses an existing packed-ref lock before creating loose `.lock` files, matching the upstream guard that concurrent packed transactions must not be missed.
- The WordPress packed-reference transaction example now includes a concurrent deployment lock refusal that leaves packed refs and loose production refs unchanged.

## Verification

- `php -l` on touched PHP files: `0` syntax errors.
- Focused `ReferenceStoreTest.php`: `1` test file, `230` assertions, `0` failures.
- Gitoxide lane one-off runner over `lanes/gitoxide/tests/*Test.php`: `32` test files, `2574` assertions, `0` failures.
- Required root harness `php tools/run-tests.php`: `181` test files, `17549` assertions, `0` failures.

## Blockers

- No current Gitoxide PHP blocker for this slice.
- Full cargo workspace parity remains unrun because the workspace is large and feature-heavy; this slice used exact bounded `gix-ref` evidence.
- Full multi-ref commit non-atomic failure surfaces, packed-ref mmap/buffer invalidation races, SSH auth/channel integration, broader git-daemon runtime integration, sparse-index writing, broader commit writer parity, and full merge semantics remain future work.
