# Commit gpgsig raw signature parity - 2026-05-31

Micro-slice: `gitoxide-commit-signature-gpgsig-parity-20260531T091313Z`

Base accepted HEAD: `0843201ab605ca08cd36b696d17e3fcdd999de22`

## Upstream source truth

- Re-read `gix-object/src/commit/ref_iter.rs`, especially `CommitRefIter::signature()`.
- Re-read `gix-object/src/parse.rs` and `gix-object/src/encode.rs` for multiline object header parse/write semantics.
- Re-read `gix-object/tests/object/commit/iter.rs` signature cases: `single_line`, `signed`, `with_encoding`, `msg_footer`, and `whitespace`.
- Re-read `gix-object/tests/object/commit/from_bytes.rs::bogus_multi_gpgsig_header`.

## Native behavior added

- `Commit::signatureForVerificationFromBytes()` now mirrors `CommitRefIter::signature()` for raw commit bytes: it validates tree, parent, author, committer, optional encoding, and prior extra headers, then returns the first `gpgsig` value and signed-data bytes without decoding later tail bytes.
- Multiline commit extra-header values now preserve continuation-line terminators, matching `any_header_field_multi_line()` and `header_field_multi_line()`. This makes `pgpSignature()`, token iteration, and `mergetag` payloads expose upstream-shaped bytes.
- Author and committer parsing now rejects unconsumed timestamp suffix bytes at commit-parse and raw-signature extraction boundaries.
- The WordPress commit-signature fixture/example now covers streaming import provenance: signature verification data can be extracted from a signed commit prefix before a later tail has been decoded as a full commit.

## Evidence

- `php tools/run-tests.php lanes/gitoxide/tests/CommitTest.php`
  - `1 test files, 247 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `38 test files, 3786 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-commit-signature.php`
  - exited `0`
- `php -l` on changed PHP files
  - `Commit.php`, `CommitTest.php`, `wordpress-commit-signature.php` fixture, and `wordpress-commit-signature.php` example all passed
- `git diff --check -- lanes/gitoxide`
  - exited `0`
- JSON validation for `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` and `lanes/gitoxide/lane-status.json`
  - passed

## Non-overlap

This is a follow-up to the accepted commit `gpgsig` signed-data range stripping slice, but it does not repeat only range removal. The new mapped behavior is raw `CommitRefIter::signature()` extraction before later bytes decode, exact multiline header terminator preservation, and complete actor-signature consumption at the commit boundary.

## Dependency closure

No new support component is needed. The slice reuses existing native commit, actor signature, object-id, and Git object helpers; no shell-out, live provider, credential store, or external Git process is required.
