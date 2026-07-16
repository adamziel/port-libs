# Receive-pack transport empty extra-parameter parity

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T152219Z`
Base accepted HEAD: `1ae10d3b407a43d8a283421317a85a7a1d500366`

## Upstream source truth

Pinned Gitoxide upstream: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.

- `gix-transport/src/client/git/mod.rs:62-71` appends every extra parameter as a NUL-delimited string. `Some(value)` is formatted as `key=value`, so `Some("")` is transmitted as `key=`.
- `gix-transport/src/client/blocking_io/http/mod.rs:345-370` builds the smart HTTP `Git-Protocol` header the same way: `Some(value)` becomes `key=value` and the formatted parameters are joined with `:`.

## Native behavior moved

- `GitDaemonReceivePackTransport` now accepts validated extra parameters with an empty value, e.g. `server-option=`, while still rejecting empty keys, spaces, colons, control bytes, and NUL-delimited injection.
- Smart HTTP receive-pack discovery fixtures now preserve `server-option=` in both protocol v2 and explicit protocol v1 `Git-Protocol` headers.
- The WordPress receive-pack transport fixture and example expose the git-daemon empty-value request payload and the smart HTTP empty-value header path.

## Verification

- `php -l lanes/gitoxide/src/GitDaemonReceivePackTransport.php`
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-receive-pack-transport.php`
- `php -l lanes/gitoxide/examples/wordpress-receive-pack-transport.php`
- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - `1 test files, 1284 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-receive-pack-transport.php`
- `git diff --check -- lanes/gitoxide`

Full Gitoxide PHP lane and full upstream Cargo workspace were not executed for this isolated micro-slice.

## Dependency closure

No new support component is needed. The slice reuses the existing native receive-pack transport request/header builders and the existing WordPress fixture/example surface.

## Non-overlap

This does not repeat the accepted receive-pack packet-line boundary, git-daemon value-only extra-parameter, smart HTTP receive-pack announcement, SSH receive-pack argv/auth boundary, or smart HTTP proxy/cookie credential clusters. It only covers the empty-value `key=` extra-parameter boundary shared by Gitoxide's git-daemon and smart HTTP transports.
