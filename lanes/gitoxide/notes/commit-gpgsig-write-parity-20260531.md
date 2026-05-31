# Commit gpgsig write parity - 2026-05-31

Micro-slice: `gitoxide-commit-signature-gpgsig-parity-20260531T094813Z`

Base accepted HEAD: `ffcc95ebfcac7bbcd16b24facd07c90559f1565a`

## Upstream source truth

- Re-read `gitoxide-core/src/repository/commit.rs::sign`: it returns the
  existing object id if `extra_headers().pgp_signature()` is present, otherwise
  it feeds the unsigned commit bytes to the signer and appends one
  `SIGNATURE_FIELD_NAME` (`gpgsig`) extra header with the detached signature
  bytes before writing the new commit object.
- Re-read `gix-object/src/commit/write.rs` and `gix-object/src/encode.rs`:
  commit extra headers are serialized with `header_field_multi_line()`, which
  writes the first signature line after `gpgsig ` and writes later signature
  lines as continuation lines prefixed by one space.
- Re-read `gix-object/src/commit/mod.rs::SignedData`: verification data is the
  original commit bytes with the signature header byte range removed.

## Native behavior added

- `Commit::withGpgSignature()` now returns an immutable signed commit when the
  input commit is unsigned, appending a `gpgsig` extra header after existing
  extra headers and reparsing the resulting storage bytes so
  `signatureForVerification()` can expose the inserted signature range.
- Already-signed commits are stable no-ops, matching Gitoxide's sign command
  early return when a `gpgsig` header is already present.
- The WordPress commit-signature fixture/example now covers detached deployment
  signature insertion without invoking `gpg` or any external Git binary.

## Evidence

- Baseline before edit: `php tools/run-tests.php lanes/gitoxide/tests/CommitTest.php`
  - `1 test files, 247 assertions, 0 failures`
- After edit: `php tools/run-tests.php lanes/gitoxide/tests/CommitTest.php`
  - `1 test files, 271 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `38 test files, 3879 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-commit-signature.php`
  - exited `0`
- `php -l lanes/gitoxide/src/Commit.php`
  - no syntax errors
- `php -l lanes/gitoxide/tests/CommitTest.php`
  - no syntax errors
- `php -l lanes/gitoxide/examples/wordpress-commit-signature.php`
  - no syntax errors
- `php -l lanes/gitoxide/fixtures/wordpress-commit-signature.php`
  - no syntax errors
- `git diff --check -- lanes/gitoxide`
  - exited `0`
- JSON validation for `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` and
  `lanes/gitoxide/lane-status.json`
  - passed

## Non-overlap

This builds on but does not repeat the accepted raw `gpgsig` extraction slice
from source commit `e319fe19`. The new mapped behavior is the signing/write
side: appending detached `gpgsig` bytes with the upstream multiline commit
writer shape, preserving existing extra-header order, returning no change for
already-signed commits, and proving the verification signed data matches the
original unsigned commit bytes.

## Dependency closure

No new support component is needed. The slice reuses native commit parsing,
commit object writing, multiline header encoding, and signed-data range
helpers. External `gpg`, live SSH, provider credentials, and Git binaries are
not executed or counted.
