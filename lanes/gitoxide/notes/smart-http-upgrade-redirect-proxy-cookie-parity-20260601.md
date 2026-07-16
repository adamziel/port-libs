# Smart HTTP upgrade redirect proxy/cookie parity

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260601T053137Z`
Base accepted HEAD: `f21524404044b11f3b8895597ad5fc6ac48001c6`

## Source Truth

- Upstream `gix/src/repository/config/transport.rs` selects the configured
  HTTP proxy option before the request enters the transport. `gitoxide.https.proxy`
  is selected only for an original HTTPS remote URL, while HTTP remotes use
  `http.proxy`/`gitoxide.http.proxy` or `gitoxide.http.allProxy`.
- Upstream `gix-transport/src/client/blocking_io/http/curl/remote.rs` then
  applies that selected proxy option set to libcurl. Redirects update the
  effective request URL inside the curl-backed request loop, but do not
  reselect `httpsProxy` merely because a safe initial HTTP discovery request
  upgrades to HTTPS.
- Redirect cookies still need to be scoped against the effective redirected URL
  and preserved into the receive-pack POST.

## Implemented Behavior

- `SmartHttpReceivePackTransport` now separates effective request URLs from
  proxy fallback selection URLs. TLS, cookie, and no-proxy host checks continue
  to use the effective redirected URL; proxy fallback selection uses the
  original logical receive-pack request URL.
- A WordPress-style `http://git.example.test/wp-content.git` discovery request
  with only `httpsProxy` configured remains direct after a safe same-authority
  redirect to HTTPS, matching upstream's original-request proxy selection.
- The redirect-issued `Set-Cookie: upgrade_gate=opened; Path=/` is replayed on
  both the upgraded discovery retry and the generated receive-pack POST.
- The WordPress proxy credential fixture/example now exposes this boundary and
  confirms the proxy helper is not consulted for the upgraded HTTP-origin
  request.

## Evidence

Red-first after adding the focused assertion and before the source fix:

```text
php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php
1 test files, 727 assertions, 1 failures
Failure: expected upgrade proxy helper calls 0, actual 2
```

Passing focused and lane verification after the implementation:

```text
php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php
1 test files, 814 assertions, 0 failures

php tools/run-tests.php lanes/gitoxide/tests
40 test files, 7610 assertions, 0 failures
```

Additional required checks are recorded in the final handoff: PHP lint for
changed PHP files, example smoke, JSON parse, and `git diff --check -- lanes/gitoxide`.

Expected dashboard movement after integration:

- `phpPass`: `7591 -> 7610`
- Conservative mapped coverage: `1748 / 2886 -> 1749 / 2886`

## Non-Overlap

This does not repeat accepted HTTPS/allProxy fallback selection, port-qualified
noProxy literal-token behavior, trailing-dot noProxy or Domain-cookie behavior,
CIDR/IPv6 noProxy bypasses, wildcard noProxy handling, 304 discovery
proxy-cookie preservation, redirect proxy credential reuse, SOCKS/TLS proxy
handling, content-type/header proxy handling, or safe POST redirect body
replay. It is bounded to the proxy fallback selection source used during safe
HTTP-to-HTTPS smart HTTP redirects.

## Dependency Closure

No new support component is needed. The slice reuses the existing smart HTTP
requester boundary, redirect resolver, cookie jar, proxy/noProxy matcher,
proxy credential helper callbacks, receive-pack packet builders, and WordPress
proxy fixture/example.
