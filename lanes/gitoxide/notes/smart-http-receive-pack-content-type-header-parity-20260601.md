# Smart HTTP Receive-Pack Content-Type Header Parity - 2026-06-01

Slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T042237Z`

Base accepted HEAD: `a514b852099d3beeb2c984bc19ea1aeae13dfd49`

## Upstream Source Truth

- Pinned upstream Gitoxide cache commit:
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-transport/src/client/blocking_io/http/mod.rs::check_content_type()`
  scans every `Content-Type` response header line and accepts the response
  when any value matches the expected `application/x-<service>-<kind>` media
  type.

## Native Delta

- `SmartHttpReceivePackTransport::assertContentType()` now validates all
  same-name `Content-Type` values returned by a native requester instead of
  checking only the normalized last value.
- Existing media-type comparison behavior is preserved: response parameters
  such as `; charset=utf-8` are ignored before comparison.
- The WordPress receive-pack transport fixture/example now records that a
  deployment push over smart HTTP succeeds when an intermediary duplicates the
  `Content-Type` header and one value is still the receive-pack media type.

## Evidence

- Red-first local probe before implementation:
  `SmartHttpReceivePackTransport` rejected a duplicate advertisement
  `Content-Type` array whose first value was
  `application/x-git-receive-pack-advertisement` and last value was
  `text/plain`, raising `smart HTTP receive-pack advertisement returned
  unexpected content type text/plain`.
- Baseline focused suite before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 777 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 784 assertions, 0 failures`.
- Full Gitoxide lane after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files,
  7333 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited
  `0`.

Full upstream Cargo workspace tests were not run for this isolated
micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native smart HTTP
requester boundary, response header normalization helpers, packet-line
receive-pack client, and fixture/example harness. No live network, external
Git binary, SSH process, provider credential, or shared support activation
gate is needed.

## Non-Overlap

This does not repeat accepted smart HTTP redirect, cookie, proxy, TLS, SOCKS,
cleartext-credential, URL normalization, SSH receive-pack invocation, git
daemon request, send-pack status parsing, sideband, packet-line bounds, or
reference transaction work. It is bounded to the upstream `gix-transport`
duplicate `Content-Type` receive-pack response-header acceptance boundary.
