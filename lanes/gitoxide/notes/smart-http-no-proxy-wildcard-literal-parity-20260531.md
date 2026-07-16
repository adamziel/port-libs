# Smart HTTP No-Proxy Wildcard Literal Parity - 2026-05-31

Micro-slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260531T214002Z`

Accepted base: `c7ca7ac45660966d9eecf84ad3eaffea66691f11`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/repository/config/transport.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/tests/gix/repository/config/transport_options.rs`

Implemented behavior:

- Gitoxide forwards configured `gitoxide.http.noProxy` and environment-derived
  `no_proxy` strings to curl/libcurl without rewriting leading asterisks.
- `SmartHttpReceivePackTransport` now treats only a bare `*` noProxy token as
  a bypass-all wildcard. Literal asterisk-bearing host tokens such as
  `*.bypass.test` and `*bypass.test` no longer bypass `git.bypass.test`.
- Literal asterisk host tokens still use the configured proxy and proxy
  credential helper, while bare `*` bypasses proxy options and helper calls.
- The bare-star bypass path still preserves origin `Set-Cookie` state from
  smart HTTP discovery into the receive-pack POST.
- Local curl parity probes confirmed that `curl --noproxy '*'` bypasses all
  hosts, while `curl --noproxy '*.example.test'` and `'*example.test'` do not
  bypass `git.example.test`.

Verification:

- Red-first check after adding wildcard-literal assertions and before the
  source fix: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  failed with `1 test files, 538 assertions, 1 failures`.
- Focused check after the source fix:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 619 assertions, 0 failures`.
- Full lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `39 test files, 5803 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  exited `0`.

Dependency closure:

- No new support component is needed. This reuses the existing native smart
  HTTP requester boundary, proxy option normalization, proxy credential helper
  callbacks, cookie jar, packet/request builders, and WordPress proxy
  credential fixture/example.

Non-overlap:

- This does not repeat accepted smart HTTP redirect cookie scoping,
  followRedirects, SOCKS proxy handshakes, HTTPS-through-SOCKS, cleartext URL
  credential rejection, optional service-announcement handling, proxy
  credential store/erase lifecycle, username-only proxy helper activation,
  proxy credential/header scoping, or the CIDR noProxy bypass slice. It is
  bounded to curl/libcurl wildcard semantics for noProxy tokens and cookie
  preservation on the bypassed receive-pack flow.
