# Protocol v2 fetch empty error sideband handler parity

Micro-slice: `gitoxide-protocol-v2-fetch-response-sideband-parity-20260601T113210Z`

Base accepted HEAD: `643db6cd7b3a41ab8e3a67fdda031493c589be65`

## Source Truth

- `GitoxideLabs/gitoxide` commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `gix-packetline/src/blocking_io/sidebands.rs`: `WithSidebands::with_progress_handler()` decodes sideband channel 3 as error text and invokes the caller progress handler while channel 1 carries pack bytes.
- `gix-protocol/src/remote_progress.rs`: empty error text is treated as a sideband-all keepalive by the progress renderer instead of becoming user-visible progress failure text.
- `gix-protocol/src/fetch/function.rs`: protocol v2 fetch installs the sideband progress handler both for `sideband-all` response parsing and for ordinary sideband pack body reads.

## Implementation

- `FetchResponse` now distinguishes empty channel-3 sideband keepalives from stored error messages: it keeps `errorMessages()` empty but still passes the empty error boundary to the caller handler.
- The same behavior is covered for ordinary `packfile` sidebands and `sideband-all` section parsing before pack data.
- A handler returning `false` for the empty error boundary aborts with `fetch response: interrupted by user`, matching the existing sideband handler cancellation path.
- The WordPress protocol-v2 fetch fixture/example now exposes the empty error boundary as a handler event while preserving pack bytes and the blank-error suppression behavior.

## Verification

- Baseline focused check before this slice: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 391 assertions, 0 failures`.
- Focused check after implementation: `php tools/run-tests.php lanes/gitoxide/tests/FetchResponseTest.php` -> `1 test files, 404 assertions, 0 failures`.
- Full Gitoxide lane check after implementation: `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 9048 assertions, 0 failures`.

Additional verification for this handoff:

- `php -l lanes/gitoxide/src/FetchResponse.php`
- `php -l lanes/gitoxide/tests/FetchResponseTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-protocol-v2-fetch-response.php`
- `php -l lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`
- `php lanes/gitoxide/examples/wordpress-protocol-v2-fetch-response.php`
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
- `git diff --check -- lanes/gitoxide`

Focused assertion delta: `+13` over the accepted `FetchResponseTest.php` baseline. Conservative mapped coverage remains `1787 / 2886`; this deepens the already represented protocol v2 fetch sideband response cluster.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP packet-line, sideband, fetch-response, smart HTTP body, and remote-progress parser components. No live network, credential store, provider, SSH process, or full upstream Cargo workspace runner was used.

## Non-overlap

This does not repeat accepted protocol v2 fetch section fixture parsing, sideband-all section/pack parsing, raw upload-pack `ERR` handling, response-end/delimiter stopped-at handling, packet-line bounds, truncated sideband rejection, SHA-256 fetch IDs, remote-progress u32 parsing, smart HTTP upload-pack validation, send-pack status parsing, smart HTTP/SSH transport, pack/index, or reference transaction slices. It is bounded to caller-visible empty channel-3 sideband handler behavior while preserving blank-error suppression.

Root harness status: `not run - isolated micro-slice`.
