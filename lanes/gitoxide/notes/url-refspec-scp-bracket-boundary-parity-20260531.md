# Gitoxide URL/Refspec SCP Bracket Boundary Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260531T182739Z`

Accepted base: `1d7de15e4e85a2b8dbfd1c80922d2921091d0371`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/parse.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/ssh.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/invalid.rs`

`gix-url::parse::find_scheme()` only skips an IPv6 bracket block when the input
starts with `[`. If the opening bracket never closes, the first `:` still
selects SCP-like parsing, which then rejects the malformed host. The PHP parser
previously treated unmatched bracketed alternatives such as `[::1:repo` as
local file paths.

## Native PHP Delta

- `GitUrl::scpColonPosition()` now mirrors the upstream SCP-like delimiter
  boundary: bracket skipping is limited to leading bracketed hosts, and
  unmatched leading brackets still expose their first colon to SCP parsing.
- Malformed bracketed deployment remotes such as `[::1:repo` and
  `mirror@[2001:db8::1:repo` now fail before being normalized as local paths.
- The WordPress URL/refspec example now records malformed bracketed remote
  rejection alongside oversized remote-host rejection.

## Focused Evidence

- Red-first after adding assertions: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` failed with 1 file / 453 assertions / 2 failures because no exception was thrown and the WordPress malformed-bracket preflight stayed false.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed 1 file / 467 assertions / 0 failures.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests` passed 39 files / 5050 assertions / 0 failures.
- Syntax checks: `php -l lanes/gitoxide/src/GitUrl.php`, `php -l lanes/gitoxide/tests/UrlRefSpecTest.php`, `php -l lanes/gitoxide/examples/wordpress-url-refspec-normalize.php`, and `php -l lanes/gitoxide/fixtures/wordpress-url-refspec-normalize.php` reported no syntax errors.
- Example smoke: `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test` exited 0.
- Lane diff check: `git diff --check -- lanes/gitoxide` exited 0.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
URL authority parser and narrows SCP-like delimiter discovery; no shared
dependency row or activation gate is proposed.

## Non-Overlap

This extends accepted URL/refspec parity beyond file-authority/SCP IPv6,
forced fetch-only normalization, one-sided push writer normalization, short
hex prefix expansion, URL length guards, and empty SSH port markers. It is
bounded to malformed SCP-like bracket delimiter handling and does not touch
transport, protocol v2, pack/object database, references, merge, pathspec,
sparse checkout, or stale May 25 smart HTTP receive-pack rework notes.
