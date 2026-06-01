# Credential Helper Invocation Raw Next-Action Parity - 2026-06-01

Slice: `gitoxide-credential-helper-context-parity-20260601T034113Z`
Base accepted HEAD: `86e2d14305df2668712f30216ab52d92b6b533a7`

## Source Truth

- Upstream `gix-credentials/src/helper/invoke.rs` decodes helper stdout into a
  credential `Context` but stores the raw stdout bytes as `NextAction`.
- Upstream `gix-credentials/tests/helper/invoke.rs::get` asserts that a helper
  response containing `username=user`, `password=pass`, and `quit=1` consumes a
  complete identity while `outcome.next.store().payload()` still contains the
  raw `quit=1` line.
- Upstream store and erase actions send the prior next-action bytes plus one
  extra blank-line terminator and do not expect an outcome.

## PHP Delta

- Added `CredentialHelperInvocation` and `CredentialHelperOutcome` as a native
  PHP caller-side helper invocation boundary.
- `CredentialHelperInvocation::get()` serializes the request context, decodes
  helper stdout for identity fields, preserves `quit`, and keeps the raw stdout
  bytes for later store/erase actions.
- `CredentialHelperInvocation::store()` and `erase()` send the preserved
  next-action bytes plus the upstream extra newline terminator while ignoring
  helper stdout.
- The WordPress credential-context fixture/example now records a deployment
  helper invocation whose raw next action retains `quit=1` without invoking
  `git credential` or reading a credential store.

## Verification

- Red-first check before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php`
  failed with `Class "PortLibs\Gitoxide\CredentialHelperInvocation" not found`
  for the two new helper-invocation checks.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `4 test files, 309 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 7189 assertions, 0 failures`.
- `php -l` passed for changed and new PHP files.
- `php lanes/gitoxide/examples/wordpress-credential-context.php`: exited `0`.
- JSON validation passed for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/gitoxide`: exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP credential
context serialization/parsing and callback-based helper boundaries; it does
not read credential stores, provider config, OAuth/browser state, live remotes,
external Git binaries, or shell helper processes.

## Non-Overlap

This does not repeat accepted credential context URL destructuring, HTTP path
clearing, signed i64/boolean parsing, parse-time UTF-8 validation, helper
exchange program-main action mapping, cascade quit ordering, platform helper
selection, next-action context decoding from cascade results, URL credential
mutation/access helpers, smart HTTP proxy credentials, SSH credential context
metadata, receive-pack, pack/index, object database, reference, sparse-checkout,
pathspec, merge-base, or tree-merge behavior. The old May 25 smart HTTP
receive-pack rework notes remain stale for this credential slice.
