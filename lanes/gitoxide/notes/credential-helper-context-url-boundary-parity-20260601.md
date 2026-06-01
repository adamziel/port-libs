# Credential Helper Context URL Boundary Parity

Slice: `gitoxide-credential-helper-context-parity-20260601T195059Z`

Base accepted HEAD: `1d41b846adc61aa23aecab0fa6f70bcf0975562b`

## Source Truth

Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide`

Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`

Relevant upstream files:

- `gix-credentials/src/protocol/context/mod.rs`
- `gix-credentials/src/helper/cascade.rs`
- `gix-url/src/parse.rs`
- `gix-url/src/scheme.rs`
- `gix-credentials/tests/protocol/context.rs`

`Context::destructure_url_in_place()` delegates to `gix_url::parse()`, then
copies the parsed scheme, user, password, host, port, and path into the helper
context. `Scheme::from()` maps both `ssh+git` and `git+ssh` to SSH. The parser
elides default SSH port `22`, keeps non-default SSH ports on the host context,
preserves special empty/non-numeric SSH port host text after trailing-colon
handling, and parses Unix `file://x:/...` as a file pseudo-host of `x:`.

`Cascade::invoke()` merges helper direct fields first and processes `url` last
through `destructure_url_in_place()`. A helper URL without credentials therefore
clears stale direct username/password before the next helper supplies identity.

## Port Delta

- Added PHP credential-context assertions for `ssh+git://`, `git+ssh://`,
  default SSH port elision, non-default SSH port preservation,
  empty/non-numeric SSH port host boundaries, and Unix file pseudo-host parsing.
- Added cascade coverage proving a URL-only helper response clears stale direct
  identity fields before the following helper provides credentials.
- Extended the WordPress credential-context fixture/example with the same
  deployment-safe helper URL boundary cases. No live credential store, git
  binary, provider config, or network service is used.

Mapped coverage stays `1817 / 2886`; this deepens an already represented
credential context/cascade cluster.

## Verification

Upstream focused evidence:

```sh
timeout 180 cargo test -p gix-credentials --test credentials protocol::context::destructure_url_in_place -- --nocapture
```

Result: `5 passed, 0 failed, 45 filtered out`.

PHP verification:

```sh
php -l lanes/gitoxide/tests/CredentialContextTest.php
php -l lanes/gitoxide/tests/CredentialCascadeTest.php
php -l lanes/gitoxide/fixtures/wordpress-credential-context.php
php -l lanes/gitoxide/examples/wordpress-credential-context.php
php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialProgramTest.php
php lanes/gitoxide/examples/wordpress-credential-context.php >/tmp/gitoxide-credential-context-example.out
php tools/run-tests.php lanes/gitoxide/tests
git diff --check -- lanes/gitoxide
```

Results:

- Changed PHP lint passed.
- Focused credential family passed: `4 test files, 530 assertions, 0 failures`.
- Example smoke exited `0`.
- Full Gitoxide lane passed: `40 test files, 10752 assertions, 0 failures`.
- `phpPass` moves `10719 -> 10752` (`+33`).
- `git diff --check -- lanes/gitoxide` passed.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`GitUrl`, `CredentialContext`, and `CredentialCascade` implementations.

## Non-Overlap

This does not repeat the accepted signed boolean, carriage-return byte,
diagnostics, root path, platform helper, smart HTTP proxy credential, transport,
reference, pack, tree/pathspec, or URL/refspec generated diagnostic authority
clusters. The patch is limited to credential helper context URL-boundary and
URL-last cascade merge parity.
