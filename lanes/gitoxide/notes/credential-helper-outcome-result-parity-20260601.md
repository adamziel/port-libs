# Credential Helper Outcome Result Parity - 2026-06-01

Slice: `gitoxide-credential-helper-context-parity-20260601T070520Z`
Base accepted HEAD: `cc9294ac19877407e3f202dbdfd54b6a9a8fb67d`

## Source Truth

- Upstream `gix-credentials/src/protocol/mod.rs` converts helper invocation
  outcomes with `helper_outcome_to_result()`.
- A complete helper outcome consumes username/password before checking `quit`,
  while an incomplete `quit=true` outcome returns the special quit error.
- Missing helper output or incomplete identity without `quit` returns an
  identity-missing error built from the redacted original request context.
- Upstream `gix-credentials/tests/helper/mod.rs` covers both missing-identity
  and quit-special result boundaries.

## PHP Delta

- `CredentialHelperOutcome::requireIdentity()` now maps the upstream get-result
  conversion boundary for caller-side helper invocation results.
- Complete identity extraction preserves OAuth refresh tokens and succeeds even
  when the raw next action contained `quit=1`.
- Missing identity diagnostics redact password and OAuth refresh token fields
  from the original request context before exposing the diagnostic text.
- The WordPress credential-context fixture/example now records complete
  required identity extraction, redacted missing-identity diagnostics, and the
  quit-special error without invoking `git credential` or a credential store.

## Verification

- Red-first probe before the patch:
  `php -r 'require "tools/bootstrap.php"; var_export(method_exists(PortLibs\\Gitoxide\\CredentialHelperOutcome::class, "requireIdentity")); echo "\n";'`
  returned `false`.
- `php -l lanes/gitoxide/src/CredentialHelperOutcome.php`
- `php -l lanes/gitoxide/tests/CredentialHelperExchangeTest.php`
- `php -l lanes/gitoxide/tests/CredentialContextTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-credential-context.php`
- `php -l lanes/gitoxide/examples/wordpress-credential-context.php`
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `4 test files, 356 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 7888 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php`: exited `0`.
- JSON validation for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`: both decoded with
  `JSON_THROW_ON_ERROR`.
- `git diff --check -- lanes/gitoxide`: exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses the lane-local native
PHP credential context parser/serializer and callback-based helper invocation
model. It does not read credential stores, provider config, OAuth/browser
state, live remotes, external Git binaries, or helper processes.

## Non-Overlap

This does not repeat accepted credential URL destructuring, root-path clearing,
signed integer/boolean parsing, CR byte preservation, raw next-action payload
preservation, helper program parsing, cascade quit merge ordering, prompt
fallback, platform helper selection, smart HTTP proxy credentials, SSH
credential context metadata, receive-pack transport, pack/index, object
database, reference, sparse-checkout, pathspec, merge-base, or tree-merge
behavior.
