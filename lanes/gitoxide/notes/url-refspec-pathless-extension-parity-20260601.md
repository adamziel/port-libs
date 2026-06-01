# Gitoxide URL/Refspec Pathless Extension Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T003402Z`

Accepted base: `5b87111468b46af8cd72097f10d11bf759b0ca92`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/lib.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/parse.rs`

`gix-url` accepts pathless extension-scheme URL-form remotes such as
`rad://user@host.git`, preserving an empty path. Its path argument helper
returns no usable shell argument when the parsed path is empty.

## Native PHP Delta

- `GitUrl::pathArgumentSafe()` now returns `null` for empty parsed paths
  instead of exposing an empty string as a safe shell argument.
- `UrlRefSpecTest.php` now maps pathless extension-scheme parsing using the
  upstream Radicle-style fixture shape and verifies extension schemes with a
  real path still serialize and remain path-argument safe.
- The WordPress URL/refspec normalization example now records a pathless custom
  remote-helper URL and proves deployment tooling does not pass an empty path
  argument to a shell command.

## Focused Evidence

- Upstream focused probe:
  `cargo test -p gix-url --test url radicle::basic -- --nocapture` passed
  `1 passed; 0 failed; 111 filtered out`.
- Focused PHP:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 557 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `40 test files, 6500 assertions, 0 failures`.
- Syntax checks:
  `php -l lanes/gitoxide/src/GitUrl.php`,
  `php -l lanes/gitoxide/tests/UrlRefSpecTest.php`,
  `php -l lanes/gitoxide/examples/wordpress-url-refspec-normalize.php`, and
  `php -l lanes/gitoxide/fixtures/wordpress-url-refspec-normalize.php` all
  reported no syntax errors.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test`
  exited 0.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP URL parser
and argument-safety helper. It does not inspect process environments,
credential stores, provider config, OAuth state, external remotes, or live
services.

## Non-Overlap

This maps one additional `gix-url` extension-scheme/argument-safety boundary
and does not repeat accepted URL/refspec file authority, forced fetch-only,
one-sided push writer, short-hex prefix, URL length guard, empty SSH port, SCP
bracket boundary, home-path expansion, canonical file path, or FTP host-required
work. It also does not touch transport, protocol v2, pack/object database,
reference transactions, merge/pathspec, partial clone, credential helpers, or
the stale May 25 smart HTTP receive-pack rework notes.
