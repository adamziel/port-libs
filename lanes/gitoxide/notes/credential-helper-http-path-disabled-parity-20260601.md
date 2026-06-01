# Credential Helper HTTP Path Disabled Parity - 2026-06-01

Slice: `gitoxide-credential-helper-context-parity-20260601T093812Z`
Base accepted HEAD: `9495523910adeabd01c9bc2c77431af9d8027200`

## Source Truth

- Upstream `gix-credentials/src/protocol/context/mod.rs`
  `Context::destructure_url_in_place()` only updates `path` for HTTP(S) URLs
  when `use_http_path` is enabled.
- Upstream `gix-credentials/src/helper/cascade.rs` copies direct helper
  `path` fields before processing a helper-provided `url`, so with
  `use_http_path=false` an HTTP helper URL updates protocol/host while the
  direct path remains the next-action repository context.

## PHP Delta

- Added focused `CredentialContextTest` coverage for HTTP and root HTTP URL
  destructuring preserving an existing path when HTTP path matching is
  disabled.
- Added focused `CredentialCascadeTest` coverage for a helper response that
  supplies a direct `path` plus an HTTP `url`: the URL updates protocol/host,
  but the direct path is preserved in the next action when `useHttpPath=false`.
- Extended the WordPress credential-context fixture/example with the same
  deployment-helper boundary without invoking `git credential`, provider
  helpers, or a credential store.

## Verification

- `php -l lanes/gitoxide/tests/CredentialContextTest.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/CredentialCascadeTest.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-credential-context.php`: no
  syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-credential-context.php`: no
  syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php`:
  `1 test files, 196 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialCascadeTest.php`:
  `1 test files, 105 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `4 test files, 382 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 8537 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php`: exited `0`.
- Full upstream Cargo workspace was not executed.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
credential context parser, URL destructuring, and credential cascade model. It
does not read credential stores, provider config, OAuth/browser state, process
environments, live remotes, external Git binaries, or helper processes.

## Non-Overlap

This does not repeat accepted credential URL destructuring with
`use_http_path=true`, HTTP root-path clearing, signed integer/boolean parsing,
parse-time UTF-8 validation, CR-byte line parsing, constructor string-field
validation, helper exchange action mapping, raw next-action preservation,
cascade quit ordering, prompt fallback, platform helper selection, smart HTTP
proxy credentials, SSH credential context metadata, receive-pack transport,
pack/index, object database, reference transactions, sparse-checkout,
pathspec, merge-base, URL/refspec, or tree-merge behavior.
