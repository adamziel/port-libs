# SSH Receive-Pack Feature-Probe Boundary Parity

Micro-slice: `gitoxide-ssh-receive-pack-boundary-parity-20260531T235059Z`

Accepted base: `b2a0ea9050b31220cefa69c10914986b6a41bc76`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/mod.rs`
  runs an SSH client feature check with `-G <host>` when no explicit
  `ProgramKind` was provided and the command basename cannot be classified.
- The same file builds that probe from `url.host_as_argument()`, so a host
  whose bytes begin with `-` is rejected for the probe even when a later
  explicit `ProgramKind` invocation could safely pass `user@-host`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/program_kind.rs`
  keeps `disallow_shell` honored while preparing shell-looking command strings.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/tests.rs`
  covers unknown command fallback, shell-looking `ssh` commands, explicit
  `ProgramKind`, and option-looking host/user argument safety.

Behavior added:

- `SshReceivePackTransport::connectorContext()` now exposes a non-executing
  `sshFeatureProbe` plan for unknown SSH command strings when no explicit
  `programKind` is supplied.
- The probe records the caller-owned command, upstream-style `['-G', host]`
  arguments, and the shell-use decision after `disallowShell` is applied.
- Option-looking hosts are rejected during unknown-command feature-probe
  planning, matching Gitoxide's `host_as_argument()` boundary.
- Explicit `programKind` values bypass the feature probe and keep accepted
  `user@-host` argv behavior for SSH and simple adapters.
- The WordPress receive-pack fixture/example now records feature-probe context,
  disallow-shell probe behavior, explicit-kind bypass, and unsafe probe-host
  rejection without launching SSH.

Verification evidence:

- Baseline focused check before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 655 assertions, 0 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 672 assertions, 0 failures`.
- Full Gitoxide lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files,
  6375 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- Full upstream Cargo workspace tests were not run for this isolated slice.

Dependency closure:

- No new support component is needed. This reuses the native SSH receive-pack
  URL parser, command classifier, caller-injected connector context, and
  existing receive-pack stream implementation. Live SSH feature probing,
  authentication, and channel setup remain caller-owned and were not invoked.

Non-overlap:

- This does not repeat accepted legacy SSH scheme admission, scp-like home-path
  normalization, decoded delimiter rejection, explicit-user option-host argv
  safety, shell-use/disallow-shell exposure, ProgramKind port/argument
  planning, protocol-v2/auth connector context, smart HTTP redirect/proxy
  behavior, git-daemon service requests, stream timeouts, or send-pack status
  parsing. It is bounded to the Gitoxide unknown-command feature-probe boundary
  before SSH receive-pack connector handoff.
