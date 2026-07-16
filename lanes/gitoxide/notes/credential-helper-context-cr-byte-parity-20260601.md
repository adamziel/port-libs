# Credential Helper Context CR Byte Parity - 2026-06-01

Slice: `gitoxide-credential-helper-context-parity-20260601T045039Z`
Base accepted HEAD: `5a7dc1daad24ba95a3c58d82c78018bfc7722899`

## Source Truth

- Upstream `gix-credentials/src/protocol/context/serde.rs` decodes helper
  input with `input.lines()`, then validates key/value bytes.
- `bstr::ByteSlice::lines()` splits only at `\n` and removes a preceding
  `\r` only as part of a CRLF terminator. Bare carriage-return bytes inside a
  line, including a final value byte without `\n`, remain part of the value.
- Upstream credential context validation rejects NUL and LF in keys/values but
  permits CR bytes, so helper `path` and `url` byte fields must preserve a
  bare CR while still accepting CRLF helper output.

## PHP Delta

- `CredentialContext::fromBytes()` now uses a small protocol-line iterator
  instead of stripping a trailing `\r` from every exploded line.
- CRLF helper output still decodes as a normal line ending, while a final bare
  `\r` in a byte-string `path` value round-trips through `storageBytes()`.
- The WordPress credential-context fixture/example now exposes the same
  deployment helper diagnostic boundary without invoking `git credential` or
  reading any credential store.

## Verification

- Red-first probe before the patch:
  `php -r 'require "tools/bootstrap.php"; $c = PortLibs\Gitoxide\CredentialContext::fromBytes("path=repo\r"); echo bin2hex($c->path ?? "null"), "\n";'`
  returned `7265706f`, dropping the final CR byte.
- After the patch, the same probe returns `7265706f0d`.
- CRLF terminator probe:
  `php -r 'require "tools/bootstrap.php"; $c = PortLibs\Gitoxide\CredentialContext::fromBytes("path=repo\r\n"); echo bin2hex($c->path ?? "null"), "\n";'`
  returns `7265706f`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php`:
  `1 test files, 145 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `4 test files, 316 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 7427 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php`: exited `0`.
- `php -l` passed for all changed PHP files.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
credential context parser/serializer and local WordPress fixture. It does not
read credential stores, provider config, OAuth/browser state, live remotes,
external Git binaries, or helper processes.

## Non-Overlap

This does not repeat accepted credential URL destructuring, HTTP root-path
clearing, signed integer/boolean parsing, parse-time UTF-8 validation, helper
program action mapping, cascade quit ordering, raw next-action preservation,
platform helper selection, smart HTTP proxy credentials, SSH credential
context metadata, receive-pack transport, pack/index, object database,
reference, sparse-checkout, pathspec, merge-base, or tree-merge behavior. The
old May 25 smart HTTP receive-pack rework notes remain stale for this
credential context byte-decoding slice.
