# SSH Receive-Pack Host Normalization Boundary Parity - 2026-06-01

Slice: `gitoxide-ssh-receive-pack-boundary-parity-20260601T180949Z`

Base accepted HEAD: `06c576275849a313a80eaf853243e2fb0ad07924`

## Upstream Source Truth

- Pinned upstream `gix-url/src/simple_url.rs::normalize_hostname()` rejects
  question marks and ASCII whitespace, lowercases DNS-like hostnames, and
  preserves non-DNS host text such as colon-rich SSH authorities.
- Pinned upstream `gix-url/src/parse.rs` strips bracketed IPv6 SSH authority
  brackets after normalization, so bracketed IPv6 hex is lowercased while bare
  colon-rich hosts remain literal.
- Pinned upstream
  `gix-transport/src/client/blocking_io/ssh/program_kind.rs::prepare_invocation()`
  receives the normalized host as the single safe host or user-at-host argv
  item before caller-owned SSH process execution.

## Native Delta

- `SshReceivePackTransport::normalizeHost()` now lowercases DNS-like SSH hosts
  and bracketed IPv6 hosts before receive-pack connector handoff.
- Bare colon-rich SSH authorities remain preserved to match gix-url's
  non-DNS host boundary.
- Decoded question-mark hosts such as `bad%3fhost.example.test` are rejected
  before argv planning or credential-helper context construction.
- The WordPress receive-pack fixture/example records the normalized SSH target
  and decoded-question host rejection without launching SSH or reading
  credentials.

## Red-First Evidence

Before the change, local probes against the accepted base showed:

- `ssh://Deploy@Host.XZ/repo.git` parsed as host `Host.XZ` while upstream
  `GitUrl::parse()` normalized it to `host.xz`.
- `Deploy@Host_XZ:repo.git` parsed as host `Host_XZ` while upstream
  normalized it to `host_xz`.
- `ssh://deploy@[2001:DB8::42]:2222/srv/wp-content.git` kept uppercase IPv6
  hex while upstream normalized it to `2001:db8::42`.
- `ssh://bad%3fhost.example.test/repo.git` was not guarded by the host
  delimiter validation.

## Verification

- `php -l lanes/gitoxide/src/SshReceivePackTransport.php` passed.
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php` passed.
- `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php`
  passed.
- `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php`
  passed.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 1317 assertions, 0 failures`.
- Focused command after edits:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 1331 assertions, 0 failures`.
- Example smoke:
  `php -r '$summary = require "lanes/gitoxide/examples/wordpress-receive-pack-transport.php"; ...'`
  printed `wordpress receive-pack ssh host normalization example ok`.
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); ...'`
  printed `lane-status json ok`.

Full Gitoxide lane, root harness, live SSH/provider tests, and upstream Cargo
workspace tests were not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native receive-pack URL
parser, connector-context builder, credential context, ProgramKind planning,
and caller-provided SSH connector boundary. Live SSH process execution,
authentication, provider configuration, and credential stores remain
caller-owned and were not invoked.

## Non-Overlap

This does not repeat accepted SSH protocol-v2/auth connector context,
ProgramKind port planning, unknown-command feature probing, shell-use or
disallow-shell exposure, legacy `ssh+git` / `git+ssh` admission, scp-like
username-at-sign handling, option-looking host safety, bare IPv6 authority
parity, non-numeric port suffix parsing, smart HTTP receive-pack behavior,
git-daemon receive-pack service requests, receive-pack packet-line request
bounds, or send-pack status parsing. It is bounded to gix-url SSH host
normalization and decoded-question rejection before receive-pack connector
handoff.
