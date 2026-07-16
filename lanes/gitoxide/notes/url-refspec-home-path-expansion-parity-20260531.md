# Gitoxide URL/Refspec Home Path Expansion Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260531T211503Z`

Accepted base: `3a3374ad59c06e8a3561833481036dd945373160`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/expand_path.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/expand_path.rs`

`gix-url` treats absolute URL paths whose first component is `~` or `~user`
as home-directory paths. It can parse the current-user and named-user forms,
format them for shell display, and expand them by asking a home resolver for
the current user or named user.

## Native PHP Delta

- `GitUrl::parseHomePath()` now recognizes `/~/repo`, `/~user/repo`, and
  `/~user` while leaving relative paths and later `~` path segments untouched.
- `GitUrl::forShellPath()` reconstructs shell-facing `~/repo` and
  `~user/repo` display paths from URL-style absolute paths.
- `GitUrl::expandHomePath()` performs deterministic expansion through an
  injected resolver, so callers can supply deployment-safe home mappings
  without reading process environments or provider configuration.
- The WordPress URL/refspec normalization example now exercises a file remote
  under `/~Deploy.User/wp-content/site.git`, verifies shell formatting, and
  resolves it through a local fixture map.

## Focused Evidence

- Red-first after adding assertions: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` failed with `Call to undefined method PortLibs\Gitoxide\GitUrl::parseHomePath()`.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 510 assertions, 0 failures`.
- Full Gitoxide lane after fix: `php tools/run-tests.php lanes/gitoxide/tests` passed `39 test files, 5784 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test` exited 0.

## Dependency Closure

No new support component is needed. This slice uses native PHP path string
helpers plus a caller-injected home resolver; it does not inspect live
environment variables, credential stores, provider config, OAuth state, or
external remotes.

## Non-Overlap

This maps one additional `gix-url` `expand_path` behavior cluster and does not
repeat accepted URL/refspec parse baseline, file authority, one-sided push,
short-hex prefix, URL length guard, empty SSH port, or SCP bracket-boundary
work. It also does not touch transport/protocol, object database, pack/index,
reference transactions, merge/pathspec, partial clone, credential helpers, or
the stale May 25 smart HTTP receive-pack rework notes.
