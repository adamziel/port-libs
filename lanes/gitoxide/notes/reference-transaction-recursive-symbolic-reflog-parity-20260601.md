# Reference Transaction Recursive Symbolic Reflog Parity - 2026-06-01

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260601T114852Z`

Base accepted HEAD: `67d4ed76975ee89824a8db4b5cecf2d98e81eb14`

## Upstream Source Truth

- Re-read pinned upstream `gix-ref/src/store/file/transaction/prepare.rs` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Re-read pinned upstream `gix-ref/src/store/file/transaction/commit.rs`.
- Re-read pinned upstream `gix-ref/tests/refs/transaction.rs`.
- Mapped upstream
  `symbolic_refs_are_split_into_referents_handling_the_reflog_and_previous_values_recursively`:
  recursive symbolic deref updates produce log-only edits for each symbolic
  parent and one reference-writing leaf edit; recursive symbolic deref deletes
  remove each reflog first while preserving symbolic parents and pruning only
  the leaf reference when the requested leaf mode writes the reference.

## Native PHP Delta

- Added focused `ReferenceStoreTest` coverage for a three-ref symbolic chain
  (`HEAD -> refs/heads/review/current -> refs/heads/review/published`) across
  prepared deref update and delete.
- Verified update lock staging, lock cleanup, edit ordering, reflog modes,
  `updatesReference` flags, previous/new target values, parent symbolic file
  preservation, leaf publication, and parent/leaf reflog fanout from the leaf
  object id transition.
- Verified delete lock staging, log-only parent edits, leaf reference pruning,
  parent symbolic preservation, and removal of parent plus leaf reflogs before
  reference pruning.
- Extended the WordPress reference-transaction fixture/example with a recursive
  tenant HEAD publish/prune path that exercises the same multi-hop lock and
  reflog behavior without shelling out to Git.

## Verification

- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php` passed with no syntax
  errors.
- `php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php` passed
  with no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-reference-transaction.php` passed
  with no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php` passed
  `1 test files, 784 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php` exited
  with status 0.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 9154
  assertions, 0 failures`.

Full upstream Cargo workspace tests and the root PHP harness were not run for
this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP loose refs,
prepared reference transactions, reflogs, namespace handling, and the existing
WordPress example fixture; no live service, external Git binary, credential
store, or provider auth state is required.

## Non-Overlap

This does not repeat the accepted single-hop deref reflog, symbolic write-mode,
direct referent HEAD-log, deref duplicate-preprocess, packed update/delete,
packed shadow, pseudo-ref, broken deref delete, reflog delete phase, or Windows
device ref guard slices. It is bounded to recursive symbolic deref split parity
and the resulting parent reflog fanout/delete behavior.
