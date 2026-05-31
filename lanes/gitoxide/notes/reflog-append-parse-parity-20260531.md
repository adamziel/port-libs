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
