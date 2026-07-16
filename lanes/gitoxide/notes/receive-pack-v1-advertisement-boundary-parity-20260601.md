Gitoxide receive-pack v1 advertisement boundary parity, 2026-06-01

- Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T234030Z`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- Upstream files consulted:
  `gix-protocol/src/handshake/refs/shared.rs`,
  `gix-protocol/src/handshake/refs/blocking_io.rs`, and
  `gix-protocol/tests/protocol/handshake.rs`.

Behavior delta:

- `ReceivePackAdvertisement::fromV1PacketLines()` now skips the all-zero
  `capabilities^{}` dummy ref used by empty repositories while preserving the
  first-packet capability parse.
- V1 `shallow <oid>` lines are recorded as receive-pack shallow boundaries via
  `ReceivePackAdvertisement::shallowUpdates()`.
- `symref=<name>:<target>` capabilities are folded into matching advertised
  refs, including upstream's `(null)` target boundary as a direct ref.
- Peeled tag advertisements fold a direct tag line plus the following
  `refs/tags/name^{}` line into one peeled `RemoteRef`, and malformed ordering
  is rejected.
- `ReceivePackClient` can handshake an empty repository advertisement and send
  the first `create` command without serializing `capabilities^{}` as a ref.
- The WordPress receive-pack fixture/example now includes an in-memory initial
  branch push over `StreamReceivePackTransport`.

Red-first evidence:

- Before the parser change,
  `ReceivePackAdvertisement::fromV1PacketLines()` rejected
  `0000000000000000000000000000000000000000 capabilities^{}\0report-status`
  with `InvalidArgumentException: Reference name contains an invalid byte`.

Verification:

- `php -l lanes/gitoxide/src/ReceivePackAdvertisement.php && php -l lanes/gitoxide/tests/SendPackSessionTest.php && php -l lanes/gitoxide/tests/ReceivePackTransportTest.php && php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php && php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php`
  passed with no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/SendPackSessionTest.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed: `2 test files, 1485 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- `php tools/run-tests.php lanes/gitoxide/tests` passed:
  `41 test files, 10885 assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide` passed.
- Root harness was not run: isolated micro-slice.

Dependency closure:

- No new support component is needed. The slice reuses the existing packet-line
  parser, protocol capability model, `RemoteRef`, `FetchShallowUpdate`,
  `ReceivePackClient`, and native stream transport. No live Git, SSH, HTTP,
  credentials, network service, or full Cargo workspace execution was used.

Non-overlap:

- This does not repeat protocol v2 fetch stop-packet parity, smart HTTP
  redirect/cookie behavior, SSH URL/auth parsing, send-pack status parsing,
  pack/object database behavior, reference transactions, or the Cargo workspace
  evidence blocker. The scope is receive-pack v1 advertisement boundary parity.
