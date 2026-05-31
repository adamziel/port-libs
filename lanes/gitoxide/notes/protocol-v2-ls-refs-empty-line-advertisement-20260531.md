# Protocol V2 ls-refs Empty-Line Advertisement Parity

Micro-slice: `gitoxide-protocol-v2-ls-refs-advertisement-parity-20260531T103908Z`

Accepted base: `f9d9e6312c63dfc0751eedbcf238e9e6c2d6e7da`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-protocol/src/handshake/refs/blocking_io.rs::from_v2_refs()` passes each protocol v2 ls-refs line directly into `refs::shared::parse_v2()`.
- `gix-protocol/src/handshake/refs/shared.rs::parse_v2()` returns `MalformedV2RefLine` when a line does not contain both object-id/unborn and ref path tokens.

## Native PHP Delta

- `LsRefsCommand::parseV2Refs()` no longer trims and drops blank response rows.
- `LsRefsCommand::parseV2PacketLines()` now preserves packet-line line boundaries, keeps accepted no-newline packet handling, and rejects empty packet payloads or explicit blank rows before flush.
- The WordPress protocol-v2 ls-refs fixture/example now records that a blank advertisement row is rejected before a deployment tool trusts advertised refs.

## Verification

- Red-first focused test before implementation: `php tools/run-tests.php lanes/gitoxide/tests/ProtocolV2Test.php` => `1 test files, 75 assertions, 2 failures`.
- Focused test after implementation: `php tools/run-tests.php lanes/gitoxide/tests/ProtocolV2Test.php` => `1 test files, 82 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests` => `39 test files, 4267 assertions, 0 failures`.
- PHP lint: `php -l lanes/gitoxide/src/LsRefsCommand.php`, `php -l lanes/gitoxide/tests/ProtocolV2Test.php`, `php -l lanes/gitoxide/fixtures/wordpress-protocol-v2-ls-refs.php`, and `php -l lanes/gitoxide/examples/wordpress-protocol-v2-ls-refs.php` => no syntax errors.
- Example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-ls-refs.php` => exit `0`.
- Lane diff check: `git diff --check -- lanes/gitoxide` => exit `0`.
- JSON check: `lanes/gitoxide/lane-status.json` and `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` decode successfully.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP packet-line reader and ls-refs remote-reference parser.

## Non-Overlap

This does not repeat accepted protocol-v2 capability packet parsing, request-byte framing, SHA-256 ref IDs, refspec prefix expansion, smart HTTP upload-pack service announcement parsing, fetch sideband fixtures, send-pack receive-status parsing, SSH auth-boundary work, or packed/loose ref work. It is bounded to upstream malformed-line handling for blank protocol v2 `ls-refs` advertisement rows.

Root harness status: `not run - isolated micro-slice`.
