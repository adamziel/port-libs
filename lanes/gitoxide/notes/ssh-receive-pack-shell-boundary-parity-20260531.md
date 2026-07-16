# SSH Receive-Pack Shell Boundary Parity

Micro-slice: `gitoxide-ssh-receive-pack-boundary-parity-20260531T203804Z`

Accepted base: `91b42fe7029899440b4b46f38b3f903a76f3b322`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-command/src/lib.rs`
  implements `command_may_be_shell_script()`, which enables shell use when a
  command string contains shell-script bytes such as whitespace.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/program_kind.rs`
  calls `command_may_be_shell_script()` for SSH invocations and clears
  `use_shell` when `disallow_shell` is set.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/tests.rs`
  covers ordinary `ssh` remaining direct and `disallow_shell_is_honored` for
  an `echo hi` command string.

Behavior added:

- `SshReceivePackTransport::connectorContext()` now exposes `useShell` for
  caller-owned SSH adapters.
- Plain command names such as `ssh`, `plink`, and `simple` remain direct
  (`useShell=false`).
- Shell-looking `sshCommand` strings such as `echo hi` set `useShell=true`.
- `disallowShell=true` forces `useShell=false` while preserving the existing
  argv, environment, credential-context, and live-authentication boundaries.
- The WordPress receive-pack fixture/example records the shell and
  disallow-shell connector context behavior without launching SSH.

Verification evidence:

- Red-first probe before implementation:
  `SshReceivePackTransport::connectorContext(..., ['sshCommand' => 'echo hi'])`
  returned no `useShell` key.
- Baseline focused test before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed `1 test files, 566 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed `1 test files, 581 assertions, 0 failures`.
- Full Gitoxide lane after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests` passed `39 test files, 5429
  assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- Full upstream Cargo workspace tests were not run for this isolated slice.

Dependency closure:

- No new support component is needed. This reuses the existing native SSH
  receive-pack URL parser, caller-injected connector context, command
  classification, and receive-pack stream implementation. Live SSH process
  spawning, authentication, and channel setup remain caller-owned and were not
  invoked.

Non-overlap:

- This does not repeat accepted SSH protocol-v2/auth connector context, legacy
  `ssh+git` / `git+ssh` URL admission, scp-like home-path normalization,
  option-looking host safety, ProgramKind port/argument planning, smart HTTP
  redirect/proxy behavior, git-daemon service requests, stream timeouts, or
  send-pack status parsing. It is bounded to Gitoxide's shell-use and
  disallow-shell process preparation boundary for SSH receive-pack connector
  contexts.
