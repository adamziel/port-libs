# Credential Helper Context Parity - 2026-05-31

Slice: `gitoxide-credential-helper-context-parity-20260531T131949Z`
Base accepted HEAD: `27153c38e7cef55880aa33fb66fba5f5470c1f89`

## Source Truth

- Upstream `gix-credentials/src/protocol/context/serde.rs` parses `quit`
  through `gix_config_value::Boolean` and parses `password_expiry_utc` as a
  signed integer.
- Upstream `gix-config-value/tests/value/boolean.rs` covers empty boolean
  values as false and plus-signed integers such as `+10` as true.
- Upstream credential context serialization treats `path` and `url` as byte
  strings while `protocol`, `host`, `username`, `password`, and
  `oauth_refresh_token` must be valid UTF-8.

## PHP Delta

- `CredentialContext::fromBytes()` now accepts optional `+` signs for
  integer-like fields.
- Empty `quit=` now parses as `false`, and plus-signed `quit=+10` parses as
  `true`, matching upstream git-config Boolean handling.
- Focused tests now assert invalid UTF-8 rejection for identity fields and
  byte-string round-trips for helper `path` and `url`.
- The WordPress credential-context fixture now covers plus-signed password
  expiry and empty `quit=` handling without invoking `git credential` or a
  credential store.

## Verification

- Red-first probe before the patch:
  `php -r 'require "tools/bootstrap.php"; $c = PortLibs\Gitoxide\CredentialContext::fromBytes("quit=\npassword_expiry_utc=+10\n"); var_export([$c->quit, $c->passwordExpiryUtc]);'`
  returned `[NULL, NULL]`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php`:
  `1 test files, 74 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `3 test files, 160 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `39 test files, 4644 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php`: exited `0`.
- `php -l` passed for changed PHP files.
- `php -r` JSON validation passed for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/gitoxide`: exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP string
validation and signed integer parsing for the existing bounded
`gix-credentials` helper context model; no live credential store, provider,
Git binary, or shared support-library activation gate is required.
