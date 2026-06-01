# Send-Pack Receive-Status Object-Format Prefix Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T124835Z`

## Source truth

- Upstream Gitoxide source truth remains the local cache at `/home/claude/port-libs/.upstream-cache/gitoxide`, specifically `gix-transport/tests/client/git.rs::push_v1_simulated` and `gix-transport/tests/fixtures/v1/push.response` for nested sideband receive-status handling.
- Git send-pack parity source is `git/git` `send-pack.c::receive_status()` and `hex.c::parse_oid_hex()`: report-status-v2 `old-oid` and `new-oid` parse exactly the negotiated repository hash length and do not require a non-hex delimiter after that fixed prefix.

## Native PHP delta

- `PushResponse::fromReportStatusPacketLines()` and `PushResponse::fromSidebandPacketLines()` now accept an optional object-format context.
- `ReceivePackClient` derives `object-format` from the negotiated push features and passes it into status parsing.
- `PushRefStatus` uses fixed SHA-1/SHA-256 prefix parsing only when an explicit negotiated format is supplied, preserving the existing conservative standalone parser for ambiguous any-hash input.
- The WordPress push-status fixture now documents a proc-receive status response whose SHA-1 object IDs are followed by additional hex diagnostic bytes.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php`
  - `1 test files, 335 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - `1 test files, 1182 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 9353 assertions, 0 failures`

Root harness status: `not run - isolated micro-slice`.

Dependency closure: no new support component is needed. This reuses the existing native packet-line, sideband, receive-pack client, and push-status parser surfaces; no shared support-library row or activation gate is proposed.

Non-overlap: this does not repeat recent empty unpack status, valueless option, malformed option, object-prefix non-hex diagnostic, delimiter/response-end, unpack-only, unrequested-option, or multi-report no-refname receive-status slices. It narrows the remaining client-side object-format context boundary for report-status-v2 object options.
