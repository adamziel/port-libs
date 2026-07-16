# SSH Receive-Pack Option Host Boundary Parity

Micro-slice: `gitoxide-ssh-receive-pack-boundary-parity-20260531T180427Z`

Accepted base: `e83ba68ab62e3e93ee2dcf9fc87ea144ffeb366d`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/program_kind.rs`
  combines `Usable(user), Dangerous(host)` into a single `user@host` SSH
  argument because the leading `user@` keeps the argv element from being
  interpreted as an option.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/tests.rs`
  covers `ambiguous_host_is_allowed_with_user_explicit_ssh`,
  `ambiguous_host_is_allowed_with_user_implicit_ssh`,
  `ambiguous_host_is_disallowed_without_user`, and dangerous-user rejection.

Behavior added:

- `SshReceivePackTransport::parseRepositoryUrl()` now accepts option-looking
  SSH hosts only when an explicit, safe user is present.
- Userless option-looking hosts and option-looking users remain rejected before
  connector handoff.
- `connectorContext()` now preserves the safe combined `user@-host` SSH
  argument, protocol-v2 environment, and redacted credential-helper context.
- The WordPress receive-pack fixture/example records the accepted
  user-plus-option-like-host target and SSH argument plan.

Verification evidence:

- Baseline before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 456 assertions, 0 failures`; the new upstream
  option-host assertions were not present.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 463 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `39 test files,
  5025 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- Syntax and diff checks:
  `php -l` passed for the changed PHP files, JSON metadata decoded
  successfully, and `git diff --check -- lanes/gitoxide` passed.
- Full upstream Cargo workspace tests were not run for this isolated slice.

Dependency closure:

- No new support component is needed. This reuses the existing native SSH
  receive-pack URL parser, connector context, command quoting, and
  caller-injected stream connector boundary. Live SSH authentication and
  channel setup remain caller-owned and were not invoked.

Non-overlap:

- This does not repeat accepted SSH protocol-v2/auth connector context,
  legacy `ssh+git`/`git+ssh` scheme admission, scp-like home-path
  normalization, decoded host/user delimiter rejection, smart HTTP redirect or
  proxy behavior, git-daemon service requests, stream timeout handling, or
  send-pack receive-status parsing. It is bounded to the Gitoxide argv safety
  distinction for option-like SSH hosts with an explicit user.
