# Reflog Append/Parse Parity, 2026-05-31

Slice: `gitoxide-reflog-append-parse-parity-20260531T083928Z`

Base accepted HEAD: `5055f9a476bd1f5f1243d68d1c463ad10b6d75ee`

## Upstream Source Truth

- Read `gix-ref/src/store/file/loose/reflog.rs`.
- Read `gix-ref/src/store/file/log/line.rs`.
- Read `gix-ref/src/store/file/log/iter.rs`.
- Read `gix-ref/tests/refs/file/store/reflog.rs`.
- Read `gix-ref/src/store/file/loose/reflog/create_or_update/tests.rs`.
- Ran focused upstream parser check:
  `cargo test -p gix-ref --lib store_impl::file::log::line::decode::test_decode --features sha1,sha256 -- --nocapture`
  passed `5` tests, `0` failed, `12` filtered.

Mapped behavior:

- Reflog line parser consumes only the first line, splits the optional message at the first tab, accepts empty messages with or without a tab, rejects malformed object/signature heads, and supports SHA-1/SHA-256 object ids.
- Forward reflog iteration returns oldest-to-newest parsed entries; reverse iteration returns newest-to-oldest parsed entries.
- Missing reflogs and directory paths return `null`, matching gix-ref's `Ok(None)` boundary.
- Append formatting uses old object id, new object id, trimmed committer signature, optional tab message, and no tab for empty messages in the create-or-append path.
- Auto-created logs remain bounded to `HEAD`, heads, remotes, notes, and worktree refs; forced creation can write tag logs.

## Native Changes

- Added `ReflogEntry` for parse, parse-all, reverse parse, append-line formatting, and storage serialization.
- Added `ReferenceStore::reflogEntries()` and `ReferenceStore::reflogEntriesReverse()`.
- Reused `ReflogEntry::appendLine()` from `ReferenceStore::appendPhysicalReflog()`.
- Added `wordpress-reflog-audit.php` fixture/example for native deployment audit parsing without invoking `git reflog`.

## Verification

- `php -l lanes/gitoxide/src/ReflogEntry.php`: pass.
- `php -l lanes/gitoxide/src/ReferenceStore.php`: pass.
- `php -l lanes/gitoxide/tests/ReflogTest.php`: pass.
- `php -l lanes/gitoxide/examples/wordpress-reflog-audit.php`: pass.
- `php -l lanes/gitoxide/fixtures/wordpress-reflog-audit.php`: pass.
- `php tools/run-tests.php lanes/gitoxide/tests/ReflogTest.php`: `1 test files, 56 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `1 test files, 353 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `33 test files, 3045 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reflog-audit.php`: exit `0`.
- `git diff --check -- lanes/gitoxide`: pass.

## Dependency Closure

No new support component is needed. The slice reuses existing native reference target, signature, and reference store code; no shell-out, live provider, credential, or external Git process is required.

## Non-Overlap

This does not repeat accepted prepared-reference reflog transaction creation/deletion, packed-ref peeled transaction rewrites, protocol v2 fetch sideband parsing, send-pack report-status-v2 parsing, or transport redirect/cookie behavior. It adds the standalone reflog entry parser and parsed store iteration surface that was still missing.

## Follow-up: Iterator Diagnostics And Directory Recovery

Slice: `gitoxide-reflog-append-parse-parity-20260531T091202Z`

Base accepted HEAD: `c4826bfb8a7874ec9af5044a69ea78310604752e`

Additional upstream source truth:

- Read `gix-ref/src/store/file/log/iter.rs`.
- Read `gix-ref/tests/refs/file/log.rs`.
- Read `gix-ref/src/store/file/loose/reflog.rs`.
- Read `gix-ref/src/store/file/loose/reflog/create_or_update/tests.rs`.
- Ran exact upstream checks:
  `timeout 180 cargo test -p gix-ref --test refs file::log::iter::forward::a_single_failure_does_not_abort_iteration --features sha1,sha256 -- --exact --nocapture`
  passed `1` test, `0` failed, `143` filtered.
  `timeout 180 cargo test -p gix-ref --lib store_impl::file::loose::reflog::create_or_update::tests::missing_reflog_creates_it_even_if_similarly_named_empty_dir_exists_and_append_log_lines --features sha1,sha256 -- --exact --nocapture`
  passed `1` test, `0` failed, `16` filtered.

Mapped behavior:

- Forward and reverse reflog iterators can surface per-line decode errors while preserving later valid entries, matching upstream's iterator-level result behavior.
- Direct reflog append recovers same-name empty directory blockers for auto-created branch logs and refuses non-empty blockers.
- The WordPress reflog audit example now reports corrupt-line diagnostics while still reading valid deployment audit entries.

Verification:

- `php -l lanes/gitoxide/src/ReflogEntry.php`: pass.
- `php -l lanes/gitoxide/src/ReferenceStore.php`: pass.
- `php -l lanes/gitoxide/tests/ReflogTest.php`: pass.
- `php -l lanes/gitoxide/examples/wordpress-reflog-audit.php`: pass.
- `php -l lanes/gitoxide/fixtures/wordpress-reflog-audit.php`: pass.
- `php tools/run-tests.php lanes/gitoxide/tests/ReflogTest.php`: `1 test files, 81 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `1 test files, 371 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `38 test files, 3764 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reflog-audit.php`: exit `0`.
- `git diff --check -- lanes/gitoxide`: pass.

Dependency closure:

No new support component is needed. The slice reuses existing native file, reference-name, and reflog parsing helpers; no shell-out, live provider, credential, or external Git process is required.

Non-overlap:

This follow-up does not repeat the accepted reflog line parser, SHA-256 object-id support, prepared transaction reflog creation/deletion, packed-ref transactions, URL/refspec parsing, merge-base graph walking, protocol v2 fetch sideband progress parsing, or send-pack status packet bounds. It adds direct append empty-directory recovery and tolerant iterator diagnostics over existing reflog files.

## Follow-up: Bounded Reverse Iterator Diagnostics

Slice: `gitoxide-reflog-append-parse-parity-20260531T101839Z`

Base accepted HEAD: `334e4120b9e72c6876e51705851ef70fc2462655`

Additional upstream source truth:

- Read `gix-ref/src/store/file/log/iter.rs`.
- Read `gix-ref/tests/refs/file/log.rs`.
- Mapped `iter::backward::with_zero_sized_buffer::any_line`,
  `iter::backward::with_buffer_too_small_for_single_line::single_line`,
  and `iter::backward::with_buffer_big_enough_for_largest_line::{single_line,two_lines}`.

Mapped behavior:

- Reverse reflog iteration rejects a zero-sized buffer before scanning.
- A reverse scan whose fixed buffer cannot hold the newest complete line reports a buffer-too-small diagnostic and stops, matching upstream's iterator error boundary.
- A large enough reverse scan returns newest-to-oldest entries for logs with or without a trailing newline.
- The WordPress reflog audit example now exposes a bounded reverse scan and a too-small-buffer diagnostic without invoking `git reflog`.

Native changes:

- Added `ReflogEntry::iterateReverseBounded()` for fixed-buffer reverse reflog diagnostics.
- Added `ReferenceStore::reflogEntryResultsReverseBounded()` for store-backed bounded reverse iteration.
- Extended `wordpress-reflog-audit.php` fixture/example coverage with bounded reverse messages and small-buffer diagnostics.

Verification:

- `php -l lanes/gitoxide/src/ReflogEntry.php`: pass.
- `php -l lanes/gitoxide/src/ReferenceStore.php`: pass.
- `php -l lanes/gitoxide/tests/ReflogTest.php`: pass.
- `php -l lanes/gitoxide/examples/wordpress-reflog-audit.php`: pass.
- `php -l lanes/gitoxide/fixtures/wordpress-reflog-audit.php`: pass.
- `php tools/run-tests.php lanes/gitoxide/tests/ReflogTest.php`: `1 test files, 107 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reflog-audit.php`: exit `0`.

Dependency closure:

No new support component is needed. The slice reuses existing native reflog parsing, reference-store file reads, and commit-signature helpers; no shell-out, live provider, credential, or external Git process is required.

Non-overlap:

This does not repeat the accepted reflog line parser, direct append, empty-directory recovery, tolerant parse diagnostics, prepared-reference reflog transactions, packed-ref transactions, send-pack status parsing, sparse-checkout, or commit-signature slices. It adds the remaining fixed-buffer reverse scan boundary from upstream `gix-ref` reflog iteration.

## Follow-up: Carriage-Return Message Preservation

Slice: `gitoxide-reflog-append-parse-parity-20260531T105402Z`

Base accepted HEAD: `229ee6ac6ba54ebcac89b65db02638641eecef2d`

Additional upstream source truth:

- Read `gix-ref/src/store/file/log/line.rs`.
- Read `gix-ref/tests/refs/file/log.rs`.
- Read `gix-ref/src/store/file/loose/reflog.rs`.
- Ran exact upstream check:
  `timeout 180 cargo test -p gix-ref --test refs file::log::line::write_to::round_trips --features sha1,sha256 -- --exact --nocapture`
  passed `1` test, `0` failed, `143` filtered.

Mapped behavior:

- The upstream reflog line writer rejects LF bytes in messages, but its message check is byte-specific and does not reject carriage-return bytes inside the message payload.
- Reflog message parsing continues to split on the first tab and the first LF line terminator, so an embedded CR remains part of the parsed message.
- Upstream parser coverage also keeps angle brackets inside tab-separated messages separate from the committer signature.

Native changes:

- Relaxed direct, prepared, and standalone reflog append validation to reject LF bytes while preserving CR bytes in the message.
- Added focused parser coverage for angle brackets inside a reflog message.
- Extended the WordPress reflog audit fixture with a CR-bearing deployment progress fragment so the example proves native audit-message preservation without invoking `git reflog`.

Verification:

- `php -l lanes/gitoxide/src/ReflogEntry.php`: pass.
- `php -l lanes/gitoxide/src/ReferenceStore.php`: pass.
- `php -l lanes/gitoxide/src/PreparedReferenceTransaction.php`: pass.
- `php -l lanes/gitoxide/tests/ReflogTest.php`: pass.
- `php -l lanes/gitoxide/fixtures/wordpress-reflog-audit.php`: pass.
- `php tools/run-tests.php lanes/gitoxide/tests/ReflogTest.php`: `1 test files, 124 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `1 test files, 393 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `39 test files, 4300 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-reflog-audit.php`: exit `0`.

Dependency closure:

No new support component is needed. The slice reuses existing native reflog parsing, reference-store append, prepared transaction, and commit-signature helpers; no shell-out, live provider, credential, or external Git process is required.

Non-overlap:

This does not repeat the accepted direct append, empty-directory recovery, tolerant iterator diagnostics, bounded reverse scans, SHA-256 reflog IDs, prepared-reference transaction ordering, packed-ref sidecar refresh, or protocol/send-pack slices. It narrows the remaining reflog append message-byte validation gap against upstream writer behavior.

## Follow-up: Symbolic Ref Peeled-Object Reflog

Slice: `gitoxide-reflog-append-parse-parity-20260531T112710Z`

Base accepted HEAD: `729105b48b26aa61ef0db4b008592ded7b7410d2`

Additional upstream source truth:

- Read `gix-ref/src/store/file/transaction/commit.rs`.
- Read `gix-ref/src/store/file/loose/reflog.rs`.
- Read `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs`.
- Ran exact upstream check:
  `timeout 180 cargo test -p gix-ref --test refs file::transaction::prepare_and_commit::create_or_update::symbolic_reference_writes_reflog_if_previous_value_is_set --features sha1,sha256 -- --exact --nocapture`
  passed `1` test, `0` failed, `143` filtered.

Mapped behavior:

- When a reference update writes a symbolic target and the previous-value mode is `ExistingMustMatch(Object)`, upstream `gix-ref` writes a reflog entry for the symbolic ref using a null old object id and the supplied object id as the new reflog object id.
- The symbolic referent is not created by this accommodation; only the symbolic ref and its reflog are written.
- Symbolic updates without an object-valued `ExistingMustMatch` continue to skip reflog writes, and ordinary object updates keep using the object target as before.

Native changes:

- Added `ReferenceStore::leafReflogTargetForUpdate()` and wired `updateWithReport()` to use the object-valued `ExistingMustMatch` target as the reflog object id for symbolic reference updates.
- Added focused PHP coverage for the symbolic-ref reflog accommodation, the non-object guard, and normal object-update behavior.
- Extended `wordpress-reflog-audit.php` to record peeled object provenance while creating a symbolic current-site ref, without invoking `git reflog` or `git update-ref`.

Verification:

- Before focused PHP pair: `php tools/run-tests.php lanes/gitoxide/tests/ReflogTest.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `2 test files, 517 assertions, 0 failures`.
- After focused PHP pair: `php tools/run-tests.php lanes/gitoxide/tests/ReflogTest.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `2 test files, 538 assertions, 0 failures`.
- Full Gitoxide PHP lane: `php tools/run-tests.php lanes/gitoxide/tests`: `39 test files, 4376 assertions, 0 failures`.
- `php -l lanes/gitoxide/src/ReferenceStore.php`: pass.
- `php -l lanes/gitoxide/tests/ReflogTest.php`: pass.
- `php -l lanes/gitoxide/examples/wordpress-reflog-audit.php`: pass.
- `php -l lanes/gitoxide/fixtures/wordpress-reflog-audit.php`: pass.
- `php lanes/gitoxide/examples/wordpress-reflog-audit.php`: exit `0`.

Dependency closure:

No new support component is needed. The slice reuses existing native reference targets, previous-value checks, reflog parsing/appending, commit signatures, and reference-store file I/O; no shell-out, live provider, credential, or external Git process is required.

Non-overlap:

This does not repeat accepted reflog message byte validation, direct append, empty-directory recovery, tolerant iterator diagnostics, bounded reverse scans, SHA-256 reflog IDs, dereferenced symbolic update/delete split behavior, prepared-reference transaction ordering, packed-ref sidecar refresh, object database, protocol, send-pack, sparse-checkout, or config slices. It maps the upstream clone-accommodation path for symbolic reference updates carrying an object-valued `ExistingMustMatch` target.
