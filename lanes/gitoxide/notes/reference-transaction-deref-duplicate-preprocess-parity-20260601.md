Gitoxide reference transaction deref duplicate preprocessing parity
===================================================================

Micro-slice: `gitoxide-reference-transaction-lock-reflog-parity-20260601T104059Z`
Base accepted HEAD: `4d71d2c69326a2d3cad8d2b8fb0de26b66be4fbb`

Source truth
------------

- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide`
- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Files:
  - `gix-ref/src/transaction/ext.rs`
  - `gix-ref/src/store/file/transaction/prepare.rs`
  - `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs`
  - `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/collisions.rs`

Mapped behavior
---------------

`gix-ref` preprocesses reference transaction edits before lock planning:
`pre_process()` expands dereferenced symbolic ref edits, then verifies that one
reference name has only one edit. `prepare_inner()` runs that preprocessing
before packed-ref transaction preparation can take or fail on `packed-refs.lock`.

This slice ports that ordering for prepared loose update transactions:

- dereferenced update splits are computed before packed-ref lock planning;
- duplicate physical edit names from symbolic parents and leaf updates fail
  with `A reference named "..." has multiple prepared edits`;
- a stale or concurrently held `packed-refs.lock` no longer masks that
  duplicate-edit error;
- no loose `.lock` files, reference writes, or reflog writes are created when
  preprocessing rejects the transaction.

Red-first evidence
------------------

Before the change, the same duplicate deref edit probe failed with the packed
lock error:

```text
RuntimeException: The lock for the packed-ref file could not be obtained
head_lock=no main_lock=no packed_lock=held by compaction
```

That showed packed-ref lock planning ran before duplicate edit preprocessing.

Native delta
------------

- `ReferenceStore::prepareLooseUpdateTransaction()` now builds prepared update
  records up front, validates duplicate physical names after deref splitting,
  then uses the prepared records for packed-ref lock planning and loose lock
  staging.
- `ReferenceStoreTest.php` adds direct coverage for `HEAD` and
  `refs/heads/main` targeting the same dereferenced leaf while
  `packed-refs.lock` is already held.
- `examples/wordpress-reference-transaction.php` and its fixture now include a
  multisite deployment smoke proving duplicate dereferenced prepared updates are
  rejected before waiting on packed-ref locks.

Verification
------------

```text
php -l lanes/gitoxide/src/ReferenceStore.php
No syntax errors detected in lanes/gitoxide/src/ReferenceStore.php

php -l lanes/gitoxide/tests/ReferenceStoreTest.php
No syntax errors detected in lanes/gitoxide/tests/ReferenceStoreTest.php

php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php
No syntax errors detected in lanes/gitoxide/fixtures/wordpress-reference-transaction.php

php -l lanes/gitoxide/examples/wordpress-reference-transaction.php
No syntax errors detected in lanes/gitoxide/examples/wordpress-reference-transaction.php

php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php
1 test files, 731 assertions, 0 failures

php lanes/gitoxide/examples/wordpress-reference-transaction.php
exit 0

php tools/run-tests.php lanes/gitoxide/tests
40 test files, 8816 assertions, 0 failures

jq empty lanes/gitoxide/lane-status.json
exit 0

git diff --check -- lanes/gitoxide
exit 0
```

Dependency closure
------------------

No new support component is needed. The slice reuses the existing native PHP
reference store, loose ref, packed-ref lock, reflog, and WordPress example
fixtures.

Non-overlap and follow-up
-------------------------

This does not repeat accepted packed update/delete rewrites, pseudo-ref packed
lock handling, packed shadow reflog behavior, broken deref delete handling,
delete write-mode reflog parity, or Windows device ref guards. A useful next
reference-transaction slice should target a different upstream boundary, such
as packed-ref commit conflict reporting, symbolic delete split phase ordering,
or reflog iterator edge parity.
