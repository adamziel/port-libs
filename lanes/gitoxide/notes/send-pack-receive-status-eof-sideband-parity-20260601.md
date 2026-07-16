# Send-Pack Receive-Status EOF Sideband Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T232438Z`

## Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  reads the send-pack response through sideband progress plus nested
  receive-status packet lines.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/fixtures/v1/push.response`
  ends after the channel-1 nested `0000` report-status flush and does not carry
  a separate outer sideband flush packet.

## Behavior Added

- `PushResponse::fromSidebandPacketLines()` now accepts EOF as the outer
  sideband terminator when packet data has already been read and the nested
  channel-1 receive-status stream is complete.
- Incomplete nested status data remains rejected by the existing
  `missing report-status flush packet` guard.
- `StreamReceivePackTransport::readResponse()` now accepts EOF only after a
  complete response packet boundary. Partial response packet headers and
  payloads still fail through the existing strict read path.
- The WordPress protocol-v1 push-response fixture/example records the same
  EOF-terminated sideband status shape for deployment push diagnostics.

## Evidence

- Red-first probe before implementation:
  `PushResponse::fromSidebandPacketLines($packet("\\x02...") . $packet("\\x01" . $packet("unpack ok\\n")) . $packet("\\x01" . $packet("ok refs/heads/main\\n")) . $packet("\\x01" . "0000"))`
  failed with `push response: missing sideband flush packet`.
- `php -l lanes/gitoxide/src/PushResponse.php && php -l lanes/gitoxide/src/StreamReceivePackTransport.php && php -l lanes/gitoxide/tests/PushResponseTest.php && php -l lanes/gitoxide/tests/ReceivePackTransportTest.php && php -l lanes/gitoxide/fixtures/wordpress-protocol-v1-push-response.php && php -l lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`:
  all passed.
- `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php lanes/gitoxide/tests/ReceivePackTransportTest.php`:
  `2 test files, 1769 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`:
  exited `0`.
- Full Gitoxide lane check was attempted with
  `php tools/run-tests.php lanes/gitoxide/tests`, but unrelated temp-file tests
  failed with `Disk quota exceeded`; root harness was not run.

## Dependency Closure

No new support component is needed. This reuses the native packet-line reader,
stream receive-pack transport, sideband receive-status parser, and existing
WordPress push-response fixture/example. No live provider, credential store,
Git binary, Cargo workspace, or shared support-library activation gate was
used.

## Non-Overlap

This extends the send-pack receive-status cluster without repeating
object-format prefix parsing, malformed object-option tolerance, valueless or
empty refname options, expected-ref filtering, missing/unpack-only fallback,
unrequested-option rejection, duplicate rejection reports, sideband fatality,
empty progress keepalives, delimiter/response-end terminators, smart HTTP
redirect/cookie/proxy behavior, SSH receive-pack boundaries, protocol-v2 fetch
sideband parsing, pack/index behavior, reference transactions, or object
database integrity checks. It is bounded to EOF termination after complete
sideband receive-status packets.

Root harness status: `not run - isolated micro-slice`.
