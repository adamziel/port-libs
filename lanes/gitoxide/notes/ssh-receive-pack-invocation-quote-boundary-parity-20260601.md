# SSH Receive-Pack Invocation Quote Boundary Parity - 2026-06-01

Slice: `gitoxide-ssh-receive-pack-boundary-parity-20260601T031711Z`

Base accepted HEAD: `979af834e747cf8f00cd2e2b7b981cbc1e549c29`

## Upstream Source Truth

- Pinned upstream `gix-transport/src/client/blocking_io/file.rs` appends the
  remote service name and shell-quoted repository path to the SSH invocation
  after the SSH client arguments.
- Pinned upstream `gix-transport/src/client/blocking_io/ssh/mod.rs` expands
  SSH home paths before building the process-backed transport.
- Pinned upstream `gix-quote/src/single.rs` wraps repository paths in single
  quotes and escapes both single quotes and `!` bytes for Bourne-shell
  handoff.

## Native Delta

- `SshReceivePackTransport::connectorContext()` now exposes
  `remoteService`, `remotePathArgument`, and `sshInvocationArguments` so
  caller-owned SSH adapters can reproduce the upstream remote invocation
  boundary without launching SSH from the PHP port.
- `receivePackCommand()` now shares the same remote service constant as the
  context plan.
- Repository path quoting now follows `gix-quote::single()` for `!` bytes in
  addition to existing single-quote escaping.
- The WordPress receive-pack fixture/example records the protocol-v2
  invocation argv and a path containing both `!` and `'` bytes.

## Verification

- Baseline before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 757 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 764 assertions, 0 failures`.
- Full Gitoxide lane after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files,
  7141 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited
  `0`.

Full upstream Cargo workspace tests were not run for this isolated
micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native receive-pack URL
parser, command quoting, connector context, and stream transport. Live SSH
process spawning, authentication, provider credentials, and channel setup
remain caller-owned and were not invoked.

## Non-Overlap

This does not repeat accepted SSH URL delimiter safety, legacy SSH scheme
admission, scp-like home-path normalization, ProgramKind port planning,
feature-probe, shell-use/disallow-shell exposure, protocol-v2 environment
sanitization, option-host safety, nonnumeric-port parsing, smart HTTP
redirect/proxy behavior, git-daemon request bytes, send-pack status parsing,
or receive-pack packet-line bounds. It is bounded to the upstream remote
service argv and `gix-quote::single()` repository-path boundary for SSH
receive-pack connector contexts.
