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

---

Slice: `gitoxide-credential-helper-context-parity-20260531T185123Z`
Base accepted HEAD: `0c0eec061390da3a2185ec8623476b5865dd4a49`

## Source Truth

- Upstream `gix-credentials/src/protocol/context/mod.rs`
  `Context::destructure_url_in_place()` parses the URL with `gix_url::parse()`
  and, when the URL scheme is not HTTP(S) or `use_http_path` is enabled,
  assigns `self.path` from the trimmed parsed path result.
- Because that assignment uses the parsed path result directly, a root/empty
  HTTP path under `use_http_path=true` clears any earlier helper `path`
  instead of preserving stale repository context.
- Upstream `gix-credentials/src/helper/cascade.rs` processes a helper-provided
  `url` after direct helper fields by destructuring it back into the cascade
  context before later helpers continue.

## PHP Delta

- `CredentialContext::destructureUrl()` now clears `path` when URL
  destructuring is path-sensitive and the parsed path trims to an empty value.
- `CredentialContextTest` covers an existing stale path being cleared by
  `https://github.com/` with `useHttpPath=true`.
- `CredentialCascadeTest` covers an upstream-shaped helper cascade where one
  helper returns a stale `path` plus a root HTTP `url`, and the following
  helper supplies credentials after the stale path has been cleared.
- The WordPress credential-context fixture/example now exposes the root HTTP
  path-clearing guard for deployment helper diagnostics.

## Verification

- Red-first probe before the patch:
  `php -r 'require "tools/bootstrap.php"; $c=(new PortLibs\Gitoxide\CredentialContext(url:"https://example.com/", path:"stale/repo"))->destructureUrl(true); var_export($c->path); echo "\n";'`
  returned `'stale/repo'`.
- Probe after the patch:
  `php -r 'require "tools/bootstrap.php"; $c=(new PortLibs\Gitoxide\CredentialContext(url:"https://example.com/", path:"stale/repo"))->destructureUrl(true); var_export($c->path); echo "\n";'`
  returned `NULL`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `3 test files, 192 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `39 test files, 5107 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php`: exited `0`.
- `php -l` passed for changed PHP files.
- `php -r` JSON validation passed for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/gitoxide`: exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`GitUrl` parser and credential cascade/context model; no live credential
store, provider config, SSH process, Git binary, or shared support-library
activation gate is required.

---

Slice: `gitoxide-credential-helper-context-parity-20260531T220340Z`
Base accepted HEAD: `9ef60eb910c3006c081a236c1ec05f4d0e7024c4`

## Source Truth

- Upstream `gix-credentials/src/helper/cascade.rs` merges helper response
  protocol/host/path/username/password/token/expiry fields into the destination
  context, then stops immediately when username and password are complete.
- The same upstream loop only copies `quit` into the destination context after
  the completeness check, so `username=...\npassword=...\nquit=1\n` returns the
  identity and stops the cascade without propagating `quit` into the successful
  helper outcome.
- Upstream `gix-credentials/tests/helper/cascade.rs`
  `helpers_can_quit_and_their_creds_are_taken_if_complete` asserts the
  complete identity behavior for that boundary.

## PHP Delta

- `CredentialCascade` now treats helper `quit` as a stop condition only when
  the helper response has not already completed username and password.
- The incomplete-quit path still records `quit=true` before stopping, so the
  existing quit error and prompt-fallback behavior is preserved.
- `CredentialCascadeTest` now asserts complete helper credentials consume the
  identity before `quit` is propagated, and the WordPress credential cascade
  fixture/example records the same emergency-helper boundary without invoking
  `git credential`.

## Verification

- Red-first probe before the patch:
  `php <<'PHP' ... CredentialCascade([fn() => "username=user\npassword=pass\nquit=1\n"]) ... PHP`
  returned `true` for `$result->quit`.
- After the patch the same probe returns `false`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialCascadeTest.php`:
  `1 test files, 77 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `3 test files, 225 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `39 test files, 5841 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-cascade.php`: exited `0`.
- `php -l` passed for changed PHP files.
- `php -r` JSON validation passed for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/gitoxide`: exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
credential context/cascade model and static upstream `gix-credentials` source
truth; it does not read credential stores, invoke provider helpers, shell out
to `git credential`, or require a shared support-library activation gate.

---

Slice: `gitoxide-credential-helper-context-parity-20260531T205538Z`
Base accepted HEAD: `7a6ad881ab7ec5dade7133aeca014b7a5e54577c`

## Source Truth

- Upstream `gix-credentials/src/protocol/context/serde.rs` parses
  `password_expiry_utc` with Rust `i64::from_str()` through
  `gix_date::SecondsSinceUnixEpoch`, so overflow or underflow values become
  absent instead of clamped.
- Upstream `gix-config-value/src/boolean.rs` parses numeric boolean values
  with `i64::from_str()` before `quit` is converted into the credential
  context, so overflowing numeric `quit` values are invalid and decode as
  absent.
- Upstream `gix-credentials/src/helper/cascade.rs` only clears secrets for an
  expired helper response when a parsed `password_expiry_utc` is present and
  older than `now`.

## PHP Delta

- `CredentialContext::fromBytes()` now parses signed numeric helper fields
  through an explicit i64 bound check before converting to PHP integers.
- Overflowing `password_expiry_utc` values now decode as `null` instead of
  clamping to `PHP_INT_MAX` or `PHP_INT_MIN`.
- Overflowing numeric `quit` values now decode as `null` instead of `true`.
- `CredentialCascadeTest` covers an underflowed expiry helper response that
  must keep otherwise complete credentials instead of clearing them as expired.
- The WordPress credential-context fixture/example records overflow expiry and
  quit values being ignored without invoking `git credential` or reading any
  credential store.

## Verification

- Red-first probe before the patch:
  `php <<'PHP' ... CredentialContext::fromBytes("password_expiry_utc=9223372036854775808\n")->passwordExpiryUtc ... PHP`
  returned `9223372036854775807`; the underflow probe returned
  `-9223372036854775808`.
- Red-first probe before the patch:
  `CredentialContext::fromBytes("quit=9223372036854775808\n")->quit`
  returned `true`.
- `php -l lanes/gitoxide/src/CredentialContext.php`:
  no syntax errors.
- `php -l lanes/gitoxide/tests/CredentialContextTest.php`:
  no syntax errors.
- `php -l lanes/gitoxide/tests/CredentialCascadeTest.php`:
  no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-credential-context.php`:
  no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-credential-context.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `3 test files, 220 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `39 test files, 5703 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php`: exited `0`.
- `php -r` JSON validation passed for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/gitoxide`: exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP string and
integer-bound checks inside the existing `gix-credentials` context/cascade
model; no live credential store, provider config, Git binary, shell helper, or
shared support-library activation gate is required.

---

Slice: `gitoxide-credential-helper-context-parity-20260531T230236Z`
Base accepted HEAD: `292ada6b86cc431f7b1537075eacedfb4e905cf4`

## Source Truth

- Upstream `gix-credentials/src/program/main.rs` maps helper action aliases:
  `fill|get` to get, `approve|store` to store, and `reject|erase` to erase.
- The same upstream entrypoint decodes helper stdin with
  `Context::from_bytes()` and only rejects the context when `url` is absent
  and either `protocol` or `host` is absent.
- Upstream `gix-credentials/tests/program/main.rs` asserts that
  `protocol=https\nhost=github.com\n` is valid without auto-populating `url`,
  `url=https://github.com\n` is valid without auto-populating protocol/host,
  and single `host=` or `protocol=` inputs fail before the credential callback
  is invoked.

## PHP Delta

- Added `CredentialHelperExchange`, a native PHP helper-entrypoint model for
  the upstream program-main boundary.
- The helper exchange now validates helper stdin context before invoking the
  callback, preserves url-only contexts without destructuring, preserves
  protocol+host contexts without populating `url`, serializes get results
  through `CredentialContext::storageBytes()`, and suppresses output for
  store/erase actions.
- Added focused `CredentialHelperExchangeTest.php` coverage and extended the
  WordPress credential-context fixture/example with the helper stdin exchange
  path without invoking `git credential` or a provider-backed credential store.

## Verification

- Red-first before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php`
  failed with `Class "PortLibs\Gitoxide\CredentialHelperExchange" not found`
  across the new helper-exchange checks.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php`:
  `1 test files, 14 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `4 test files, 245 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 6096 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php`: exited `0`.
- `php -l` on changed PHP files:
  no syntax errors for `CredentialHelperExchange.php`,
  `CredentialHelperExchangeTest.php`, `CredentialContextTest.php`,
  `wordpress-credential-context.php` fixture, and
  `wordpress-credential-context.php` example.
- JSON validation passed for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/gitoxide`: exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
credential context serialization/parser and a lane-local callback boundary; it
does not read credential stores, invoke provider helpers, shell out to
`git credential`, run live-service tests, or require a shared support-library
activation gate.

## Non-Overlap

This does not repeat accepted credential-context URL destructuring,
path-clearing, UTF-8/storage byte handling, i64/boolean parsing, cascade quit
ordering, credential program definition parsing, smart HTTP proxy credentials,
SSH credential context transport metadata, protocol, pack/index, object
database, reference, sparse-checkout, pathspec, merge-base, or tree-merge
behavior. The old May 25 smart HTTP receive-pack rework notes are stale for
this slice.

---

Slice: `gitoxide-credential-helper-context-parity-20260601T001512Z`
Base accepted HEAD: `971daa0084ecd1928b1d3f4a05b94528a5194f66`

## Source Truth

- Upstream `gix-credentials/src/protocol/context/serde.rs`
  `Context::from_bytes()` validates each known Rust `String` field as soon as
  the line is decoded. An invalid UTF-8 `username`, `password`, `host`,
  `protocol`, or `oauth_refresh_token` value fails immediately even if a later
  duplicate key would overwrite it.
- The same upstream decoder keeps `url` and `path` as byte strings, so those
  fields may contain non-UTF-8 bytes and duplicate keys still follow the usual
  last-value-wins context behavior.

## PHP Delta

- `CredentialContext::fromBytes()` now performs parse-time UTF-8 validation
  for known String-valued fields instead of only validating the final stored
  value.
- Focused tests cover a duplicate invalid `username` followed by a valid
  replacement, plus a duplicate byte-string `path` that remains accepted.
- The WordPress credential-context fixture/example records the same guard for
  deployment helper diagnostics without invoking `git credential` or reading a
  credential store.

## Verification

- Red-first probe before the patch:
  `php -r 'require "tools/bootstrap.php"; try { $c=PortLibs\\Gitoxide\\CredentialContext::fromBytes("username=bad\\xff\\nusername=ok\\n"); var_export($c->username); } catch (Throwable $e) { echo get_class($e), ": ", $e->getMessage(), "\\n"; }'`
  returned `'ok'`.
- `php -l lanes/gitoxide/src/CredentialContext.php`,
  `php -l lanes/gitoxide/tests/CredentialContextTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-credential-context.php`, and
  `php -l lanes/gitoxide/examples/wordpress-credential-context.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php`:
  `1 test files, 130 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `4 test files, 249 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 6457 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php`: exited `0`.
- JSON validation passed for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/gitoxide`: exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
credential context parser and string validation; it does not read credential
stores, invoke provider helpers, shell out to Git, run live-service tests, or
require a shared support-library activation gate.

## Non-Overlap

This does not repeat accepted credential URL destructuring, HTTP path
clearing, signed i64/boolean parsing, helper exchange action mapping, cascade
quit ordering, credential program definition parsing, smart HTTP proxy
credential lifecycle, SSH credential context metadata, protocol, object,
reference, sparse-checkout, pathspec, merge-base, or tree-merge behavior. The
old May 25 smart HTTP receive-pack rework notes remain stale for this slice.

---

Slice: `gitoxide-credential-helper-context-parity-20260601T012007Z`
Base accepted HEAD: `b9bbeca66ecf5a12b5cede18d997f59a57398d59`

## Source Truth

- Upstream `gix-credentials/src/helper/cascade.rs`
  `Cascade::platform_builtin()` returns one configured helper per platform:
  `osxkeychain` on macOS, `libsecret` on Linux, `manager-core` on Windows, and
  no helper on other platforms.
- Upstream stores those as custom credential helper program definitions, so
  the command shape is external `git credential-<name>` plus the Git
  credential action name `get`, `store`, or `erase`.

## PHP Delta

- `CredentialProgram::platformBuiltins()` now exposes deterministic native
  command planning for the upstream platform default helper names without
  invoking any helper process.
- `CredentialProgramTest` covers Linux, macOS, Windows, and unknown-platform
  selection plus get/store/erase action command shapes.
- The WordPress credential-program fixture/example records the platform
  default helper plans for deployment tooling preflight without reading
  credential stores.

## Verification

- Red-first probe before the patch:
  `php -r 'require "tools/bootstrap.php"; var_export(method_exists(PortLibs\\Gitoxide\\CredentialProgram::class, "platformBuiltins")); echo "\n";'`
  returned `false`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `1 test files, 44 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `4 test files, 265 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 6659 assertions, 0 failures`.
- `php -l` reported no syntax errors for `CredentialProgram.php`,
  `CredentialProgramTest.php`, the WordPress credential-program fixture, and
  the WordPress credential-program example.
- `php lanes/gitoxide/examples/wordpress-credential-program.php`: exited `0`.
- JSON validation passed for `lanes/gitoxide/lane-status.json`.
- `git diff --check -- lanes/gitoxide`: exited `0`.

## Dependency Closure

No new support component is needed. This slice only maps upstream platform
helper command selection into native PHP command planning; it does not invoke
credential helpers, read credential stores, inspect provider config, or
require a shared support-library activation gate.

## Non-Overlap

This does not repeat accepted credential context parsing, URL destructuring,
cascade quit ordering, helper exchange stdin validation, credential program
custom-definition parsing, smart HTTP proxy credentials, SSH credential
context metadata, receive-pack, pack/index, object database, reference,
sparse-checkout, pathspec, merge-base, or tree-merge behavior. The May 25
smart HTTP receive-pack rework notes remain stale for this slice.

---

Slice: `gitoxide-credential-helper-context-parity-20260601T023322Z`
Base accepted HEAD: `aae30af0e20a252fbc6d49ffeaf4400dbc5a6747`

## Source Truth

- Upstream `gix-credentials/src/helper/mod.rs` models `NextAction` as the
  previously emitted helper context bytes and implements `TryFrom<&NextAction>
  for Context` by decoding those bytes with `Context::from_bytes()`.
- Upstream `gix-credentials/tests/helper/cascade.rs` depends on that stored
  context for cascade parity: URL usernames are retained when helpers only
  supply a password, direct helper `protocol`/`host`/`path` fields are copied
  verbatim, and a helper-provided `url` is processed after direct fields so it
  overwrites earlier protocol/host/path context.

## PHP Delta

- `CredentialCascadeResult` now exposes `nextActionContext()`, mirroring
  upstream `NextAction -> Context` decoding without invoking `git credential`
  or a credential store.
- `CredentialCascadeTest` now covers the upstream next-action context
  precedence cluster: direct helper paths remain verbatim, helper URLs are
  destructured last, and usernames parsed from the original URL survive a
  password-only helper.
- The WordPress credential cascade fixture/example now records the decoded
  next-action context and a verbatim helper context for deployment diagnostics.

## Verification

- Red-first probe before the patch:
  `php -r 'require "tools/bootstrap.php"; var_export(method_exists(PortLibs\Gitoxide\CredentialCascadeResult::class, "nextActionContext")); echo "\n";'`
  returned `false`.
- `php -l lanes/gitoxide/src/CredentialCascadeResult.php`,
  `php -l lanes/gitoxide/tests/CredentialCascadeTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-credential-cascade.php`, and
  `php -l lanes/gitoxide/examples/wordpress-credential-cascade.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialCascadeTest.php`:
  `1 test files, 98 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `4 test files, 286 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 6953 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-cascade.php`: exited `0`.
- `php -r` JSON validation passed for `lanes/gitoxide/lane-status.json`.
- `git diff --check -- lanes/gitoxide`: exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
credential context parser and cascade result bytes; it does not read
credential stores, invoke provider helpers, shell out to Git, run live-service
tests, or require a shared support-library activation gate.

## Non-Overlap

This does not repeat accepted credential context URL destructuring,
HTTP path clearing, signed i64/boolean parsing, parse-time UTF-8 validation,
helper exchange action mapping, cascade quit ordering, platform helper
selection, smart HTTP proxy credentials, SSH credential context metadata,
receive-pack, pack/index, object database, reference, sparse-checkout,
pathspec, merge-base, or tree-merge behavior. The old May 25 smart HTTP
receive-pack rework notes remain stale for this slice.

---

Slice: `gitoxide-credential-helper-context-parity-20260601T055513Z`
Base accepted HEAD: `7db0bee1b6d6b17fcc1ae3a0e1b10ac7a87ade2d`

## Source Truth

- Upstream `gix-credentials/src/protocol/context/mod.rs`
  `Context::destructure_url_in_place()` delegates the byte-string `url` field
  to `gix_url::parse()`, then copies the parsed scheme, user, host, non-default
  port, and slash-trimmed path back into the credential context.
- Upstream `gix-url/tests/url/parse/file.rs` covers local paths without a
  protocol, `file:///...` paths, file URLs with user/authority, and file IPv6
  authority preservation.
- Upstream `gix-url` extension-scheme parser behavior allows pathless
  extension remotes while hostless extension URLs keep their path bytes.

## PHP Delta

- Added focused `CredentialContextTest` assertions for local path URL
  destructuring, file URL authority/user preservation, pathless extension
  remotes clearing stale paths, and hostless extension remotes clearing stale
  hosts while retaining repository paths.
- Extended the WordPress credential-context fixture/example with deployment
  diagnostics for local mirror remotes and extension-scheme remotes without
  invoking `git credential` or reading any credential store.
- No production source change was needed; the existing native
  `CredentialContext::destructureUrl()` path already delegates through the
  lane-local `GitUrl` parser. This patch locks that upstream-backed behavior
  into focused Gitoxide lane coverage.

## Verification

- `php -l lanes/gitoxide/tests/CredentialContextTest.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-credential-context.php`: no
  syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-credential-context.php`: no syntax
  errors.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php`:
  `1 test files, 171 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`:
  `4 test files, 342 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 7701 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php`: exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
credential context model and lane-local `GitUrl` parser. It does not read
credential stores, provider config, OAuth/browser state, process environments,
live remotes, external Git binaries, or helper processes.

## Non-Overlap

This does not repeat accepted credential signed-integer/boolean parsing,
parse-time UTF-8 validation, CR-byte line parsing, HTTP root-path clearing,
helper exchange stdin validation, raw next-action preservation, cascade quit
ordering, platform helper selection, smart HTTP proxy credentials, SSH
credential context metadata, receive-pack transport, pack/index, object
database, reference transactions, sparse-checkout, pathspec, merge-base, or
tree-merge behavior. It is a bounded credential-context URL destructuring
coverage slice for local and extension-scheme remotes.
