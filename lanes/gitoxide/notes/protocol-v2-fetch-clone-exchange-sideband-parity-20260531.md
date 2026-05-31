# Protocol v2 fetch clone exchange sideband parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260531T202831Z`

Accepted base: `29362e0d6ada0a9ddb4cefdc699cee6add41d488`

## Upstream source truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Exact fixture bytes: `gix-protocol/tests/fixtures/v2/clone.response`
- Full exchange target: `gix-transport/tests/client/git.rs::handshake_v2_and_request`
- Fetch delegate target: `gix-protocol/tests/protocol/fetch/v2.rs::{ls_remote,clone_abort_prep}`
- Sideband source reference: `gix-packetline/src/blocking_io/sidebands.rs`

The fixture is a persistent protocol-v2 upload-pack exchange: a capability
advertisement, a separate `ls-refs` advertisement, and then a sidebanded
`packfile` response. The sideband-stripped pack is 876 bytes and ends in
trailer `150a1045f04dc0fc2dbf72313699fda696bf4126`.

## Native behavior covered

- Added `ProtocolV2FetchExchange::fromPacketLines()` to split one v2 upload-pack
  exchange into capability, ls-refs, and fetch response packet-line messages.
- Reused `ProtocolCapabilities`, `LsRefsCommand`, and `FetchResponse` so each
  stage is parsed by the existing native Gitoxide port.
- Verified the exact upstream `v2/clone.response` capabilities, HEAD symbolic
  ref, direct branch ref, sideband channel-1 pack bytes, and channel-2 progress.
- Extended the WordPress protocol-v2 fetch response fixture/example to parse a
  clone-style exchange before handing pack bytes to object import.

## Evidence

- Baseline focused check before this slice: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 199 assertions, 0 failures`
- Focused check after this slice: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 221 assertions, 0 failures`
- Full Gitoxide lane check: `php tools/run-tests.php lanes/gitoxide/tests` -> `39 test files, 5436 assertions, 0 failures`
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` -> exit 0
- PHP lint: `php -l` on `ProtocolV2FetchExchange.php`, `FetchResponseTest.php`, `upstream-gix-protocol-v2-fetch-sideband.php`, the WordPress fetch-response fixture, and the WordPress fetch-response example -> no syntax errors
- JSON validation: `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` decode successfully
- Diff check: `git diff --check -- lanes/gitoxide` -> clean
- Expected mapped movement: `1632 / 2886` to `1633 / 2886`
- Expected PHP pass movement: `5414` to `5436`

## Dependency closure

No new support component is needed. This slice reuses the existing native PHP
packet-line, protocol-v2 capability, ls-refs, fetch-response, sideband, and
remote-progress parsing code. No live network, SSH, credential store, provider,
or full upstream Cargo workspace test was used.

## Non-overlap

This does not repeat the accepted exact `fetch.response` suffixless ACK fixture,
clone-only sideband fixtures, ref-in-want fixture, section sideband fixture set,
raw upload-pack ERR handling, packet-line bounds, send-pack status parsing,
smart HTTP receive-pack transport, or protocol-v2 ls-refs parser slices. It is
bounded to the exact upstream `v2/clone.response` persistent upload-pack
exchange where capability and ls-refs packet messages precede the sidebanded
fetch response.

## Root status

Root harness not run - isolated Gitoxide micro-slice.
