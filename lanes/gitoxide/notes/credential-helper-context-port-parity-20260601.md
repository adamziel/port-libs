## Credential Helper Context Port Parity - 2026-06-01

Slice: `gitoxide-credential-helper-context-parity-20260601T143646Z`
Base accepted HEAD: `eef9582145ab6422998777b814b61569f08c5e06`

### Source Truth

- Upstream `gix-credentials/src/protocol/context/mod.rs` destructures context URLs through `gix_url::parse()` and appends `:port` to the helper `host` field only when the parsed port differs from the scheme default.
- Upstream `gix-url/tests/url/parse/http.rs`, `ssh.rs`, and `file.rs` cover default ports, non-default ports, IPv6 authorities, path trimming, and helper URL normalization boundaries used by credential context destructuring.

### PHP Delta

- `CredentialContextTest.php` now verifies helper-context host rendering across default `http`, `https`, `ssh`, and `git` ports, plus non-default `http`, `https`, `git`, and IPv6 `ssh` ports.
- `fixtures/wordpress-credential-context.php` and `examples/wordpress-credential-context.php` now expose the WordPress deployment helper summary for default-port elision and non-default HTTPS/SSH port preservation.
- No production source change was needed: the existing native PHP `CredentialContext::destructureUrl()` already matched the upstream behavior.

### Verification

- Before widening this slice, `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php` passed with `1 test files, 248 assertions, 0 failures`.
- `php -l lanes/gitoxide/tests/CredentialContextTest.php` reported no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-credential-context.php` reported no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-credential-context.php` reported no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php` passed with `1 test files, 277 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php` passed with `4 test files, 483 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php` exited 0.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files, 9864 assertions, 0 failures`.

### Dependency Closure

No new support component is needed. This slice reuses the existing native PHP credential-context URL parser/destructurer, WordPress fixture/example boundary, and local test harness. It did not read credential stores, invoke live credential helpers, contact remotes, or run the upstream Cargo workspace.

### Non-Overlap

This slice is bounded to credential helper context port rendering. It does not repeat prior credential context work for signed integer/boolean parsing, CR-byte line handling, UTF-8 validation, percent-decoded user/password/path fields, local/file URL handling, HTTP path disabling, password-only userinfo, helper action boundaries, cascade quit ordering, credential program parsing, smart HTTP transport, SSH receive-pack transport, pack/index, object database, refs, or pathspec behavior.
