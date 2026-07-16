# Smart HTTP Receive-Pack SSL Version Boundary Parity

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T140401Z`

Accepted base: `279720d63150e90242798bff73dcddaa9362b3e5`

## Source Truth

- Upstream Gitoxide cache: `/home/claude/port-libs/.upstream-cache/gitoxide`
  at `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-transport/src/client/blocking_io/http/mod.rs` defines
  `Options::ssl_version: Option<SslVersionRangeInclusive>` plus
  `SslVersionRangeInclusive::min_max()`, which orders reversed min/max bounds.
- `gix-transport/src/client/blocking_io/http/curl/remote.rs` applies
  `ssl_version.min_max()` to either a single `handle.ssl_version()` call or a
  min/max `handle.ssl_min_max_version()` call.

## Red-First Probe

Before the patch, this current-base probe rejected the upstream-shaped option:

```sh
php -r 'require "tools/bootstrap.php"; new PortLibs\Gitoxide\SmartHttpReceivePackTransport("https://example.test/repo.git", null, [], 30.0, [], ["sslVersion" => "tlsv1.2"]); echo "accepted\n";'
```

Result: `InvalidArgumentException: smart HTTP receive-pack HTTP option is not supported`.

## Native Delta

- `SmartHttpReceivePackTransport` now accepts `sslVersion` as a string or
  min/max range, normalizes Gitoxide/libcurl-shaped aliases, rejects malformed
  or control-byte values, and orders reversed ranges like upstream `min_max()`.
- Normalized SSL version state is forwarded to injected requesters so focused
  tests can inspect the transport boundary without live network access.
- Native PHP HTTPS stream and SOCKS TLS negotiation now derive
  `crypto_method` from the normalized range, preserving the default TLS method
  when no SSL range is configured.
- The WordPress receive-pack fixture/example now records the normalized
  `tlsv1.2` to `tlsv1.3` range and the PHP stream crypto method.

## Verification

- `php -l lanes/gitoxide/src/SmartHttpReceivePackTransport.php` passed.
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php` passed.
- `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed: `1 test files, 1244 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- `php tools/run-tests.php lanes/gitoxide/tests` passed:
  `40 test files, 9675 assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide` passed.

The root harness and full upstream Cargo workspace were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded smart
HTTP receive-pack request option normalization, caller-injected requester
boundary, PHP stream context handling, and SOCKS connector TLS boundary. No
live provider credentials, external service, shared support-library row, or
activation gate is required.

## Non-Overlap

This slice does not repeat accepted receive-pack packet-line/request encoding,
content-type/header/proxy/cookie redirect behavior, noProxy behavior,
SSH/git-daemon receive-pack boundaries, protocol-v2 fetch/send-pack parsing,
loose-object integrity, or reference transaction slices. It maps only the
smart HTTP `sslVersion` transport option boundary and its TLS range handling.
