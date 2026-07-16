# Smart HTTP Protocol-Relative Redirect Cookie/Proxy Parity

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260601T125812Z`

Base accepted HEAD: `27cf721c25e91c9dcac0b599677df25582e922d2`

## Source Truth

- Upstream `gix-transport/src/client/blocking_io/http/curl/remote.rs` delegates redirect following to curl and then reads `effective_url()` after the request completes.
- Upstream `gix-transport/src/client/blocking_io/http/redirect.rs` validates the effective URL with `base_url()`: redirects must retain the same authority, or only upgrade `http` to `https` on the same host/default port, and the resulting path must still target the requested smart HTTP endpoint.
- Curl resolves a network-path Location such as `//git.example.test/repo.git/info/refs?...` using the current request scheme before `redirect::base_url()` sees the effective absolute URL.

## Native Delta

- `SmartHttpReceivePackTransport::resolveRedirectUrl()` now treats protocol-relative redirect locations as current-scheme absolute URLs instead of appending them as an absolute path on the old authority.
- Same-host protocol-relative discovery redirects now update the effective repository base and carry redirect cookies into the subsequent `git-receive-pack` POST.
- Cross-host protocol-relative redirects are still rejected by the existing redirect base guard.
- The WordPress proxy fixture now covers a proxied receive-pack flow where a protocol-relative discovery redirect preserves scoped cookies and proxy credential-helper behavior without leaking proxy authorization to origin headers.

## Verification

- `php -l lanes/gitoxide/src/SmartHttpReceivePackTransport.php`
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-smart-http-proxy-credentials.php`
- `php -l lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - Result: `1 test files, 1211 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  - Result: exit `0`
- `git diff --check -- lanes/gitoxide`
  - Result: passed

## Non-Overlap

This slice does not repeat accepted smart HTTP cookie Path whitespace/no-slash behavior, same-port `http` to `https` upgrade redirects, dot-segment relative redirects, redirect limits, 304 proxy-cookie discovery, noProxy CIDR/IPv6/port-literal matching, HTTPS/allProxy fallback, or credential helper lifecycle coverage. It covers the previously separate network-path redirect resolution branch used before the existing redirect base validation.

## Dependency Closure

No new support component is needed. The slice reuses the existing smart HTTP requester injection, proxy option normalization, credential-helper callbacks, redirect validation, and cookie jar helpers.
