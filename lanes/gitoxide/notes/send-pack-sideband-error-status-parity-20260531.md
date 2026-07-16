# Send-pack sideband error/status parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260531T203013Z`

## Source truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-packetline/src/blocking_io/sidebands.rs` decodes sideband channel 3 as `BandRef::Error` and delivers it to the progress handler with `is_error=true`.
- `gix-transport/tests/client/git.rs::push_v1_simulated` and `gix-transport/tests/fixtures/v1/push.response` define the nested send-pack status boundary where channel 1 carries packet-line encoded `unpack`/ref status bytes while channel 2 carries progress.
- Git pack protocol side-band semantics reserve channel 3 for fatal remote errors, so a non-empty channel-3 packet must not be downgraded into a merely unsuccessful parsed push response when channel 1 already carried valid status bytes.

## Implementation

- `PushResponse::fromSidebandPacketLines()` now treats any non-empty channel-3 packet as a fatal receive-pack sideband error before returning a parsed response, even if channel 1 already carried a complete nested report-status stream.
- Empty channel-3 packets are ignored as keepalives, matching the existing lane-local fetch sideband behavior and avoiding a false `errorMessages=[""]` unsuccessful response.
- `ReceivePackClient::send()` inherits the fatal-after-status behavior at the send-pack command boundary.
- The WordPress protocol-v1 push-response fixture/example now records both a fatal hook message after status bytes and an empty error-sideband keepalive.

## Verification

- Red-first probe before the fix:
  - `php -r 'require "tools/bootstrap.php"; ... PushResponse::fromSidebandPacketLines(...channel 1 status..., ...channel 3 fatal...) ...'`
  - Result: `NO_THROW failure`
- Focused tests:
  - `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - Result: `2 test files, 723 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `39 test files, 5421 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/gitoxide/src/PushResponse.php`
  - `php -l lanes/gitoxide/tests/PushResponseTest.php`
  - `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - `php -l lanes/gitoxide/fixtures/wordpress-protocol-v1-push-response.php`
  - `php -l lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`
  - Result: no syntax errors
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`
  - Result: exit 0
- Diff check:
  - `git diff --check -- lanes/gitoxide`
  - Result: exit 0

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP packet-line reader, sideband decoder, `PushResponse`, `ReceivePackClient`, and WordPress push-response fixture/example. No live network, SSH process, credential store, provider, or full upstream Cargo workspace runner was used.

## Non-overlap

This extends the accepted send-pack fatal-no-status, receive-status compatibility, expected-ref filtering, missing-ref handling, unrequested-option rejection, linefeed trimming, packet-bound, sideband push-response client, proc-receive, smart HTTP, SSH, and protocol-v2 fetch sideband clusters. It is bounded to sideband channel-3 fatality after otherwise valid send-pack receive-status bytes plus empty channel-3 keepalive tolerance.
