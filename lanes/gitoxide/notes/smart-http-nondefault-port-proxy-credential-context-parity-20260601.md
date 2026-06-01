# Smart HTTP non-default-port proxy credential context parity

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260601T180726Z`

## Source Truth

- Upstream `gix-credentials/src/protocol/context/mod.rs` appends a URL port to
  helper `host` context only when it differs from the scheme default.
- Upstream `gix-credentials/tests/helper/cascade.rs` pins
  `Action::get_for_url("https://example.com:8080/path/git/")` as
  `host=example.com:8080`.
- Upstream `gix/src/config/snapshot/credential_helpers.rs` documents the
  default-port elision deviation used before helper invocation.

## Port Delta

- `SmartHttpReceivePackTransport` now builds proxy credential helper/store
  request hosts with the same default-port elision rule: `https://host` and
  `https://host:443` remain `host`, while `https://host:8443` becomes
  `host:8443`.
- The focused receive-pack transport test adds a proxied
  `https://git.example.test:8443/wp-content.git` flow that preserves helper
  request-host port context, stores the same context, keeps proxy
  authorization out of origin headers, and carries discovery cookies into the
  receive-pack POST.
- The WordPress proxy fixture/example exposes the same non-default-port
  deployment path.

## Verification

- `php -l lanes/gitoxide/src/SmartHttpReceivePackTransport.php`
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-smart-http-proxy-credentials.php`
- `php -l lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed: `1 test files, 1345 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/*.php` passed:
  `40 test files, 10403 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  exited 0.

## Non-Overlap

This does not repeat accepted smart HTTP port-qualified `noProxy` literal-token
behavior, default proxy-port credential URL canonicalization, proxy fallback,
redirect credential reuse, 304 proxy-cookie handling, trailing-dot noProxy,
IPv6 noProxy, protocol-relative redirect, or cookie Path/Domain scope slices.
It is limited to non-default repository port preservation in proxy credential
helper/store context.

## Dependency Closure

No new support component is needed. The patch reuses the existing native smart
HTTP requester boundary, proxy option normalization, credential-helper
callbacks, cookie jar, and WordPress proxy fixture/example; it does not invoke
live remotes, provider credentials, external Git helpers, or the upstream Cargo
workspace.
