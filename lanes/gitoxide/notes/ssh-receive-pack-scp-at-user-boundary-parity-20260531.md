# SSH Receive-Pack SCP At-User Boundary Parity

Micro-slice: `gitoxide-ssh-receive-pack-boundary-parity-20260531T224332Z`

Accepted base: `33a65237308053a0654b3629f3bffe8d77c73515`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/ssh.rs`
  includes `scp_like_with_username_including_at`, where
  `user@name@host.xz:path` round-trips as SCP-like SSH with user
  `user@name`, host `host.xz`, and path `path`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/parse.rs`
  parses SCP-like authorities through URL authority parsing, which splits user
  info at the last `@`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/program_kind.rs`
  then builds a single SSH target argument from usable user and host parts.

Behavior added:

- `SshReceivePackTransport::parseRepositoryUrl()` now splits SCP-like
  authorities at the last `@`, matching Gitoxide for usernames that themselves
  contain an at-sign.
- `user@name@host.xz:wp-content.git` reaches the caller-owned connector as
  user `user@name`, host `host.xz`, and SSH argv target
  `user@name@host.xz`.
- Existing `ssh://` encoded at-sign/colon username delimiter rejection remains
  in place; the at-sign allowance is limited to SCP-like authority parsing
  after the structural split.
- The WordPress receive-pack fixture/example now records the at-user target
  and protocol-v2 connector argv.

Verification evidence:

- Red-first probe before implementation:
  `SshReceivePackTransport::parseRepositoryUrl('user@name@host.xz:path')`
  returned user `user` and host `name@host.xz`.
- Syntax checks passed:
  `php -l lanes/gitoxide/src/SshReceivePackTransport.php`,
  `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`,
  `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php`,
  and `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php`.
- Focused check:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 631 assertions, 0 failures`.
- Full Gitoxide lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `39 test files,
  6059 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- Hygiene checks:
  `git diff --check -- lanes/gitoxide` exited 0, and JSON decoding passed for
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` plus
  `lanes/gitoxide/lane-status.json`.
- Full Cargo workspace tests were not run for this isolated micro-slice.

Dependency closure:

- No new support component is needed. This reuses the native SSH receive-pack
  URL parser, caller-injected SSH connector boundary, credential context, and
  existing receive-pack fixture/example. Live SSH authentication and process
  spawning remain caller-owned and were not invoked.

Non-overlap:

- This does not repeat accepted SSH protocol-v2/auth connector context,
  ProgramKind argv planning, shell-use detection, legacy `ssh+git` /
  `git+ssh` scheme admission, scp-like `/~` home-path normalization,
  scp-like bracketed IPv6 user rejection, decoded ssh:// host/user delimiter
  rejection, option-looking host safety, smart HTTP redirect/cookie behavior,
  git-daemon service requests, stream timeout handling, or send-pack status
  parsing. It is bounded to the upstream `gix-url` SCP-like
  username-containing-`@` boundary as applied to receive-pack connector
  targets.
