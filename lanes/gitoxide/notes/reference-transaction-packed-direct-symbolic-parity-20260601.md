# Reference Transaction Packed Direct-Symbolic Parity

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260601T130538Z`

Base accepted HEAD: `96d5510e066bd7782f01bbae271bcdda6b59ec3e`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/collisions.rs`
  - `conflicting_creation_into_packed_refs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-ref/src/store/file/transaction/prepare.rs`
  - direct object updates in packed update mode are staged into packed refs without loose leaf locks
  - symbolic updates remain loose even in packed update mode
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-ref/src/store/file/transaction/commit.rs`
  - object reflogs are written before packed refs are committed and loose sources are pruned

## Native Parity Added

- Added `ReferenceStoreTest` coverage for a mixed prepared transaction containing:
  - two object refs created directly into `packed-refs`
  - one symbolic ref created as a loose lockfile
  - object reflogs for packed object refs
  - no symbolic reflog entry for the symbolic direct creation
  - same-target packed refreshes that avoid held loose `.lock` files and do not add reflog noise
  - delete cleanup that removes packed entries, loose symbolic ref, and reflogs
- Extended the WordPress reference transaction smoke to publish packed content/assets review refs while keeping a symbolic review pointer loose.

## Verification

- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php`
  - `No syntax errors detected in lanes/gitoxide/tests/ReferenceStoreTest.php`
- `php -l lanes/gitoxide/examples/wordpress-reference-transaction.php`
  - `No syntax errors detected in lanes/gitoxide/examples/wordpress-reference-transaction.php`
- `php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php`
  - `No syntax errors detected in lanes/gitoxide/fixtures/wordpress-reference-transaction.php`
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`
  - `1 test files, 831 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php`
  - exit `0`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 9461 assertions, 0 failures`
- `jq empty lanes/gitoxide/lane-status.json lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`
  - exit `0`
- `git diff --check -- lanes/gitoxide`
  - exit `0`

## Dependency Closure

No new support component is required. This slice reuses existing native PHP `ReferenceStore`, `PreparedReferenceTransaction`, `PackedReferences`, reflog, and filesystem lock primitives.

## Non-Overlap

This does not repeat the earlier prepared packed update/prune slice, packed-lock/ref-log-only slice, symbolic ExistingMustMatch reflog accommodation, dereferenced symbolic split behavior, or protocol/transport batches. The newly mapped upstream behavior is the combined direct-to-packed object plus loose-symbolic collision scenario from `conflicting_creation_into_packed_refs`.
