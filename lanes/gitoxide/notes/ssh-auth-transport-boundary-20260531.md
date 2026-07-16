# SSH Auth Transport Boundary Parity - 2026-05-31

Micro-slice: `gitoxide-ssh-auth-transport-boundary-parity-20260531T0815Z`

Accepted base: `c30488a9bf382b1e22d97b1c84f8d0d71eb4f7ef`

Upstream source truth:

- `gix-transport/src/client/blocking_io/ssh/mod.rs`
- `gix-transport/src/client/blocking_io/ssh/program_kind.rs`
- `gix-transport/src/client/blocking_io/ssh/tests.rs`
- `gix-transport/src/client/non_io_types.rs`

Implemented behavior:

- `SshReceivePackTransport::connect()` now accepts optional `protocolVersion` 1 or 2 while preserving the existing five-argument connector boundary for callers that do not opt in.
- Connectors with six parameters or a variadic signature receive an upstream-shaped context with `GIT_PROTOCOL=version=2`, `LANG=C`, `LC_ALL=C`, SSH argument planning, the quoted remote `git-receive-pack` command, and a redacted `CredentialContext`.
- SSH authentication and channel setup remain caller-owned. The PHP transport does not read credentials or invoke SSH, and SSH URL passwords are rejected before connector handoff.

Verification:

- `php -l lanes/gitoxide/src/SshReceivePackTransport.php` - passed.
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php` - passed.
- `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php` - passed.
- `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php` - passed.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` - passed, `1 test files, 392 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` - passed.

Dependency closure:

- No new support component is needed for this slice. It reuses the existing caller-injected SSH connector boundary, native `CredentialContext`, receive-pack packet/request builders, and URL/argument preflight. A future native SSH channel implementation should be activated only behind local fixture evidence, not live provider credentials.

Root harness:

- Not run - isolated micro-slice.
