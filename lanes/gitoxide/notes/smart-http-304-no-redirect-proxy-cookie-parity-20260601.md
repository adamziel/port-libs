# Smart HTTP 304 no-redirect proxy/cookie parity

- Worker slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260601T141406Z`
  on accepted base `15456a3269b6c0a5196b0c477a7392b691bbc201`.
- Source truth: upstream Gitoxide
  `gix-transport/src/client/blocking_io/http/curl/remote.rs`,
  `gix-transport/src/client/blocking_io/http/mod.rs`, and
  `gix/src/repository/config/transport.rs` at pinned cache source.
- Upstream behavior mapped: curl HTTP discovery accepts 3xx statuses only when
  redirect following allows them. With `FollowRedirects::None`, a `304 Not
  Modified` is outside the accepted end status range. The proxy credential
  helper records the last status and erases credentials after non-200 results
  instead of storing them.
- Native PHP delta: `SmartHttpReceivePackTransport` now derives advertisement
  accepted statuses from `followRedirects`. Default redirect mode keeps the
  accepted `200`/`304` discovery path, but `followRedirects => false` accepts
  only `200`, rejects `304` as a smart HTTP status error, erases proxy helper
  credentials, and stops before preserving cookies or replaying a POST.
- WordPress fixture/example delta: `wordpress-smart-http-proxy-credentials.php`
  now exposes both boundaries: accepted default-mode `304` discovery cookies
  still flow into receive-pack POSTs, while no-redirect `304` rejects and
  records proxy-helper erase context without leaking origin headers or using a
  live credential store.
- Red-first evidence: before the implementation change, the focused
  `ReceivePackTransportTest.php` failed with `1 test files, 1196 assertions,
  1 failures` because no-redirect `304` was accepted and parsed as an empty
  advertisement.
- Verification: after implementation and fixture/example updates,
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed `1 test files, 1252 assertions, 0 failures`; changed PHP lint passed;
  `php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  exited `0`; `git diff --check -- lanes/gitoxide` passed.
- Expected status movement: focused assertion coverage moves `1232 -> 1252`;
  lane `phpPass` moves `9689 -> 9709`. Mapped denominator coverage remains
  conservatively unchanged at `1799 / 2886` because this slice does not add a
  manifest inventory row.
- Dependency closure: no new support component is needed. The slice reuses the
  existing native PHP smart HTTP transport, proxy option normalization,
  credential-helper callback boundary, cookie jar, packet/request builders, and
  lane-local fixture/example. It does not read credential stores, inspect
  provider config, use network remotes, invoke Git, or require a shared
  support-library activation gate.
- Non-overlap: this does not repeat accepted smart HTTP proxy fallback,
  port-qualified noProxy, bare-star noProxy, IPv6 noProxy, protocol-relative
  redirect, HTTP-to-HTTPS upgrade redirect, domain/trailing-dot cookie scope,
  root-scoped non-slash Path cookie, default redirect-mode `304` cookie
  preservation, or URL/refspec credential/ref matching work. It is bounded to
  the upstream no-redirect `304` status boundary and proxy-helper erase action.
