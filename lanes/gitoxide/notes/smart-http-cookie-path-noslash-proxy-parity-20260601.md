# Smart HTTP Cookie Path No-Slash Proxy Parity

- Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260601T114051Z`
- Base accepted HEAD: `177ad9d0c94fb78133f1ea6eeb906ed590161382`
- Upstream source truth: Gitoxide's blocking smart HTTP transport uses the curl backend for proxy/no-proxy and proxy-auth handling (`gix-transport/src/client/blocking_io/http/mod.rs`, `gix-transport/src/client/blocking_io/http/curl/remote.rs`), and repository transport config forwards curl-style proxy settings from `gix/src/repository/config/transport.rs`.
- Local curl probe: `Set-Cookie: empty_path=sent; Path=` was rejected, while `Set-Cookie: noslash=sent; Path=wp-content.git` was stored with root path `/` and replayed to later receive-pack paths. A dot-segment Path remained rejected for the normalized receive-pack path.

Implemented behavior:

- `SmartHttpReceivePackTransport` now treats a non-empty cookie `Path` that does not start with `/` as root-scoped `/`, matching curl/libcurl behavior used by upstream Gitoxide.
- Empty and control-byte `Path` attributes remain quarantined.
- Receive-pack proxy tests and WordPress fixtures now prove the root-scoped cookie is carried through an authenticated proxy POST while the empty-Path cookie is omitted.

Evidence:

- Red-first: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` failed before the source fix with `1 test files, 1012 assertions, 1 failures`; the proxy POST cookie header was `NULL` instead of `wp_root=wide`.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` passed with `1 test files, 1138 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files, 9074 assertions, 0 failures`.
- Examples: `php lanes/gitoxide/examples/wordpress-smart-http-follow-redirects.php` and `php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php` exited 0.

Non-overlap:

- This slice does not cover trailing-dot Domain cookies, CIDR/IPv6/noProxy parsing, port-qualified noProxy, 304 discovery cookies, redirect limits, safe POST redirect eligibility, SOCKS/TLS handshakes, content-type/status validation, Git-Protocol negotiation, or SSH transport behavior.

Dependency closure:

- No new support component is needed. The existing smart HTTP requester hook, proxy option plumbing, cookie jar, receive-pack client, and WordPress fixtures were sufficient.
