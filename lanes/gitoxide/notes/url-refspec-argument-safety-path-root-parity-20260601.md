# Gitoxide URL/Refspec Argument Safety and Path Root Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T014724Z`

Accepted base: `d422a4f583db5c682fa3bc6c48dc5ce9f8a1bae6`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/lib.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/access.rs`

`gix-url` distinguishes absent, usable, and dangerous URL components through
`ArgumentSafety`. The existing PHP helpers exposed only nullable safe values,
which was enough for transport guards but did not let callers report why a
deployment remote component was refused. `gix-url` also exposes `path_is_root()`
for normalized root paths such as `http://host.xz`.

## Native PHP Delta

- `GitUrl` now exposes `ARGUMENT_ABSENT`, `ARGUMENT_USABLE`, and
  `ARGUMENT_DANGEROUS` constants.
- `GitUrl::userArgumentSafety()`, `hostArgumentSafety()`, and
  `pathArgumentSafety()` return the upstream-shaped classification plus the
  original component value when present.
- Existing `userArgumentSafe()`, `hostArgumentSafe()`, and `pathArgumentSafe()`
  remain backward-compatible nullable helpers built on the classification.
- `GitUrl::pathIsRoot()` now identifies normalized root paths.
- The WordPress URL/refspec example records safe deployment remote components,
  dangerous SSH option-looking user/host/path components, and HTTP root-path
  safety without invoking `git` or an SSH transport.

## Focused Evidence

- Red-first after adding assertions:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` failed with
  missing `GitUrl::ARGUMENT_DANGEROUS` and missing
  `GitUrl::userArgumentSafety()`, reporting `1 test files, 519 assertions,
  2 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 581 assertions, 0 failures`.
- Full Gitoxide lane after fix:
  `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files,
  6784 assertions, 0 failures`.
- Syntax checks:
  `php -l lanes/gitoxide/src/GitUrl.php`,
  `php -l lanes/gitoxide/tests/UrlRefSpecTest.php`,
  `php -l lanes/gitoxide/examples/wordpress-url-refspec-normalize.php`, and
  `php -l lanes/gitoxide/fixtures/wordpress-url-refspec-normalize.php`
  reported no syntax errors.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test`
  exited 0.
- Lane diff check:
  `git diff --check -- lanes/gitoxide` exited 0.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP URL
parser and access helpers. It does not inspect process environments,
credential stores, provider config, OAuth state, external remotes, or live
services.

## Non-Overlap

This maps one additional `gix-url` access-helper behavior cluster and does not
repeat accepted URL/refspec file authority, forced fetch-only, one-sided push
writer, short-hex prefix, URL length guard, empty SSH port, SCP bracket
boundary, home-path expansion, canonical file path, FTP host-required, or
pathless extension remote work. It also does not touch transport, protocol v2,
pack/object database, reference transactions, merge/pathspec, partial clone,
credential helper exchange, or the stale May 25 smart HTTP receive-pack rework
notes.
