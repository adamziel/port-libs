# Credential Helper Context Constructor String Parity - 2026-06-01

Slice: `gitoxide-credential-helper-context-parity-20260601T081613Z`
Base accepted HEAD: `cd382f66a9c80c833a3567dcc34622923a1e8fb9`

## Source Truth

- Upstream `gix-credentials/src/protocol/mod.rs` stores credential context
  `protocol`, `host`, `username`, `password`, and `oauth_refresh_token` as
  Rust `String` fields.
- The same upstream context stores `path` and `url` as `BString` byte fields.
- Upstream `gix-credentials/src/protocol/context/serde.rs` enforces this
  boundary when decoding helper input: String fields must be valid UTF-8, while
  byte fields may carry non-UTF-8 bytes until write-time NUL/LF validation.

## PHP Delta

- `CredentialContext` now validates direct constructor values for
  `protocol`, `host`, `username`, `password`, and `oauthRefreshToken` as
  valid UTF-8, matching the upstream Rust `String` boundary outside helper
  stdin decoding.
- `path` and `url` remain byte-preserving constructor fields. The focused test
  and WordPress credential-context fixture verify `0xff` bytes round-trip
  through storage bytes for those fields.
- The WordPress credential-context example now reports constructor-side
  string-field rejection and byte-field preservation without invoking
  `git credential`, provider helpers, or any credential store.

## Verification

- Red-first probe before the patch:
  `php -r 'require "tools/bootstrap.php"; $c = new PortLibs\Gitoxide\CredentialContext(username: "bad\xff"); echo bin2hex($c->username ?? ""), "\n";'`
  returned `626164ff`, accepting malformed UTF-8 in a String-equivalent field.
- After the patch:
  `php -r 'require "tools/bootstrap.php"; try { new PortLibs\Gitoxide\CredentialContext(username: "bad\xff"); echo "accepted\n"; } catch (Throwable $e) { echo $e::class, ": ", $e->getMessage(), "\n"; }'`
  returned `InvalidArgumentException: Credential context field username must be valid UTF-8`.
- `php -l lanes/gitoxide/src/CredentialContext.php`
- `php -l lanes/gitoxide/tests/CredentialContextTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-credential-context.php`
- `php -l lanes/gitoxide/examples/wordpress-credential-context.php`
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `4 test files, 369 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 8193 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php`: exited `0`.
- JSON validation for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`: both decoded with
  `JSON_THROW_ON_ERROR`.
- `git diff --check -- lanes/gitoxide`: exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
credential context model and existing WordPress credential fixture. It does
not read credential stores, process environments, provider config,
OAuth/browser state, live remotes, external Git binaries, or helper processes.

## Non-Overlap

This does not repeat accepted credential URL destructuring, HTTP root-path
clearing, signed integer/boolean parsing, parse-time helper input UTF-8
validation, CR-byte line parsing, helper exchange action mapping, raw
next-action preservation, cascade quit ordering, prompt fallback, platform
helper selection, smart HTTP proxy credentials, SSH credential context
metadata, receive-pack transport, pack/index, object database, reference
transactions, sparse-checkout, pathspec, merge-base, or tree-merge behavior.
