# Reference Transaction Windows Device Ref Guard Parity - 2026-06-01

## Source Truth

- Upstream `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs`
  rejects prepared updates for Windows device-name reference components when
  `prohibit_windows_device_names` is enabled.
- Upstream `gix-ref/src/store/file/find.rs` applies the same guard to physical
  loose reference paths before filesystem access.
- Upstream `gix-validate/src/path.rs` treats device names such as `CON`,
  `CONIN$`, `CONOUT$`, `AUX`, `NUL`, `PRN`, `COM1`-`COM9`, and `LPT0`-`LPT9`
  as protected when they appear as a path component, including dotted suffixes.

## Native PHP Delta

- `ReferenceStore` now has an opt-in `prohibitWindowsDeviceNames` guard on the
  constructor and `ReferenceStore::at()`.
- Physical reference names are validated after namespace expansion and before
  loose or packed transaction preflight can create `.lock` files or reflogs.
- The default remains permissive, matching upstream behavior on Unix when the
  Windows protection flag is disabled.
- The WordPress reference transaction smoke now verifies a protected
  `refs/heads/CON` tenant ref fails before any `refs/` or `logs/` side effects.

## Verification

- `php -l lanes/gitoxide/src/ReferenceStore.php` - no syntax errors.
- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php` - no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-reference-transaction.php` - no
  syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-reference-transaction.php` - no
  syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php` - 1
  test file, 719 assertions, 0 failures.
- `php lanes/gitoxide/examples/wordpress-reference-transaction.php` - exit 0.
- `php tools/run-tests.php lanes/gitoxide/tests` - 40 test files, 8429
  assertions, 0 failures.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
reference store, prepared transaction, namespace, reflog, and WordPress
reference transaction example surfaces; no shell-out, live provider, credential
store, or external Git process is required.

## Non-Overlap

This does not repeat accepted prepared no-op lock/reflog behavior, dereferenced
symbolic update/delete reflog splits, packed-reference transaction update/delete
parity, symbolic reflog locks, pseudo-ref packed-lock exclusion, broken-ref
delete handling, send-pack receive-status work, object database, pack/index,
pathspec, sparse-checkout, merge-base, or tree-merge slices. It is limited to
the upstream protected Windows device-name reference path guard before
transaction lock and reflog side effects.
