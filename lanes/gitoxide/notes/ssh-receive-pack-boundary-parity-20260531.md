# SSH Receive-Pack Boundary Parity

Micro-slice: `gitoxide-ssh-receive-pack-boundary-parity-20260531T122411Z`

Accepted base: `04dbb4e533b3fb66a6f43a84ffc1c556c2be36a7`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/scheme.rs`
  maps legacy `ssh+git` and `git+ssh` schemes to `Scheme::Ssh`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/parse.rs`
  normalizes scp-like `host:/~repo` repository paths to `~repo`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/ssh.rs`
  covers scp-like home-path and absolute-path forms.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/fixtures/make_baseline.sh`
  includes the generated baseline matrix for `ssh+git`, `git+ssh`, `ssh`,
  and `git` URL forms.

Behavior added:

- `SshReceivePackTransport::parseRepositoryUrl()` now accepts `ssh+git://`
  and `git+ssh://` as SSH receive-pack targets while preserving existing host,
  user, password, port, query, fragment, and command-argument guards.
- Scp-like `host:/~repo.git` paths now normalize to `~repo.git` before
  `git-receive-pack` command construction, matching Gitoxide's shell path
  expansion boundary.
- The WordPress receive-pack fixture/example now records legacy SSH targets,
  home-path command quoting, and legacy-scheme option-like host rejection.

Verification evidence:

- Baseline focused check before implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 415 assertions, 0 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 425 assertions, 0 failures`.
- Full Gitoxide lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `39 test files,
  4550 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0.
- Full Cargo workspace tests were not run for this isolated micro-slice.

Dependency closure:

- No new support component is needed. This reuses the native SSH receive-pack
  URL parser, command quoting, caller-injected stream connector boundary, and
  existing receive-pack fixture/example. Live SSH authentication and channel
  setup remain caller-owned and were not invoked.

Non-overlap:

- This does not repeat accepted SSH protocol-v2/auth connector context,
  decoded SSH host/user delimiter rejection, encoded username delimiter
  rejection, smart HTTP receive-pack redirect/cookie behavior, git-daemon
  service requests, stream timeout handling, or send-pack status parsing. It is
  bounded to legacy SSH scheme admission plus scp-like home-path normalization
  before receive-pack connector handoff.
