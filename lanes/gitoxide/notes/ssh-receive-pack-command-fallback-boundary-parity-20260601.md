# SSH Receive-Pack Command Fallback Boundary Parity

Micro-slice: `gitoxide-ssh-receive-pack-boundary-parity-20260601T103218Z`

Accepted base: `25bfd8b5291a9dba8331a5a3b17363ea2ce51f4a`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/repository/config/mod.rs`
  resolves `core.sshCommand` first, then activates
  `gitoxide.ssh.commandWithoutShellFallback` only as a fallback and sets
  `disallow_shell` when that fallback is active.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/src/config/tree/sections/gitoxide.rs`
  defines `gitoxide.ssh.commandWithoutShellFallback` as the executable fallback
  with `GIT_SSH` environment override semantics in upstream Gitoxide.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix/tests/gix/repository/config/mod.rs`
  verifies the fallback command is preserved, the configured `ssh.variant`
  remains effective, and shell fallback is disabled.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/mod.rs`
  and `program_kind.rs` consume those options while preparing SSH invocation and
  feature-probe boundaries.

## Native Delta

- `SshReceivePackTransport::connect()` and `connectorContext()` now accept
  `commandWithoutShellFallback`.
- The fallback command is used only when `sshCommand` is absent, matching
  upstream `core.sshCommand` precedence.
- An active fallback forces `disallowShell=true`, so shell-looking fallback
  commands expose `useShell=false` and feature probes are planned without a
  shell.
- The configured `programKind` still controls SSH argv planning when a fallback
  command is active; a fallback plus `putty` keeps `-P <port>` arguments.
- The WordPress receive-pack fixture/example records fallback command, argv,
  feature-probe, command-precedence, and invalid fallback command boundaries.

## Verification

- Red-first probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $ctx=PortLibs\Gitoxide\SshReceivePackTransport::connectorContext("ssh://deploy@git.example.test:2222/var/www/wp-content.git", ["protocolVersion"=>2, "programKind"=>"putty", "commandWithoutShellFallback"=>"ssh --fallback"]); var_export(["sshCommand"=>$ctx["sshCommand"], "disallowShell"=>$ctx["disallowShell"], "useShell"=>$ctx["useShell"], "programKind"=>$ctx["programKind"], "sshArguments"=>$ctx["sshArguments"]]); echo "\n";'`
  reported `sshCommand => putty`, `disallowShell => false`, and ignored the
  fallback command.
- `php -l lanes/gitoxide/src/SshReceivePackTransport.php` passed.
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php` passed.
- `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 1063 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files,
  8777 assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide` passed.
- Full upstream Cargo workspace tests were not run for this isolated
  micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native SSH
receive-pack URL parser, ProgramKind argv planner, non-executing feature-probe
metadata, caller-injected connector boundary, receive-pack stream transport,
and WordPress receive-pack fixture/example. No live SSH process, provider,
credential store, process environment, or external Git command was invoked.

## Non-Overlap

This does not repeat accepted SSH protocol-v2 environment planning,
Git environment-removal exposure, identity username replacement/clearing,
ProgramKind file-stem inference, port flag ordering, shell-use detection,
feature-probe host rejection, option-looking host/user safety, legacy SSH
scheme admission, scp-like home-path normalization, nonnumeric-port host
parsing, root-path handling, invocation quoting, smart HTTP behavior,
git-daemon behavior, packet-line boundaries, send-pack status parsing, or
protocol-v2 fetch sideband parsing. It is bounded to upstream
`commandWithoutShellFallback` precedence and no-shell fallback behavior before
SSH receive-pack connector handoff.
