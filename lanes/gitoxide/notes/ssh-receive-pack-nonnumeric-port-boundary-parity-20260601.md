# SSH Receive-Pack Non-Numeric Port Boundary Parity

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T005005Z`

Accepted base: `5b87111468b46af8cd72097f10d11bf759b0ca92`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/invalid.rs`
  has `invalid_port_format`, which accepts `ssh://host.xz:abc/path` and
  records `host.xz:abc` as the host with no parsed port.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/simple_url.rs`
  treats a regular `host:port` separator as a port only when the suffix is
  non-empty ASCII digits; otherwise the whole authority remains the host.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/ssh/program_kind.rs`
  passes the safe host, optionally combined with a user, as one SSH argv item
  before spawning transport I/O.

Behavior added:

- `SshReceivePackTransport::parseRepositoryUrl()` now falls back when PHP
  rejects URL-form SSH authorities with non-numeric port-looking suffixes.
- The fallback is scoped to non-numeric suffixes: `ssh://host.xz:abc/path`
  maps to host `host.xz:abc`, port `null`, path `/path`, while numeric
  overflow ports such as `:65536` still fail.
- `connectorContext()` preserves the upstream-shaped combined argv item such
  as `deploy@git.example.test:tenant` and does not bracket the non-numeric
  suffix as if it were an IPv6 literal.
- The WordPress receive-pack fixture/example now records the accepted
  non-numeric suffix target and the numeric overflow rejection.

Verification evidence:

- Red-first spot check before implementation:
  `php -r 'require "tools/bootstrap.php"; PortLibs\Gitoxide\SshReceivePackTransport::parseRepositoryUrl("ssh://deploy@git.example.test:tenant/wp-content.git");'`
  failed with `InvalidArgumentException`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 702 assertions, 0 failures`.
- Full Gitoxide lane check:
  `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files,
  6497 assertions, 0 failures`.
- Handoff checks:
  `php -l` passed for changed PHP files,
  `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited 0,
  JSON validation passed for `UPSTREAM_TEST_MANIFEST.json` and
  `lane-status.json`, and `git diff --check -- lanes/gitoxide` passed.
- Full upstream Cargo workspace tests were not run for this isolated slice.

Dependency closure:

- No new support component is needed. This reuses the native PHP SSH
  receive-pack URL parser, ProgramKind connector context, credential context,
  and existing stream transport boundary. Live SSH process execution,
  authentication, and credential stores remain caller-owned and were not
  invoked.

Non-overlap:

- This does not repeat accepted SSH protocol-v2/auth connector context,
  ProgramKind port/argument planning, unknown-command feature probing,
  shell-use/disallow-shell exposure, legacy `ssh+git` / `git+ssh` admission,
  scp-like username-at-sign handling, encoded delimiter rejection,
  option-looking host safety, smart HTTP redirect/proxy behavior, git-daemon
  service requests, stream timeouts, packet-line request bounds, or send-pack
  status parsing. It is bounded to the gix-url non-numeric URL-form SSH port
  fallback as observed through receive-pack connector handoff.
