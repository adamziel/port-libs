# Reference Transaction Symbolic No-op Lock/Reflog Parity

Micro-slice: `gitoxide-reference-transaction-lock-reflog-parity-20260601T153303Z`

Base accepted HEAD: `58f1b15e81ee03d64915f36a0a94fc3dd31fae09`

## Upstream Source Truth

- `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs`
  - `reference_with_must_not_exist_constraint_may_exist_already_if_the_new_value_matches_the_existing_one`
- `gix-ref/src/store/file/transaction/prepare.rs`
- `gix-ref/src/transaction/ext.rs`
- `gix-ref/src/transaction/mod.rs`
- `gix-ref/src/store/file/transaction/prepare_and_commit/collisions.rs`

The upstream behavior treats prepared same-target updates as no-ops even when
the edit carries a `MustNotExist`-style previous-value constraint. For symbolic
`HEAD` already pointing at the requested target, the transaction returns the
edit outcome but does not acquire a ref lock and does not append a reflog entry.
The collision tests also document that no locks are obtained for unchanged refs.

## Native Behavior

`ReferenceStore::prepareLooseUpdateTransaction()` now uses the existing no-op
fast path for any same-target reference, not only object-id references. The
packed-ref object write path still bypasses this fast path when an object update
must be materialized into `packed-refs`.

The focused test covers a symbolic `HEAD` whose `.lock` file is already held by
another checkout. Updating `HEAD` to the same symbolic target with
`PREVIOUS_MUST_NOT_EXIST`, a committer, a reflog message, and forced reflog
mode now succeeds as an unchanged edit while preserving the held lock and
leaving the reflog absent.

The WordPress reference transaction smoke now includes an idempotent namespaced
tenant `HEAD` update that preserves a held lock and avoids reflog noise.

## Red-first Evidence

Before the implementation change, the new focused assertion failed:

```text
php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php
FAIL prepared reference transaction skips locks and reflogs for unchanged symbolic updates like upstream
A lock could not be obtained for reference "HEAD"
1 test files, 850 assertions, 1 failures
```

## Verification

```text
php -l lanes/gitoxide/src/ReferenceStore.php
No syntax errors detected in lanes/gitoxide/src/ReferenceStore.php

php -l lanes/gitoxide/tests/ReferenceStoreTest.php
No syntax errors detected in lanes/gitoxide/tests/ReferenceStoreTest.php

php -l lanes/gitoxide/examples/wordpress-reference-transaction.php
No syntax errors detected in lanes/gitoxide/examples/wordpress-reference-transaction.php

php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php
No syntax errors detected in lanes/gitoxide/fixtures/wordpress-reference-transaction.php

jq empty lanes/gitoxide/lane-status.json lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json
exit 0

php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php
1 test files, 869 assertions, 0 failures

php lanes/gitoxide/examples/wordpress-reference-transaction.php
exit 0

git diff --check -- lanes/gitoxide
exit 0
```

## Status Delta

- `phpPass`: `9974 -> 9993` from the focused ReferenceStore assertion delta.
- `benchmarkDenominator.mapped`: unchanged at `1801 / 2886`; this deepens an
  already represented `gix-ref` reference transaction no-op cluster rather than
  adding a new denominator unit.
- Upstream Cargo workspace: not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
reference store, prepared transaction staging, loose reference, lock, and reflog
components.

## Non-overlap

This does not repeat existing accepted coverage for object-id no-op updates,
packed-ref no-op reflog behavior, direct referent updates, dereferenced
symbolic updates, chained-symbolic `ExistingMustMatch`, reflog parser behavior,
transport/protocol behavior, pathspec/attributes behavior, merge-base behavior,
pack/index behavior, or partial-clone promisor hydration.
