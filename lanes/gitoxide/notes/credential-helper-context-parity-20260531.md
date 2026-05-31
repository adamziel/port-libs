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

---

Slice: `gitoxide-credential-helper-context-parity-20260531T181455Z`
Base accepted HEAD: `f239ae84229f0ac8ecc07e38ef32523b43f8024f`

## Source Truth

- Upstream `gix-credentials/src/protocol/context/mod.rs` calls
  `gix_url::parse()` in `Context::destructure_url_in_place()`, then copies the
  parsed scheme, user, password, normalized host, non-default port, and
  trimmed decoded path back into the credential context.
- Upstream `gix-url` parses percent-encoded user/password/path components,
  normalizes DNS-like hostnames to lowercase, strips brackets from SSH IPv6
  hosts, accepts scp-like SSH URLs, accepts local/file URLs without a host, and
  treats `git://` port `9418` as the default port.
- Upstream credential context serialization writes `protocol`, `host`,
  `username`, `password`, and `oauth_refresh_token` from Rust `String` fields,
  while `path` and `url` remain byte strings.

## PHP Delta

- `CredentialContext::destructureUrl()` now reuses the lane's native
  `GitUrl::parse()` implementation instead of PHP `parse_url()`.
- Focused tests now cover percent-decoded HTTP paths with query/fragment
  bytes, normalized hosts, default `git://` port elision, SSH IPv6 host
  bracket stripping, scp-like SSH URLs, and file URLs without a host.
- `CredentialContext::storageBytes()` now rejects invalid UTF-8 for the
  upstream `String` fields while continuing to preserve byte-string `path` and
  `url` fields.
- The WordPress credential-context fixture/example now records percent-decoded
  deployment repository paths and normalized helper hosts without invoking
  `git credential` or reading a credential store.

## Verification

- Red-first probe before the patch:
  `php -r 'require "tools/bootstrap.php"; $c=(new PortLibs\Gitoxide\CredentialContext(url:"https://USER%20name:p%40ss%3Aword@EXAMPLE.com:443/path/with%20spaces/file"))->destructureUrl(true); var_export([$c->username,$c->password,$c->host,$c->path]);'`
  returned `['USER name', 'p@ss:word', 'EXAMPLE.com', 'path/with%20spaces/file']`.
- Red-first probe before the patch:
  `php -r 'require "tools/bootstrap.php"; (new PortLibs\Gitoxide\CredentialContext(url:"User@HOST.xz:repo.git"))->destructureUrl();'`
  raised `InvalidArgumentException: Either 'url' field or both 'protocol' and
  'host' fields must be provided`.
- Red-first probe before the patch:
  `php -r 'require "tools/bootstrap.php"; (new PortLibs\Gitoxide\CredentialContext(url:"file:///srv/repo.git"))->destructureUrl();'`
  raised `InvalidArgumentException: Either 'url' field or both 'protocol' and
  'host' fields must be provided`.
- Red-first probe before the patch:
  `php -r 'require "tools/bootstrap.php"; echo (new PortLibs\Gitoxide\CredentialContext(username:"bad\xff"))->storageBytes();'`
  returned `username=bad...` instead of rejecting the invalid UTF-8 username.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php`:
  `1 test files, 99 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `3 test files, 185 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `39 test files, 5050 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php`: exited `0`.
- `php -l` passed for changed PHP files.
- `php -r` JSON validation passed for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/gitoxide`: exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`GitUrl` parser plus `CredentialContext` serialization logic; no live
credential store, provider config, SSH process, Git binary, or shared
support-library activation gate is required.
