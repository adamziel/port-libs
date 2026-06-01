# SSH Receive-Pack IPv6 Argv Boundary Parity

Micro-slice: `gitoxide-ssh-receive-pack-boundary-parity-20260601T114837Z`

Accepted base: `67d4ed76975ee89824a8db4b5cecf2d98e81eb14`

## Source Truth

- Upstream `gix-transport/src/client/blocking_io/ssh/program_kind.rs` builds SSH process arguments with `ProgramKind::prepare_invocation()` using `url.user_as_argument()` and `url.host_as_argument()` before appending `git-receive-pack`.
- Upstream `gix-url/src/parse.rs` stores bracketed SSH IPv6 hosts as raw host literals.
- Upstream `gix-url/tests/url/parse/ssh.rs` covers URL-form and SCP-like IPv6 parsing to unbracketed hosts while keeping scp-like `user@[IPv6]:repo` rejected.

## Behavior

- `SshReceivePackTransport::sshArgumentsForTarget()` now passes the raw parsed host to SSH process argv, matching upstream host-as-argument behavior.
- URL-form IPv6 receive-pack connector contexts now use `deploy@2001:db8::42` in argv instead of `deploy@[2001:db8::42]`.
- SCP-like IPv6 receive-pack connector contexts now use `2001:db8::42` in argv instead of `[2001:db8::42]`.
- Credential helper context still preserves bracketed authority storage:
  - `host=[2001:db8::42]:2222` for URL-form IPv6 with a port.
  - `host=[2001:db8::42]` for SCP-like IPv6.
- The WordPress receive-pack fixture and example now expose both IPv6 argv plans.

## Red-First Evidence

Before the transport edit, a local connector-context probe produced bracketed SSH argv entries for the same targets:

- URL form: `['-o', 'SendEnv=GIT_PROTOCOL', '-p2222', 'deploy@[2001:db8::42]']`
- SCP-like form: `['-o', 'SendEnv=GIT_PROTOCOL', '[2001:db8::42]']`

Those are now covered by focused assertions as raw IPv6 argv boundaries.

## Verification

- `php -l lanes/gitoxide/src/SshReceivePackTransport.php` -> no syntax errors detected.
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php` -> no syntax errors detected.
- `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php` -> no syntax errors detected.
- `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php` -> no syntax errors detected.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` -> 1 test file, 1128 assertions, 0 failures.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` -> exited 0.
- `php tools/run-tests.php lanes/gitoxide/tests` -> 40 test files, 9111 assertions, 0 failures.
- `git diff --check -- lanes/gitoxide` -> passed.

Full upstream Cargo workspace verification was not run for this isolated PHP micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing SSH URL parser, connector context, credential helper context, receive-pack invocation builder, and local fixture/example smoke coverage. Live SSH/provider execution remains caller-owned and was not activated.

## Non-Overlap

This does not repeat accepted SSH receive-pack program-kind planning, protocol-v2 environment handling, feature-probe boundaries, identity handling, command fallback behavior, option-looking host safety, scp-like IPv6 username rejection, root/authority normalization, invocation quoting, receive-status parsing, or smart-HTTP proxy/cookie work. It is bounded to the SSH argv host boundary for parsed IPv6 receive-pack targets.
