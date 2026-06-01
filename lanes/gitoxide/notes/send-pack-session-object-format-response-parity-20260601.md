# Send-Pack Session Object-Format Receive-Status Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T151712Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` keep the Gitoxide
  transport boundary where sideband channel 1 carries nested receive-status
  packet lines after a send-pack request.
- Git `send-pack.c::receive_status()` at source
  `866e6a391f466baeeb98bc585845ea638322c04b` parses report-status-v2
  `old-oid` and `new-oid` through the negotiated repository hash algorithm
  and accepts the fixed-length object prefix before any hook diagnostic suffix.
- Git `gitprotocol-pack` documents report-status-v2 object names as using the
  negotiated `object-format` capability.

## Delta

- `SendPackSession::parseSidebandResponse()` and
  `SendPackSession::parseReportStatusResponse()` now pass the session command's
  negotiated object-format into `PushResponse`.
- Added focused session coverage for direct and sideband report-status-v2
  responses where a SHA-1 `old-oid` / `new-oid` is followed by extra hex hook
  diagnostics. The generic parser rejects that ambiguous shape, so the session
  wrapper must carry the negotiated SHA-1 context just like
  `ReceivePackClient`.
- Updated the WordPress send-pack session fixture/example to expose the parsed
  receive-status old/new object prefixes for deployment status diagnostics.

## Verification

- `php -l lanes/gitoxide/src/SendPackSession.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/SendPackSessionTest.php`: no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-send-pack-session.php`: no syntax
  errors.
- `php -l lanes/gitoxide/examples/wordpress-send-pack-session.php`: no syntax
  errors.
- `php tools/run-tests.php lanes/gitoxide/tests/SendPackSessionTest.php`: `1
  test files, 60 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php
  lanes/gitoxide/tests/ReceivePackTransportTest.php`: `2 test files, 1641
  assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-send-pack-session.php`: exited `0`.
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"),
  true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`:
  `lane-status json ok`.
- `git diff --check -- lanes/gitoxide`: passed.

## Dependency Closure

No new support component is needed. This reuses the existing native packet-line
reader, sideband receive-status parser, `PushResponse`, and
`SendPackSession` command feature negotiation. No live service, credential
store, provider, SSH process, or Cargo workspace runner was used.

## Non-Overlap

This does not repeat the accepted lower-level `PushResponse` object-format
prefix parser, empty unpack status, valueless option, malformed option,
object-prefix diagnostic, delimiter/response-end terminator, unpack-only,
unrequested-option, multi-report, smart HTTP/SSH receive-pack, or protocol-v2
fetch sideband slices. It is bounded to the higher-level send-pack session
wrapper preserving negotiated object-format context while parsing receive-status
responses.
