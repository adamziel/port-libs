# SSH Receive-Pack Environment Boundary Parity

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T020324Z`

## Source Truth

- Upstream `gix-transport/src/client/blocking_io/file.rs` removes local
  repository-scoped Git environment variables before spawning a process-backed
  transport. The removal list includes `GIT_DIR`, `GIT_WORK_TREE`,
  `GIT_OBJECT_DIRECTORY`, alternate-object, replacement, shallow, config, and
  prefix variables.
- Upstream `gix-transport/src/client/blocking_io/ssh/program_kind.rs` then adds
  the transport-specific environment such as `GIT_PROTOCOL=version=2` and
  `LANG=C` / `LC_ALL=C` for SSH invocation parity.

## Native Delta

- `SshReceivePackTransport::connectorContext()` now includes an
  `environmentRemovals` list matching the upstream process-spawn sanitization
  boundary.
- The context keeps `GIT_PROTOCOL` in the positive `environment` map for
  protocol v2 and deliberately does not include it in `environmentRemovals`.
- Caller-owned SSH connectors can now clear local repository state before
  launching their approved transport while preserving the protocol and locale
  variables supplied by the native PHP context.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed: `1 test files, 728 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed:
  `40 test files, 6796 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited
  `0`.
- Full upstream Cargo workspace was not run in this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing caller-owned
SSH connector boundary and only exposes the upstream Git environment
sanitization list as native PHP context data. No live SSH process, credential
store, provider, or process environment inspection is required.

## Non-Overlap

This extends the accepted SSH receive-pack argument-safety, ProgramKind,
feature-probe, shell-use, nonnumeric-port, scp-like user, and auth-context
slices without changing URL parsing, command quoting, packet-line parsing,
smart HTTP redirect/cookie/proxy behavior, git-daemon request bytes, send-pack
status parsing, or object/pack/reference behavior.
