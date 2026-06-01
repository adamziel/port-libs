# Send-Pack Stream Delimiter Terminator Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T113934Z`

## Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  defines the send-pack transport boundary where sideband channel 1 carries
  nested receive-status packet lines and channel 2 carries progress.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/bufread_ext.rs`
  and the async equivalent map `PacketLineRef::Delimiter` to a non-data
  `MessageKind::Delimiter`.
- Git `send-pack.c::receive_status()` at
  `2be606a3bd1c916fcc14435556a807c6f5b5ce14` exits the receive-status loop
  when packet reading returns any non-normal packet after valid status data.

## Behavior Added

- `StreamReceivePackTransport::readResponse()` now treats an outer `0001`
  delimiter as a response terminator, preserving the delimiter bytes for
  `PushResponse` to parse.
- Advertisement reads still continue across delimiters, keeping the existing
  receive-pack advertisement behavior unchanged.
- Focused coverage now proves both direct report-status responses and
  sidebanded receive-status responses can terminate at `0001` when read
  through the stream-backed receive-pack client.
- The WordPress receive-pack stream fixture now uses an outer delimiter
  terminator so the local example exercises the same path.

## Evidence

- `php -l lanes/gitoxide/src/StreamReceivePackTransport.php`
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php`
- `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php`
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`:
  `1 test files, 1128 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php`: exit `0`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 9064 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This reuses the native stream
receive-pack transport, packet-line status parser, sideband accumulator,
receive-pack client flow, and WordPress stream fixture. It does not shell out
to Git, run live provider tests, read credentials, or require a shared support
activation gate.

## Non-Overlap

This extends the accepted delimiter receive-status parser behavior to the
stream transport read boundary. It does not repeat direct parser
response-end/delimiter handling, empty unpack status, unpack-only expected-ref
fallback, empty progress sidebands, fatal sideband errors, packet-line maximum
bounds, report-status-v2 object options, valueless options, proc-receive
multi-report filtering, smart HTTP redirect/cookie/proxy behavior, SSH
receive-pack boundaries, protocol-v2 fetch sideband parsing, reference
transactions, pack/index behavior, pathspec work, or object database
integrity checks.
