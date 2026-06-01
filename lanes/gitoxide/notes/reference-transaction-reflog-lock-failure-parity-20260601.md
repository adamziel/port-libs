Reference transaction reflog-before-lock-failure parity slice prepared on 2026-06-01

- Worker slice: `gitoxide-reference-transaction-lock-reflog-parity-20260601T141724Z`
  on accepted base `4ffdaaa5b255e04219f8cfab7cf9e3d1ed08d99c`.
- Source truth: upstream Gitoxide
  `gix-ref/src/store/file/transaction/commit.rs` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`. `Transaction::commit()`
  writes update reflogs before committing prepared reference locks and does
  not roll back partial multi-file failures.
- Native PHP delta: focused `ReferenceStoreTest.php` coverage now proves a
  later prepared lock publication failure keeps the failed ref unpublished,
  preserves its lock and directory blocker, and still leaves both the earlier
  successful update and the later failed update with reflog audit entries.
  The WordPress reference-transaction fixture/example now models the same
  staged tenant review publish failure without invoking `git update-ref`.
- Verification: baseline focused `ReferenceStoreTest.php` passed `1 test
  files, 831 assertions, 0 failures`; after the slice it passed `1 test files,
  850 assertions, 0 failures`. `php lanes/gitoxide/examples/wordpress-reference-transaction.php`
  exited 0. Full Gitoxide lane, root harness, and upstream Cargo workspace
  were not executed in this isolated micro-slice.
- Expected movement: `phpPass` `9707 -> 9726`; conservative mapped coverage
  `1799 / 2886 -> 1800 / 2886`.
- Dependency closure: no new support component is needed. This reuses the
  existing native PHP reference store, prepared transaction, and reflog
  writer components; activation evidence is the focused PHP reference-store
  test and WordPress reference transaction smoke.
