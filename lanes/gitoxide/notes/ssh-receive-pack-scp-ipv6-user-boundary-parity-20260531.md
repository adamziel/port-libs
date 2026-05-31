# SSH Receive-Pack SCP IPv6 User Boundary Parity

Micro-slice: `gitoxide-ssh-receive-pack-boundary-parity-20260531T214030Z`

Accepted base: `c7ca7ac45660966d9eecf84ad3eaffea66691f11`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/ssh.rs`
  accepts SCP-like bracketed IPv6 without user info via `[::1]:repo`.
- The same upstream test rejects `user@[::1]:repo` because Git does not
  support SCP-like bracketed IPv6 hosts with user info.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/program_kind.rs`
  still owns the caller-approved SSH connector invocation boundary.

Behavior added:

- `SshReceivePackTransport::parseRepositoryUrl()` now rejects SCP-like
  `user@[IPv6]:repo` targets before connector context construction.
- SCP-like bracketed IPv6 without user info remains accepted and normalized
  to an unbracketed host for the caller-owned connector.
- Explicit `ssh://user@[IPv6]:port/path` URLs remain accepted, preserving the
  already mapped SSH auth and ProgramKind connector behavior.
- The WordPress receive-pack fixture/example now records the accepted
  scp-like IPv6 no-user target and the rejected user-bearing form.

Verification evidence:

- Red-first probe before the patch:
  `SshReceivePackTransport::parseRepositoryUrl('deploy@[2001:db8::42]:wp-content.git')`
  returned an accepted target.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 603 assertions, 0 failures`.
- Full Gitoxide lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `39 test files,
  5787 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- Full Cargo workspace tests were not run for this isolated micro-slice.

Dependency closure:

- No new support component is needed. This reuses the native SSH receive-pack
  URL parser, caller-injected stream connector boundary, and existing
  WordPress receive-pack fixture/example. Live SSH authentication and process
  spawning remain caller-owned and were not invoked.

Non-overlap:

- This does not repeat accepted SSH protocol-v2/auth connector context,
  ProgramKind argv planning, shell-use detection, legacy `ssh+git` /
  `git+ssh` scheme admission, scp-like `/~` home-path normalization, decoded
  host/user delimiter rejection, option-looking host safety, smart HTTP
  redirect/cookie behavior, git-daemon service requests, stream timeout
  handling, or send-pack status parsing. It is bounded to the upstream
  `gix-url` SCP-like bracketed IPv6 user-info boundary as applied to
  receive-pack connector targets.
