# Smart HTTP Default-Port Proxy Credential Context Parity

Worker slice: `gitoxide-smart-http-transport-cookie-proxy-parity-20260601T063917Z`
Base: `0beac79ced31a7dd838adc7168578a431ce35af2`

## Source Truth

- Upstream `gix/src/repository/config/transport.rs` normalizes proxy config by
  prefixing `http://` when no scheme is present, but it does not synthesize a
  default port into the selected proxy URL.
- Upstream `gix-credentials/src/protocol/context/mod.rs` destructures helper
  URL context and filters default ports out of the helper `host` field.
- Upstream `gix-transport/src/client/blocking_io/http/curl/remote.rs` still
  gives curl a concrete selected proxy option for the HTTP request.

## Native Delta

- `SmartHttpReceivePackTransport::normalizeProxy()` now keeps separate
  connection, visible proxy URL, and credential-helper URL authorities.
- Connection streams still include concrete default ports such as
  `tcp://proxy.example.test:80`.
- Proxy URLs omit synthesized default ports when the configured proxy omitted
  them.
- Credential-helper/store/erase proxy URLs omit default ports even when the
  configured proxy spelled the default explicitly, matching Gitoxide helper
  context behavior.
- The WordPress smart HTTP proxy fixture/example now proves helper/store
  context canonicalization while preserving `Set-Cookie` from discovery into
  receive-pack POST through the proxy.

## Verification

- `php -l lanes/gitoxide/src/SmartHttpReceivePackTransport.php`
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-smart-http-proxy-credentials.php`
- `php -l lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed `1 test files, 870 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-smart-http-proxy-credentials.php`
  exited `0`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 7882
  assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide` passed.

## Non-Overlap And Dependency Closure

This slice does not repeat accepted smart HTTP proxy lifecycle, username-only
proxy helper, noProxy CIDR/trailing-dot/IPv6/port-literal, 304 cookie, upgrade
redirect, content-type/proxy header, or SOCKS/TLS behavior. It covers only the
default-port canonicalization boundary for proxy helper context plus the
receive-pack cookie path through that proxy.

No new support component is required; the existing bounded smart HTTP requester,
proxy helper callback, and WordPress fixture/example support are reused.
