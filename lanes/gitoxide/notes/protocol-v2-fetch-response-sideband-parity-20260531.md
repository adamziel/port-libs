# Protocol v2 fetch.response sideband parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260531T151720Z`

Accepted base: `4678f572bda3b3437f0480f42476c787d671be75`

## Upstream source truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Exact fixture bytes: `gix-protocol/tests/fixtures/v2/fetch.response`
- Parser parity target: `gix-protocol/tests/protocol/fetch/response.rs::v2::from_line_reader::fetch_acks_and_pack`
- Sideband source references: `gix-packetline/src/blocking_io/sidebands.rs` and `gix-protocol/src/remote_progress.rs`

The fixture combines suffixless `ACK <oid>` lines, a delimiter before the `packfile` section, fragmented channel-2 progress packets, and channel-1 pack data. The sideband-stripped pack is 5360 bytes and ends in trailer `7699593d62b1a50764036e7ebb48f4e3ed111268`.

## Native behavior covered

- Protocol v2 suffixless ACK lines are accepted as common acknowledgements before the `ready` acknowledgement.
- The delimiter packet separates acknowledgements from the `packfile` section without becoming response data.
- Sideband channel 1 is concatenated into pack bytes while channel 2 fragmented progress remains separate.
- Gitoxide remote-progress-like records are still parsed from the channel-2 text fragments.
- The WordPress protocol-v2 fetch response example now includes a suffixless ACK response with sideband progress and pack data preservation.

## Evidence

- Baseline focused check before the slice: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 148 assertions, 0 failures`
- Focused check after the slice: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 174 assertions, 0 failures`
- Full Gitoxide lane check: `php tools/run-tests.php lanes/gitoxide/tests` -> `39 test files, 4766 assertions, 0 failures`
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` -> exit 0
- PHP lint: `php -l` on `upstream-gix-protocol-v2-fetch-response-sideband.php`, `FetchResponseTest.php`, `wordpress-protocol-v2-fetch-response.php`, and the touched example -> no syntax errors
- JSON validation: `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` decode successfully
- Diff check: `git diff --check -- lanes/gitoxide` -> clean
- Expected mapped movement: `1584 / 2886` to `1585 / 2886`
- Expected PHP pass movement: `4740` to `4766`

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP packet-line, fetch-response, sideband, and remote-progress parsing code. No live network, SSH, credential, or provider tests were used.

## Non-overlap

This is not the accepted sideband-all synthetic response, upstream clone-only sideband fixture, fetch-section sideband fixture set, packet-bound guard, raw ERR response, send-pack status parsing, protocol v2 ls-refs, tree merge, or sparse/pathspec coverage. It maps the exact upstream `v2/fetch.response` fetch-acks-and-pack fixture that was not previously covered.

## Root status

Root harness not run - isolated Gitoxide micro-slice.
