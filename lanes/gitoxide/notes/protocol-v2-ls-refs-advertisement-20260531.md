# Protocol V2 ls-refs Advertisement Parity

Micro-slice: `gitoxide-protocol-v2-ls-refs-advertisement-parity-20260531T082857Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Inspected `gix-transport/src/client/capabilities.rs`, `gix-transport/src/client/blocking_io/traits.rs`, `gix-transport/src/client/blocking_io/request.rs`, `gix-transport/tests/client/git.rs`, `gix-transport/tests/fixtures/v2/http-handshake.response`, `gix-transport/tests/fixtures/v2/http-no-newlines-handshake.response`, `gix-protocol/src/ls_refs.rs`, and `gix-protocol/tests/protocol/fetch/v2.rs`.

Implemented behavior:

- `ProtocolCapabilities::fromV2PacketLines()` parses packet-line v2 capability advertisements, appends missing newlines like Gitoxide's handshake reader, rejects malformed packet lengths, and reports server `ERR` packet lines before capability parsing.
- `LsRefsCommand::requestBytes()` emits upstream-shaped protocol v2 request bytes: `command=ls-refs`, optional `agent=...`, delimiter, text-line arguments, and final flush.
- `LsRefsCommand::parseV2PacketLines()` parses packet-line `ls-refs` ref advertisements, handles missing line endings, and reports remote `ERR` packets before typed ref parsing.

Verification:

- Before focused test: `php tools/run-tests.php lanes/gitoxide/tests/ProtocolV2Test.php` => `1 test files, 34 assertions, 0 failures`.
- After focused test: `php tools/run-tests.php lanes/gitoxide/tests/ProtocolV2Test.php` => `1 test files, 52 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests` => `32 test files, 2981 assertions, 0 failures`.
- Changed example smoke: `php lanes/gitoxide/examples/wordpress-protocol-v2-ls-refs.php` => exit `0`.

Dependency closure:

- No new support component is needed. The slice reuses bounded native PHP packet-line parsing and encoding inside the Gitoxide lane.

Non-overlap:

- Avoids the accepted receive-pack transport, smart HTTP redirect/cookie, SSH auth-boundary, object database, pack/MIDX, packed-ref, and recursive tree-merge clusters. This patch only extends protocol v2 `ls-refs` upload-pack advertisement/request parity.
