# Smart HTTP Proxy Fallback Cookie Parity

Slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260601T020728Z`
Base: `dc8bb5cac377111467dc403c9b9c75704db62cd4`

## Source Truth

- Upstream `gix/src/repository/config/transport.rs` at pinned Gitoxide commit
  `87433ed33eee9ba974111d20b854f6acb07cd4a6` selects a primary proxy before
  `gitoxide.https.proxy` for HTTPS URLs and before `gitoxide.http.allProxy`.
- The same upstream behavior treats a configured empty primary proxy as a real
  "configured but disabled" value, so lower fallback proxies are not selected.
- Upstream `gix-transport/src/client/blocking_io/http/mod.rs` and
  `curl/remote.rs` apply selected proxy settings with proxy auth, no-proxy
  matching, redirect handling, and credential storage/erase boundaries.

## Native Behavior Added

- `SmartHttpReceivePackTransport` now accepts `httpsProxy` and `allProxy`
  transport options in addition to `proxy`.
- Proxy selection follows upstream precedence:
  `proxy` if configured, else `httpsProxy` for HTTPS requests, else `allProxy`.
- A configured empty `proxy` disables lower fallback proxies.
- Receive-pack discovery and POST preserve cookies through HTTPS-specific and
  all-proxy fallback paths, and proxy authorization remains transport-only.

## Evidence

Red-first before the source change:

```text
php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php
1 test files, 660 assertions, 1 failures
Failure: smart HTTP receive-pack HTTP option is not supported
```

Passing verification after the implementation:

```text
php -l lanes/gitoxide/src/SmartHttpReceivePackTransport.php
No syntax errors detected

php -l lanes/gitoxide/tests/ReceivePackTransportTest.php
No syntax errors detected

php -l lanes/gitoxide/fixtures/wordpress-smart-http-proxy-credentials.php
No syntax errors detected

php -l lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php
No syntax errors detected

php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php
1 test files, 750 assertions, 0 failures

php tools/run-tests.php lanes/gitoxide/tests
40 test files, 6818 assertions, 0 failures

php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php
exit 0

git diff --check -- lanes/gitoxide
exit 0
```

Expected dashboard movement after integration:

- `phpPass`: `6789 -> 6818`
- Conservative mapped coverage: `1699 / 2886 -> 1700 / 2886`

## Dependency Closure

No new support component is needed. The slice reuses the existing injected HTTP
requester, proxy credential helper/store/erase hooks, cookie jar, and
`noProxy` matcher.

## Non-Overlap

This does not repeat accepted smart HTTP port-qualified `noProxy`, trailing-dot
`noProxy`, CIDR `noProxy`, username-only proxy URL, redirect proxy credential
reuse, or 304 proxy-cookie preservation work. It adds the upstream fallback
selection layer around the already accepted proxy/cookie machinery.
