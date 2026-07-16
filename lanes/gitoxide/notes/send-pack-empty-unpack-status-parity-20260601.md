# Send-Pack Empty Unpack Status Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T041624Z`

## Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` keep the send-pack
  response boundary where sideband channel 1 carries nested receive-status
  packet-line data.
- Git `v2.54.0` `send-pack.c::receive_unpack_status()` accepts any packet
  line that starts with `unpack `, then treats the bytes after that prefix as
  the remote unpack result. Only the exact result `ok` is successful; an empty
  result is a remote unpack failure, not a malformed packet stream.

## Behavior

- `PushResponse` now preserves an empty unpack result from `unpack ` as
  `unpackStatus() === ''` and `unpackOk() === false` for direct and sidebanded
  receive-status streams.
- The parser still requires the initial `unpack ` prefix and still rejects a
  missing unpack line, malformed packet-line framing, and invalid ref status
  lines.
- The WordPress protocol-v1 push-response fixture/example now records a
  deployment response where the receiver reports an empty unpack failure and a
  concrete branch rejection message.

## Evidence

- Red-first focused probe before implementation:
  `php -r 'require "tools/bootstrap.php"; ... PushResponse::fromReportStatusPacketLines($packet("unpack \n") ...) ...'`
  printed `InvalidArgumentException: push response: unpack status cannot be empty`.
- PHP lint passed for the changed PHP files:
  `lanes/gitoxide/src/PushResponse.php`,
  `lanes/gitoxide/tests/PushResponseTest.php`,
  `lanes/gitoxide/fixtures/wordpress-protocol-v1-push-response.php`, and
  `lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`.
- Focused parser check:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php`
  passed with `1 test files, 245 assertions, 0 failures`.
- Adjacent send-pack/receive-pack gate:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php lanes/gitoxide/tests/SendPackSessionTest.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `3 test files, 1071 assertions, 0 failures`.
- Full Gitoxide lane check:
  `php tools/run-tests.php lanes/gitoxide/tests`
  passed with `40 test files, 7316 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`
  exited `0`.
- JSON metadata validation:
  `php -r 'foreach (["lanes/gitoxide/lane-status.json", "lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'`
  reported both files `OK`.
- Diff whitespace check:
  `git diff --check -- lanes/gitoxide`
  exited `0`.

## Dependency Closure

No new support component is needed. This reuses the native packet-line reader,
sideband accumulator, `PushResponse`, `PushRefStatus`, and the existing
WordPress protocol-v1 push-response fixture/example. It does not shell out to
Git, run live provider tests, read credentials, or require a shared support
activation gate.

## Non-Overlap

This extends the accepted send-pack receive-status cluster without repeating
report-status-v2 object-option parsing, valueless option handling, empty
`ng <ref> ` rejection text, unrequested-option rejection, expected-ref
filtering, unpack-only fallbacks, response-end or delimiter terminators,
packet-line bounds, fatal sideband errors, smart HTTP redirect/cookie/proxy
behavior, SSH receive-pack boundaries, or protocol-v2 fetch sideband parsing.
It is bounded to the `unpack ` status line where the remote sends no unpack
result bytes after the required prefix.
