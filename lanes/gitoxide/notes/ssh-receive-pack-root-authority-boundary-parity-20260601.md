# SSH Receive-Pack Root And Authority Boundary Parity - 2026-06-01

Micro-slice: `gitoxide-ssh-receive-pack-boundary-parity-20260601T053314Z`

Accepted base: `663e16b4022673e2529b925ce20b45f0a578189e`

## Upstream Source Truth

- Pinned upstream `gix-url/src/parse.rs` accepts an SSH URL-form repository
  path of `/` while rejecting only empty SSH/Git paths.
- Pinned upstream `gix-url/src/parse.rs` caps the scheme and pre-path
  authority boundary at `1024` bytes before parsing the repository path.
- Pinned upstream `gix-transport/src/client/blocking_io/ssh/mod.rs` passes the
  parsed SSH URL path through the receive-pack invocation boundary after
  shell-path expansion and quoting.

## Native Delta

- `SshReceivePackTransport::parseRepositoryUrl()` now accepts
  `ssh://host.xz:21/` as a root repository path and carries it into
  `git-receive-pack '/'` connector context planning.
- URL-form SSH authorities longer than the upstream `1024` byte pre-path
  boundary are rejected before connector handoff.
- The WordPress receive-pack fixture/example records the root-path SSH target,
  protocol-v2 argv planning, and the overlong-authority preflight guard.

## Verification

- Red-first boundary before implementation:
  `php -r 'require "tools/bootstrap.php"; try { var_export(PortLibs\Gitoxide\SshReceivePackTransport::parseRepositoryUrl("ssh://host.xz:21/")); echo "\n"; } catch (Throwable $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }'`
  reported `InvalidArgumentException: SSH receive-pack URL must include a repository path`.
- Red-first overlong authority before implementation:
  `php -r 'require "tools/bootstrap.php"; $host=str_repeat("h", 1025); try { PortLibs\Gitoxide\SshReceivePackTransport::parseRepositoryUrl("ssh://{$host}/repo.git"); echo "accepted\n"; } catch (Throwable $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }'`
  reported `accepted`.
- `php -l lanes/gitoxide/src/SshReceivePackTransport.php` passed.
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php` passed.
- `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  passed with `1 test files, 806 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php` exited
  `0`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed with `40 test files,
  7613 assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide` passed.
- Full upstream Cargo workspace tests were not run for this isolated
  micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native SSH
receive-pack parser, command quoting, caller-injected connector context, and
stream transport. Live SSH process spawning, authentication, provider
credentials, and channel setup remain caller-owned and were not invoked.

## Non-Overlap

This does not repeat accepted SSH protocol-v2 environment planning, Git-local
environment removals, ProgramKind port and shell-use planning, feature-probe
guards, option-looking host/user argument safety, legacy SSH scheme admission,
scp-like home-path normalization, nonnumeric-port host parsing, remote-service
argv quoting, smart HTTP receive-pack redirect/proxy/cookie handling,
git-daemon service requests, send-pack status parsing, receive-pack packet-line
bounds, or the general `GitUrl` URL/refspec root-path and length guards. It is
bounded to the receive-pack SSH parser and connector context boundary.
