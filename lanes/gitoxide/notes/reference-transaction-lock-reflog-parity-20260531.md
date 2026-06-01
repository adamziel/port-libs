# Gitoxide Reference Transaction Lock/Reflog Parity

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260531T153101Z`

Base accepted HEAD: `a7ecc1c03f47b919bbd97dfd951b936133999f9f`

## Upstream Source Truth

- Read `gix-ref/src/store/file/transaction/prepare.rs`.
- Read `gix-ref/src/store/file/transaction/commit.rs`.
- Read `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs`.
- Read `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/collisions.rs`.
- Existing lane inventory records the exact upstream probes for:
  - `reference_with_must_not_exist_constraint_may_exist_already_if_the_new_value_matches_the_existing_one`
  - `collisions::non_conflicting_creation_without_packed_refs_work`

## Mapped Behavior

- Prepared object updates whose target already matches the existing object are no-op edits: no loose reference lock is acquired, no reflog is appended, and pre-existing lock sidecars are left untouched.
- Loose reference iteration skips `.lock` sidecars so an in-flight or stale prepared lock is not surfaced as an invalid reference name.
- A prepared transaction for a non-conflicting ref can commit its reference and reflog while another prepared lock remains open, matching the upstream collision test.

## Native Changes

- Added `PreparedReferenceTransaction::ACTION_NOOP` for prepared edits that should be reported but do not own a lock file.
- Extended `ReferenceStore::prepareLooseUpdateTransaction()` with optional previous-value constraints and no-op detection for unchanged object updates.
- Updated `LooseReferenceStore::prefixed()` to ignore `.lock` sidecars.
- Extended the WordPress reference transaction example with an idempotent tenant review ref update that preserves a held lock and avoids reflog noise.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `1 test files, 418 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReflogTest.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `2 test files, 563 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `39 test files, 4831 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php`: exit `0`.

Full upstream Cargo workspace tests were not run; this slice used targeted upstream source reads plus the existing bounded upstream runner evidence recorded in `lanes/gitoxide/notes/upstream-inventory.md`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP reference-store, loose-reference, prepared-transaction, reflog, and commit-signature helpers; no shell-out, live provider, credential store, or external Git process is required.

## Non-Overlap

This does not repeat accepted packed-ref lock collision, prepared commit publication, reflog message byte validation, deref update/delete splitting, sparse checkout, pathspec, URL/refspec, merge-base, or transport slices. It narrows the remaining prepared reference transaction lock/reflog parity gap around unchanged object updates and `.lock` sidecar visibility.

---

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260531T203827Z`

Base accepted HEAD: `91b42fe7029899440b4b46f38b3f903a76f3b322`

## Upstream Source Truth

- Re-read `gix-ref/src/store/file/transaction/prepare.rs`.
- Re-read `gix-ref/src/store/file/transaction/commit.rs`.
- Re-read `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs`.
- Existing lane inventory already records the exact upstream runner:
  `file::transaction::prepare_and_commit::create_or_update::symbolic_head_missing_referent_then_update_referent`
  passed 1/1 with 143 filtered out.

## Mapped Behavior

- Prepared dereferenced updates split symbolic parents into `RefLog::Only` edits while the leaf referent receives the actual reference write.
- Prepared symbolic parent locks are staged during prepare, but commit removes them after writing only the parent reflog, preserving the symbolic parent file.
- Parent reflog entries use the leaf referent's previous object id when available, matching upstream `leaf_referent_previous_oid` handling.
- Missing leaf referents are created through the prepared leaf lock while both parent and leaf reflogs record a null-old-id to new-object transition.

## Native Changes

- Extended `ReferenceStore::prepareLooseUpdateTransaction()` with an optional deref mode that stages parent reflog-only locks and leaf reference locks.
- Updated `PreparedReferenceTransaction::commitUpdate()` to support reflog-only update edits by appending the prepared reflog and removing the lock instead of publishing it as a reference file.
- Added focused PHP coverage for missing and existing leaf referent prepared-deref updates.
- Extended the WordPress reference transaction fixture/example with a prepared symbolic `HEAD` production publish that logs both `HEAD` and the production branch while keeping `HEAD` symbolic.

## Verification

- `php -l lanes/gitoxide/src/ReferenceStore.php`: pass.
- `php -l lanes/gitoxide/src/PreparedReferenceTransaction.php`: pass.
- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php`: pass.
- `php -l lanes/gitoxide/examples/wordpress-reference-transaction.php`: pass.
- `php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php`: pass.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `1 test files, 471 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReflogTest.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `2 test files, 616 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `39 test files, 5444 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php`: exit `0`.
- `jq empty lanes/gitoxide/lane-status.json`: pass.
- `git diff --check -- lanes/gitoxide`: pass.
- `rg -n "WordPress|wordpress|wp_|WP|Wp" lanes/gitoxide/src || true`: no matches.

Full upstream Cargo workspace tests were not run; this slice used targeted upstream source reads plus the existing bounded upstream runner evidence recorded in `lanes/gitoxide/notes/upstream-inventory.md`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP reference-store, loose-reference, prepared-transaction, reflog, namespace, and commit-signature helpers; no shell-out, live provider, credential store, or external Git process is required.

## Non-Overlap

This does not repeat accepted direct deref update/delete reporting, prepared unchanged object no-op handling, prepared delete/reflog-only handling, packed-ref lock collision handling, packed update-mode rewrites, reflog parser/append behavior, sparse checkout, pathspec, URL/refspec, merge-base, or transport slices. It adds the missing prepared transaction variant of upstream dereferenced symbolic update lock and reflog behavior.

---

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260531T224837Z`

Base accepted HEAD: `33a65237308053a0654b3629f3bffe8d77c73515`

## Upstream Source Truth

- Re-read pinned upstream `gix-ref/src/store/file/transaction/prepare.rs`.
- Re-read pinned upstream `gix-ref/src/store/file/transaction/commit.rs`.
- Re-read pinned upstream `gix-ref/src/store/file/loose/reflog.rs`.
- Re-read pinned upstream `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs`.
- Mapped the `symbolic_head_missing_referent_then_update_referent` write-mode loop, where prepared symbolic `HEAD` creation stays logless and the later dereferenced object update writes `HEAD` plus leaf reflogs in `Normal` and `Always`, but writes no reflogs in `Disable`.

## Mapped Behavior

- `ReferenceStore` now exposes native reflog write modes: `WRITE_REFLOG_NORMAL`, `WRITE_REFLOG_ALWAYS`, and `WRITE_REFLOG_DISABLE`.
- Direct and prepared reflog appends honor disabled mode by skipping reflog writes even when a committer, message, or force-create request is supplied.
- Always mode forces reflog creation for object-target reflogs when a commit signature is available, while unchanged object updates still avoid reflog noise.
- Prepared dereferenced symbolic updates pass the write mode through both the symbolic parent `RefLog::Only` edit and the leaf reference edit.

## Native Changes

- Added store-level reflog write-mode constants and constructor plumbing in `ReferenceStore`.
- Carried write-mode metadata into `PreparedReferenceTransaction` reflog records.
- Added focused `ReferenceStoreTest` coverage for prepared symbolic `HEAD` creation followed by a dereferenced object update under normal, always, and disabled write modes.
- Extended the WordPress reference transaction fixture/example with a quiet reflog-disabled prepared production publish.

## Verification

- Red-first before implementation: `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php` failed on missing `ReferenceStore::WRITE_REFLOG_NORMAL`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `1 test files, 529 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `39 test files, 6091 assertions, 0 failures`.
- `php -l` passed for changed PHP files.
- `jq empty` passed for `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` and `lanes/gitoxide/lane-status.json`.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php`: exit `0`.

Full upstream Cargo workspace tests were not run; this slice used targeted pinned upstream source reads and native PHP focused/full-lane evidence.

## Dependency Closure

No new support component is needed. The slice reuses native PHP reference-store, prepared-transaction, reflog, namespace, and WordPress reference transaction example surfaces; no shell-out, live provider, credential store, or external Git process is required.

## Non-Overlap

This does not repeat prepared unchanged object no-op handling, prepared symbolic ExistingMustMatch clone accommodation, dereferenced symbolic update/delete splits, prepared delete/reflog-only behavior, packed-lock collision handling, packed-ref peeled transaction work, sparse-checkout, pathspec, URL/refspec, merge-base, object, pack, or transport slices. It is bounded to store-level reflog write-mode parity for prepared dereferenced reference transactions.

---

Slice: `gitoxide-reference-transaction-lock-reflog-parity-20260531T235807Z`

Base accepted HEAD: `0e78c232d5f671d5140ddac2287b4ff3c85d5779`

## Upstream Source Truth

- Re-read pinned upstream `gix-ref/src/store/file/transaction/prepare.rs`.
- Re-read pinned upstream `gix-ref/src/store/file/transaction/commit.rs`.
- Re-read pinned upstream `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs`.
- Mapped upstream `write_reference_to_which_head_points_to_does_not_update_heads_reflog_even_though_it_should`: a prepared update to the object ref that symbolic `HEAD` points to writes only that referent's reflog and intentionally leaves `HEAD` reflog unchanged.

## Mapped Behavior

- Prepared direct referent updates stage and publish the referent lock file without creating any `HEAD.lock`.
- The referent branch reflog receives the object transition, including the existing object id inferred during prepare.
- The symbolic `HEAD` file remains symbolic and its existing reflog bytes remain unchanged, matching upstream gix-ref's documented current behavior.
- The WordPress reference transaction smoke now demonstrates a production branch publish that updates only the production branch reflog while preserving HEAD audit history.

## Native Changes

- Added focused `ReferenceStoreTest` coverage for prepared direct-referent updates under a symbolic `HEAD`.
- Extended `wordpress-reference-transaction.php` and its fixture with the WordPress deployment smoke for direct production referent publishing.
- Updated `lane-status.json` with the focused/full-lane evidence and current blocker.

## Verification

- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php`: pass.
- `php -l lanes/gitoxide/examples/wordpress-reference-transaction.php`: pass.
- `php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php`: pass.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `1 test files, 547 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php`: exit `0`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 6413 assertions, 0 failures`.

Full upstream Cargo workspace tests were not run; this slice used targeted pinned upstream source reads and native PHP focused/full-lane evidence.

## Dependency Closure

No new support component is needed. The slice reuses native PHP reference-store, loose-reference, prepared-transaction, reflog, namespace, and WordPress reference transaction example surfaces; no shell-out, live provider, credential store, or external Git process is required.

## Non-Overlap

This does not repeat prepared unchanged object no-op handling, symbolic clone reflog accommodation, dereferenced symbolic write-mode behavior, prepared delete/reflog-only behavior, packed-lock collision handling, packed-ref peeled transaction work, smart HTTP/send-pack/protocol, pathspec, URL/refspec, merge-base, object, pack, or tree-merge slices. It is bounded to the upstream direct-referent update behavior where HEAD's reflog intentionally does not follow a branch update.
