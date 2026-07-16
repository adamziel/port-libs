# SSH Receive-Pack ProgramKind Boundary Parity

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260531T183708Z`

Accepted base: `1d7de15e4e85a2b8dbfd1c80922d2921091d0371`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/program_kind.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/tests.rs`

Behavior added:

- `SshReceivePackTransport::connectorContext()` now exposes upstream-shaped
  SSH program-kind planning for caller-owned connectors.
- Standard `ssh` preserves the accepted protocol-v2 `GIT_PROTOCOL` /
  `SendEnv=GIT_PROTOCOL` boundary and `-p<port>` port argument.
- `plink` and `putty` use `-P <port>`, and `tortoiseplink` also prefixes
  `-batch`, matching Gitoxide `ProgramKind::prepare_invocation()`.
- `simple` clients get only the combined user/host argument and reject
  ported targets before connector handoff.
- `sshCommand` basename inference maps `ssh`, `plink`, `putty`, and
  `tortoiseplink(.exe)` to their program kinds, with unknown commands treated
  as `simple` like Gitoxide's pre-feature-check fallback.
- The WordPress receive-pack fixture/example records plink,
  tortoiseplink, and simple-client argument plans without launching SSH.

Verification evidence:

- Baseline before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 479 assertions, 0 failures`; the ProgramKind
  assertions were not present.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 507 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `39 test files,
  5075 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- Syntax and diff checks:
  `php -l` passed for the changed PHP files, and
  `git diff --check -- lanes/gitoxide` passed.
- Full upstream Cargo workspace tests were not run for this isolated slice.

Dependency closure:

- No new support component is needed. This reuses the existing caller-injected
  SSH connector boundary, native receive-pack URL parser, command quoting,
  and credential context. Live SSH authentication and process spawning remain
  caller-owned and were not invoked.

Non-overlap:

- This builds on accepted SSH protocol-v2/auth connector context,
  option-looking host safety, legacy `ssh+git` / `git+ssh` URL admission,
  scp-like home-path normalization, smart HTTP redirect/proxy behavior,
  git-daemon service requests, stream timeouts, and send-pack status parsing.
  It is bounded to Gitoxide `ProgramKind` invocation planning for SSH-backed
  receive-pack connector contexts.
