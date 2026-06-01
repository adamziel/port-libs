# Credential Helper Context HTTP Userinfo Byte Path Parity - 2026-06-01

Slice: `gitoxide-credential-helper-context-parity-20260601T105938Z`
Base accepted HEAD: `33333a56ebb8828822e56091b018c21a9ae7058c`

## Source Truth

- Upstream `gix-credentials/src/protocol/context/mod.rs`
  `Context::destructure_url_in_place()` delegates the byte-string `url` field
  to `gix_url::parse()` and then copies the parsed scheme, user, password,
  host, and slash-trimmed path into the credential context.
- Upstream `gix-url/tests/url/parse/http.rs` distinguishes
  `http://:password@example.com/...` from `http://@example.com/...`: the
  password-only form keeps an empty username plus password, while the empty
  userinfo form normalizes to no username and no password.
- Upstream `gix-url/tests/url/parse/file.rs` accepts local file paths without a
  URL protocol as byte-preserving file URLs, including non-UTF8 path bytes.

## PHP Delta

- Added focused `CredentialContextTest` coverage for credential context URL
  destructuring of HTTP password-only helper URLs, empty HTTP userinfo helper
  URLs, and non-UTF8 local mirror paths.
- Extended the WordPress credential-context fixture/example with deployment
  diagnostics for distinguishing password-only helper remotes from empty
  userinfo remotes and preserving byte-oriented local mirror paths.
- No production source change was needed; the existing native `CredentialContext`
  and `GitUrl` parser path already matched this upstream behavior on the
  current accepted base.

## Verification

- Baseline focused context test before the patch:
  `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php`:
  `1 test files, 196 assertions, 0 failures`.
- Focused context test after the patch:
  `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php`:
  `1 test files, 217 assertions, 0 failures`.
- Credential family after the patch:
  `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `4 test files, 403 assertions, 0 failures`.
- Full Gitoxide lane after the patch:
  `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 8895 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-credential-context.php`: exited `0`.
- Changed PHP lint:
  `php -l lanes/gitoxide/tests/CredentialContextTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-credential-context.php`, and
  `php -l lanes/gitoxide/examples/wordpress-credential-context.php`: no syntax
  errors.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
credential context model and lane-local `GitUrl` parser. It does not read
credential stores, provider config, OAuth/browser state, process environments,
live remotes, external Git binaries, or helper processes.

## Non-Overlap

This does not repeat accepted credential signed-integer/boolean parsing,
parse-time UTF-8 validation, CR-byte line parsing, HTTP root-path clearing,
HTTP path-disabled preservation, constructor string-field validation, helper
exchange action mapping, raw next-action preservation, cascade quit ordering,
prompt fallback, platform helper selection, smart HTTP proxy credentials, SSH
credential context metadata, receive-pack transport, pack/index, object
database, reference transactions, sparse-checkout, pathspec, merge-base,
URL/refspec display coverage, or tree-merge behavior. It is bounded to applying
already-native Git URL password-only userinfo and local byte-path behavior to
credential helper contexts.
