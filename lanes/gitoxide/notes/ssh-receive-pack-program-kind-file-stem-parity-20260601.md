# SSH Receive-Pack ProgramKind File-Stem Parity

Micro-slice: `gitoxide-ssh-receive-pack-boundary-parity-20260601T091120Z`

Accepted base: `8c8829e6ea966fa9e8e7ed89cc2696e6096ac93d`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/program_kind.rs`
  implements `From<&OsStr> for ProgramKind` with `std::path::Path::file_stem()`.
  The stem, not only a `.exe` suffix, determines whether a caller-supplied
  command is treated as `ssh`, `plink`, `putty`, `tortoiseplink`, or `simple`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/tests.rs`
  verifies basename-based kind detection for known programs, unknown simple
  fallback, and the shell-looking `ssh -VVV` simple fallback boundary.

Behavior added:

- `SshReceivePackTransport::programKindFromCommand()` now strips one non-leading
  filename extension before matching the known SSH program kinds. This matches
  Rust `Path::file_stem()` for extension-bearing command names such as
  `plink.cmd`, `putty.wrapper`, `ssh.custom`, and `tortoiseplink.bat`.
- Extension-bearing built-in commands now use the same receive-pack argv
  planning as their stem kind: `plink`/`putty` use `-P <port>`, `ssh` uses
  `-o SendEnv=GIT_PROTOCOL` and `-p<port>` for protocol v2, and
  `tortoiseplink` keeps `-batch`.
- Leading-dot commands such as `.ssh` remain `simple`, preserving the upstream
  hidden-file stem boundary and keeping the unknown-command feature probe path.
- The WordPress receive-pack fixture/example records the inferred kinds and argv
  for deployment connector commands with non-`.exe` extensions.

Verification evidence:

- Red-first focused probe on the accepted base:
  `php -r 'require "tools/bootstrap.php"; ... SshReceivePackTransport::connectorContext("ssh://deploy@git.example.test:2222/var/www/wp-content.git", ["protocolVersion"=>2,"sshCommand"=>"/opt/bin/plink.cmd"]) ...'`
  failed with `InvalidArgumentException: SSH receive-pack simple programKind
  does not support setting the port`; the same failure occurred for
  `putty.wrapper`, `ssh.custom`, and `tortoiseplink.bat`.
- Baseline focused test before editing:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 958 assertions, 0 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 990 assertions, 0 failures`.
- Full Gitoxide lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with
  `40 test files, 8415 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- Full upstream Cargo workspace was not run for this isolated micro-slice.

Dependency closure:

- No new support component is needed. This reuses the native SSH receive-pack
  URL parser, caller-owned connector boundary, ProgramKind argv planner,
  feature-probe metadata, and WordPress receive-pack fixture/example. No live
  SSH process, credential store, provider, process environment, or external
  Git command was used.

Non-overlap:

- This does not repeat accepted SSH identity replacement, feature-probe
  scheduling, program-kind `.exe` detection, port flag ordering, shell-use
  detection, protocol-v2 environment planning, nonnumeric-port host parsing,
  option-looking host/user safety, root-path handling, scp-like username or
  IPv6 parsing, invocation quoting, smart HTTP behavior, git-daemon behavior,
  packet-line boundaries, send-pack status parsing, or protocol-v2 fetch
  sideband parsing. It is bounded to the upstream file-stem inference boundary
  before receive-pack SSH connector argv planning.
