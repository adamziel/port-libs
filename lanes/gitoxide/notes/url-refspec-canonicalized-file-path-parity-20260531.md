# Gitoxide URL Canonicalized File Path Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260531T222346Z`

Accepted base: `6cff27008844e2e4a3255962746465ff174dc963`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/lib.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/access.rs`

`gix-url` exposes `Url::canonicalized()` as a file-URL-only normalization
helper. Non-file URLs are returned unchanged, absolute file paths are already
canonical for this boundary, and relative file paths are resolved against a
caller-provided current directory.

## Native PHP Delta

- `GitUrl::canonicalized()` now returns an immutable canonicalized URL object.
- Non-file URLs and absolute file URLs keep their existing path and serialized
  bytes.
- Relative file paths such as `.`, `../site.git`, and
  `./mirrors/../site.git` are joined to the caller-provided current directory
  and normalized without shelling out to `git`, `realpath`, or provider tools.
- The WordPress URL/refspec normalization example now canonicalizes a relative
  deployment mirror path from a fixture current directory.

## Focused Evidence

- Red-first after adding assertions: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` failed with `Call to undefined method PortLibs\Gitoxide\GitUrl::canonicalized()` and reported `1 test files, 475 assertions, 2 failures`.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 525 assertions, 0 failures`.
- Full Gitoxide lane after fix: `php tools/run-tests.php lanes/gitoxide/tests` passed `39 test files, 5965 assertions, 0 failures`.
- Syntax checks: `php -l lanes/gitoxide/src/GitUrl.php`, `php -l lanes/gitoxide/tests/UrlRefSpecTest.php`, `php -l lanes/gitoxide/examples/wordpress-url-refspec-normalize.php`, and `php -l lanes/gitoxide/fixtures/wordpress-url-refspec-normalize.php` reported no syntax errors.
- Example smoke: `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test` exited 0.

## Dependency Closure

No new support component is needed. This slice uses native PHP string path
normalization with a caller-provided current directory and does not inspect
process environments, credential stores, provider configuration, OAuth state,
external remotes, or live services.

## Non-Overlap

This maps one additional `gix-url` access/canonicalization behavior cluster and
does not repeat accepted URL/refspec parse baseline, file authority, one-sided
push writer, short-hex prefix, URL length guard, empty SSH port, SCP bracket
boundary, or home-path expansion work. It also does not touch transport,
protocol v2, pack/object database, references, merge/pathspec, sparse checkout,
partial clone, credential helpers, or the stale May 25 smart HTTP receive-pack
rework notes.
