# Smart HTTP IPv6 Literal noProxy Cookie/Proxy Parity

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260601T042437Z`

Source truth:
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/repository/config/transport.rs` forwards `http.noProxy`/environment no-proxy strings into transport options without rejecting literal host entries.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs` applies those options through curl's `handle.noproxy(&no_proxy)` before issuing smart HTTP requests.

Behavior added:
- Verified bracketed IPv6 literal repository hosts such as `https://[2001:db8::10]/wp-content.git` match a bracketed `noProxy` literal.
- The bypassed flow does not consult proxy credential helpers and passes empty proxy HTTP options for both receive-pack discovery and POST.
- Origin `Set-Cookie` from discovery is still scoped to the IPv6 literal host and sent on receive-pack POST.
- The WordPress proxy fixture/example now exposes the same IPv6 literal bypass summary.

Non-overlap:
- This is distinct from accepted CIDR noProxy, bare-star noProxy, wildcard-literal proxy use, trailing-dot host/domain-cookie bypass, port-qualified literal-token proxy use, proxy fallback, redirect credential reuse, SOCKS/TLS, and 304 cookie/proxy slices.

Verification:
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php` passed.
- `php -l lanes/gitoxide/fixtures/wordpress-smart-http-proxy-credentials.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` passed: 1 file / 788 assertions / 0 failures.
- `php tools/run-tests.php lanes/gitoxide/tests` passed: 40 files / 7337 assertions / 0 failures.
- `php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php` exited 0.
- `git diff --check -- lanes/gitoxide` passed.

Dependency closure:
- No new support component is needed. The existing native smart HTTP transport noProxy matcher, cookie jar, proxy credential action, and WordPress fixture infrastructure cover this parity slice.
