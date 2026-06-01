## Slice

`gitoxide-receive-pack-transport-boundary-parity-20260601T164445Z`

## Source truth

- Upstream `gix-protocol/src/handshake/refs/shared.rs` parses advertised ref
  object ids through `gix_hash::ObjectId::from_hex()`, so the ref-id width is
  the negotiated repository object format instead of a hard-coded SHA-1 width.
- Upstream receive-pack/send-pack traffic carries the `object-format=<hash>`
  capability in the first update packet, as exercised by the local
  `gix-transport/tests/client/git.rs` receive-pack request fixtures.

## Native delta

- `ReceivePackAdvertisement` now records the advertised object format and
  validates SHA-256 advertised refs when `object-format=sha256` is present.
- `SendPackSession` selects the advertised object format and passes it into
  `PushCommand`.
- `PushCommand` and `PushUpdate` now emit SHA-256 create/update/delete command
  lines, including the 64-byte zero object id for delete-only requests.
- SHA-256 non-delete requests are guarded before pack generation because the
  current `PackBuilder` still produces SHA-1 pack/index checksums.

## Verification

- Before edits: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php lanes/gitoxide/tests/SendPackSessionTest.php lanes/gitoxide/tests/PushCommandTest.php` -> `3 test files, 1383 assertions, 0 failures`.
- After edits: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php lanes/gitoxide/tests/SendPackSessionTest.php lanes/gitoxide/tests/PushCommandTest.php` -> `3 test files, 1426 assertions, 0 failures`.
- Full lane: `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 10236 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` -> exit 0.
- PHP lint: changed source, test, fixture, and example PHP files all passed `php -l`.
- Whitespace: `git diff --check -- lanes/gitoxide` -> exit 0.

## Non-overlap

This does not touch accepted smart HTTP proxy/cookie/redirect/status/header
boundaries, SSH argv/auth handling, git-daemon service request construction,
report-status parsing, or pack/index/object-database behavior. The only new
transport boundary is receive-pack object-format negotiation and request-line
emission for SHA-256 delete/update commands.

## Dependency closure

No new support component is required. The slice reuses the existing packet-line,
receive-pack transport, send-pack session, and push-response components. The
remaining dependency is an explicit follow-up: implement SHA-256 pack/index
generation before allowing non-delete SHA-256 pushes with pack data.
