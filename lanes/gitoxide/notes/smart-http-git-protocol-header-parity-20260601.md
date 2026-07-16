# Smart HTTP Git-Protocol header parity

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T085822Z`

## Source truth

- Upstream `gix-transport/src/client/blocking_io/http/mod.rs` builds the discovery `Git-Protocol` header when the desired protocol is not v1 or extra parameters are present. For v2 plus extras, it sends `version=2:<extra>...` (lines 350-370 in the pinned cache).
- The same transport captures the negotiated `actual_protocol` from the advertisement after optional service-announcement handling (lines 382-409).
- POST requests only send `Git-Protocol: version=<actual>` when the negotiated protocol is not v1; discovery extra parameters are not repeated on the request (lines 433-438).

## Native movement

- Added an explicit smart HTTP `protocolVersion` option to `SmartHttpReceivePackTransport`.
- Discovery headers now include `version=2` before colon-joined extra parameters for desired protocol v2, while explicit protocol v1 sends only extra parameters and legacy callers without `protocolVersion` keep the previous extra-parameter-only behavior.
- Request headers now use the negotiated advertisement version and omit `Git-Protocol` after a v1 downgrade instead of replaying discovery parameters.
- The WordPress receive-pack fixture and example expose the v2 discovery/request boundary and the v2-desired-to-v1-downgrade POST omission path.

## Verification

- Baseline before this slice: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` -> `1 test files, 958 assertions, 0 failures`.
- Focused syntax: `php -l lanes/gitoxide/src/SmartHttpReceivePackTransport.php`; `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`; `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php`; `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php`.
- Focused behavior: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` -> `1 test files, 981 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 8378 assertions, 0 failures`.
- Example smoke: the updated `wordpress-receive-pack-transport.php` summary reports `version=2:session-id:object-format=sha1` for discovery, `version=2` for v2 POST, `null` for downgrade POST, and preserved POST bodies.
- Whitespace gate: `git diff --check -- lanes/gitoxide` passed.

## Non-overlap

This avoids accepted smart HTTP proxy/cookie/noProxy/redirect/content-type packet-line work, SSH receive-pack argv/auth boundaries, git-daemon extra-parameter handling, send-pack status parsing, and request packet-line boundary slices. The patch is limited to smart HTTP receive-pack `Git-Protocol` header scoping across discovery and negotiated POST requests.

## Dependency closure

No new support component is needed. The slice reuses the existing injected HTTP requester, packet-line advertisement parser, receive-pack fixture, and lane-local PHP test harness. Full upstream Cargo workspace execution remains out of scope for this isolated worker.

Next task: continue non-overlapping transport/protocol work around live HTTP error boundaries, SSH auth contexts, send-pack receive-status variants, or protocol v2 request/response parsing that is not already represented by accepted slices.
