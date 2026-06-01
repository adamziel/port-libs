# SSH Receive-Pack Identity Boundary Parity

Micro-slice: `gitoxide-ssh-receive-pack-boundary-parity-20260601T063919Z`

Accepted base: `0beac79ced31a7dd838adc7168578a431ce35af2`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/file.rs`
  `SpawnProcessOnDemand::set_identity()` sets the SSH URL user from a
  credential identity username and clears the URL user when the identity
  username is empty.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/program_kind.rs`
  `ProgramKind::prepare_invocation()` builds the final `user@host` SSH argv
  argument, rejects ambiguous users/hosts, and feeds the same URL identity into
  command planning.

Behavior added:

- `SshReceivePackTransport::connect()` and `connectorContext()` now accept an
  `identityUsername` option. A non-empty string replaces the URL user before
  connector handoff, SSH argv planning, and credential-helper context storage.
- Empty string or `null` clears the URL user, matching Gitoxide's empty
  identity username boundary.
- Unsafe identity usernames are rejected before connector handoff. Clearing an
  identity on an option-looking host revalidates the host and fails before
  exposing a raw ambiguous SSH argument.
- The WordPress receive-pack fixture/example now records identity replacement,
  clearing, unsafe identity rejection, and option-host clearing rejection.

Verification evidence:

- Red-first focused check after adding assertions and before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  failed as expected with `1 test files, 752 assertions, 2 failures` because
  the connector still saw `NULL` and the fixture kept the stale URL user.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 871 assertions, 0 failures`.
- Full Gitoxide lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files,
  7883 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- Full upstream Cargo workspace was not run for this isolated micro-slice.

Dependency closure:

- No new support component is needed. This reuses the native SSH receive-pack
  URL parser, caller-injected stream connector boundary, SSH argv planner, and
  credential context serialization. Live SSH authentication remains
  caller-owned and was not invoked.

Non-overlap:

- This does not repeat accepted SSH program kind detection, protocol-v2
  environment handling, nonnumeric port parsing, shell feature-probe planning,
  invocation quoting, option-looking host handling with URL users, root-path
  handling, or scp-like username/IPv6 URL parsing. It is bounded to the
  Gitoxide identity-to-SSH-user boundary before receive-pack connector handoff.
