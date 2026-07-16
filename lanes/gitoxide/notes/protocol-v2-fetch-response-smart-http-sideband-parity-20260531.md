# Protocol v2 fetch smart HTTP sideband parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260531T233706Z`

Accepted base: `6dcdbdf63680f15710c0b63f093637566ee78a22`

## Upstream source truth

- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Exact fixture bytes: `gix-transport/tests/fixtures/v2/http-fetch.response`
- Test reference: `gix-transport/tests/client/git.rs::http_v2::fetch`
- Parser references: `gix-protocol/src/fetch/response/blocking_io.rs` and `gix-packetline/src/blocking_io/sidebands.rs`

The upstream fixture wraps a protocol-v2 `packfile` response in a smart HTTP
`application/x-git-upload-pack-result` response. The body has `Content-Length:
1135`, contains five channel-2 progress packets, channel-1 pack bytes, and a
sideband-stripped pack trailer `150a1045f04dc0fc2dbf72313699fda696bf4126`.

## Native behavior covered

- `FetchResponse::fromSmartHttpUploadPackResult()` validates the smart HTTP
  response status, upload-pack result content type, and content length before
  parsing the packet-line body.
- The existing protocol-v2 fetch response parser receives the HTTP body and
  keeps channel-1 pack bytes separate from channel-2 progress messages.
- The upstream fixture is lane-local and self-contained so PHP tests no longer
  depend on the upstream cache.
- The WordPress fetch response example now includes a smart HTTP upload-pack
  result smoke path for deployment fetches without invoking `git`.

## Evidence

- PHP lint: `php -l lanes/gitoxide/src/FetchResponse.php && php -l lanes/gitoxide/tests/FetchResponseTest.php && php -l lanes/gitoxide/fixtures/upstream-gix-transport-v2-http-fetch-response.php && php -l lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php && php -l lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` -> no syntax errors
- Focused test: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 276 assertions, 0 failures`
- Full lane test: `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 6295 assertions, 0 failures`
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php` -> exit 0
- Diff check: `git diff --check -- lanes/gitoxide` -> clean
- Expected mapped movement: `1667 / 2886` to `1668 / 2886`
- Expected PHP pass movement: `6268` to `6295`

## Dependency closure

No new support component is needed. This reuses the native PHP packet-line,
sideband, fetch-response, and remote-progress parsing code and adds only a
bounded smart HTTP response unwrap for upload-pack result bytes. No live
network, SSH, credential, or provider tests were used.

## Non-overlap

This does not repeat the accepted `gix-protocol/tests/fixtures/v2/fetch.response`
fixture, sideband-all synthetic response, clone/ref-in-want sideband fixtures,
fetch-section sideband fixtures, smart HTTP receive-pack redirect/cookie
behavior, protocol-v2 ls-refs service announcements, send-pack status parsing,
or SSH receive-pack transport boundaries. It maps the distinct upstream
`gix-transport/tests/fixtures/v2/http-fetch.response` upload-pack result body.

## Root status

Root harness not run - isolated Gitoxide micro-slice.
