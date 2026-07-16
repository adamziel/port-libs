# Protocol V2 ls-refs Service Announcement Parity

Micro-slice: `gitoxide-protocol-v2-ls-refs-advertisement-parity-20260531T100602Z`

## Upstream Source Truth

- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/mod.rs`.
- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/fixtures/v2/http-handshake-service-announced.response`.
- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/fixtures/v2/http-lsrefs.response`.

## Native PHP Delta

- `ProtocolCapabilities::fromV2PacketLines()` now accepts the optional smart HTTP `# service=git-upload-pack` packet-line prelude that Gitoxide strips before parsing protocol v2 capabilities.
- The parser validates an optional expected service name and rejects mismatched service announcements.
- The parser requires the service announcement to be followed by its flush packet before the `version 2` capability advertisement.
- The WordPress protocol-v2 `ls-refs` fixture now includes the upload-pack service announcement so the example smoke covers this path.

## Verification

- Red-first focused check before implementation: `php tools/run-tests.php lanes/gitoxide/tests/ProtocolV2Test.php` => `1 test files, 65 assertions, 1 failures`, failing with `Expected 'version X', got # service=git-upload-pack`.
- Focused test after implementation: `php tools/run-tests.php lanes/gitoxide/tests/ProtocolV2Test.php` => `1 test files, 71 assertions, 0 failures`.
- Full Gitoxide lane after implementation: `php tools/run-tests.php lanes/gitoxide/tests` => `38 test files, 4032 assertions, 0 failures`.
- Example smoke after fixture update: `php lanes/gitoxide/examples/wordpress-protocol-v2-ls-refs.php` => exit `0`.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local packet-line parser and capability model.

## Non-Overlap

This does not repeat accepted protocol-v2 request byte framing, packet-line `ls-refs` response parsing, SHA-256 ref IDs, refspec prefix expansion, fetch sideband parsing, send-pack receive-status parsing, smart HTTP receive-pack redirect/cookie handling, or SSH auth-boundary work. It is bounded to Gitoxide's optional smart HTTP upload-pack service announcement before protocol v2 capability advertisements.
