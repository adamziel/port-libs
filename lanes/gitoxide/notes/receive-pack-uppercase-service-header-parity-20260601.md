# Receive-Pack Uppercase Service Header Parity - 2026-06-01

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T180146Z`

Accepted base: `eaf4be71f1e017e55035a4ef726a86e2aab9b7cc`

Upstream source truth:

- `GitoxideLabs/gitoxide` upstream cache under `.upstream-cache/gitoxide`
- `gix-packetline/src/decode.rs`: `hex_prefix()` decodes the four length bytes through `faster_hex::hex_decode` instead of a lowercase-only regular expression.
- `gix-transport/src/client/blocking_io/http/mod.rs`: smart HTTP receive-pack discovery treats the `# service=git-receive-pack` announcement as an optional pkt-line envelope before the advertisement payload.

Implemented behavior:

- `SmartHttpReceivePackTransport` now accepts uppercase hexadecimal pkt-line length prefixes while stripping the optional smart HTTP receive-pack service announcement.
- The direct receive-pack smart HTTP test now exercises a `# service=git-receive-pack` announcement encoded with an uppercase length prefix before posting the generated request.
- The WordPress receive-pack fixture/example records the same uppercase service-header boundary before deployment ref discovery.

Verification:

- Baseline before patch:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed, `1 test files, 1317 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed, `1 test files, 1323 assertions, 0 failures`.
- Syntax checks passed:
  `php -l lanes/gitoxide/src/SmartHttpReceivePackTransport.php`,
  `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php`, and
  `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php`
  exited 0.
- JSON validity check for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` printed `json ok`.
- Whitespace check:
  `git diff --check -- lanes/gitoxide` exited 0.

Status delta:

- Focused receive-pack transport evidence moves from `1317` to `1323` assertions, a `+6` assertion delta.
- Conservative mapped Gitoxide coverage moves from `1809 / 2886` to `1810 / 2886` for smart HTTP receive-pack uppercase service-announcement length parsing.

Dependency closure:

- No new support component is needed. This reuses the existing native PHP smart HTTP transport, packet-line parsing helpers, receive-pack client, and WordPress receive-pack fixture. No live network, credential store, SSH process, provider, or full upstream Cargo workspace runner was used.

Non-overlap and exclusions:

- This does not repeat receive-pack packet-line max-length rejection, smart HTTP optional service-announcement absence, content-type/header/proxy/noProxy/cookie/redirect behavior, SSH receive-pack boundaries, git-daemon receive-pack service requests, or send-pack status parsing.
- Root harness status: `not run - isolated micro-slice`.
