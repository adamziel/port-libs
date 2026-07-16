# Smart HTTP Receive-Pack Redirect Limit Parity

Slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T063447Z`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/redirect.rs`
- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/reqwest/remote.rs`
- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`

The bounded parity target is the smart HTTP discovery redirect boundary. Upstream HTTP transport keeps redirects constrained by host/scheme checks and aligns reqwest with curl's default 50 redirect limit. The native receive-pack transport now follows 50 same-authority discovery redirects and rejects the 51st redirect before accepting an advertisement.

## Native Delta

- `SmartHttpReceivePackTransport::MAX_INITIAL_REDIRECTS` moved from 10 to 50.
- `ReceivePackTransportTest.php` adds a red/green boundary test that accepts the 50th redirected discovery endpoint and rejects the 51st redirect.
- The WordPress smart HTTP redirect fixture/example exposes the accepted and overflow boundary counts.

## Verification

- Red-first before the source change: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` failed with `smart HTTP receive-pack GET request returned an unexpected redirect status 302` at the old 10-redirect cap.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` => `1 test files, 858 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` => `40 test files, 7815 assertions, 0 failures`.
- `php -l` on changed PHP files passed.
- `php lanes/gitoxide/examples/wordpress-smart-http-follow-redirects.php` exited 0.
- `git diff --check -- lanes/gitoxide` exited 0.

## Dependency Closure

No new support component is needed. The slice reuses the existing injected smart HTTP requester and receive-pack advertisement parser; no live network, credential, SSH, or provider tests were run.

## Non-Overlap

This does not repeat accepted proxy credential, noProxy, cookie-scope, POST redirect replay, receive-pack content-type, packet-line, SSH, or git-daemon receive-pack slices. It only changes the upstream redirect-count boundary for smart HTTP receive-pack discovery.
