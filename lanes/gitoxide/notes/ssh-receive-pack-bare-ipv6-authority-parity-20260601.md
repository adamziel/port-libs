# SSH Receive-Pack Bare IPv6 Authority Parity - 2026-06-01

Slice: `gitoxide-ssh-receive-pack-boundary-parity-20260601T141649Z`

Base accepted HEAD: `4ffdaaa5b255e04219f8cfab7cf9e3d1ed08d99c`

## Upstream Source Truth

- Pinned upstream `gix-url/src/simple_url.rs::parse_host_port()` keeps
  colon-rich, non-bracketed SSH authorities as the host instead of treating the
  numeric tail as a port. Bracketed IPv6 remains the explicit port form.
- Pinned upstream `gix-url/src/parse.rs` strips brackets from bracketed SSH
  IPv6 hosts, but bare IPv6 authorities remain bare host strings.
- Pinned upstream
  `gix-transport/src/client/blocking_io/ssh/program_kind.rs::prepare_invocation()`
  passes the safe `user@host` or host argument to the SSH program after
  ProgramKind-specific options.

## Native Delta

- `SshReceivePackTransport::parseRepositoryUrl()` now has a scoped fallback for
  bare colon-rich SSH authorities before PHP `parse_url()` can reinterpret the
  last IPv6 segment as a port.
- Bracketed IPv6 with explicit ports keeps the existing accepted behavior.
- `connectorContext()` now plans protocol-v2 SSH arguments and credential
  context for `ssh://deploy@2001:db8::42/wp-content.git` and for numeric-tail
  bare hosts such as `ssh://2001:db8::42:2222/wp-content.git`.
- The WordPress receive-pack fixture/example records the bare IPv6 deployment
  connector boundary without launching SSH or reading credentials.

## Red-First Evidence

Before the change, a local probe showed:

- `ssh://deploy@2001:db8::42/wp-content.git` parsed as host `2001:db8:` with
  port `42`.
- `ssh://2001:db8::42:2222/wp-content.git` parsed as host `2001:db8::42` with
  port `2222`.

Both are opposite to the pinned gix-url colon-rich authority boundary.

## Verification

- `php -l lanes/gitoxide/src/SshReceivePackTransport.php` passed.
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php` passed.
- `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php`
  passed.
- `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php`
  passed.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 1250 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited
  `0`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files,
  9725 assertions, 0 failures`.

Full upstream Cargo workspace tests were not run for this isolated
micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native receive-pack URL
parser, connector-context builder, credential context, and caller-provided SSH
connector boundary. Live SSH spawning, authentication, provider credentials,
and channel setup remain caller-owned and were not invoked.

## Non-Overlap

This does not repeat accepted bracketed IPv6 SSH handling, empty SSH port
normalization, nonnumeric DNS host-port parsing, scp-like username-at-sign
handling, option-looking host safety, protocol-v2 environment exposure,
ProgramKind port planning, shell-use/disallow-shell behavior, quote escaping,
smart HTTP receive-pack behavior, git-daemon receive-pack service requests, or
send-pack status parsing. It is bounded to bare, non-bracketed colon-rich SSH
authorities before receive-pack connector handoff.
