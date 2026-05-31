# Gitoxide URL/Refspec Empty SSH Port Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260531T150824Z`

Accepted base: `5042ee5a640251937d88ffe1e25c7b681010f72f`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/parse.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/fixtures/generated-do-not-edit/make_baseline/sha1/2409820380-unix/git-baseline.unix`

`gix-url` accepts SSH URL-form authorities with an empty port marker, stores
no port, and normalizes the host by removing a single trailing `:` for regular
hosts and the `]:` suffix for bracketed IPv6 hosts. Non-numeric port-like host
text such as `host.xz:abc` remains host text.

## Native PHP Delta

- `GitUrl::parse()` now normalizes URL-form SSH hosts from `ssh://host:/repo`
  to host `host`, no port, and path `/repo`.
- Bracketed IPv6 empty-port SSH URLs such as `ssh://[::1]:/repo` now store
  host `::1` with no port, matching the upstream parser's host normalization.
- Non-numeric port text remains unchanged, preserving the accepted
  `ssh://host.xz:abc/path` behavior.
- The WordPress URL/refspec example now includes an empty-port deployment
  remote and verifies normalized host/path output without shelling out to Git.

## Focused Evidence

- Red-first after adding assertions: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` failed with `Expected: 'host' Actual: 'host:'`.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 464 assertions, 0 failures`.
- Full Gitoxide lane after fix: `php tools/run-tests.php lanes/gitoxide/tests` passed `39 test files, 4760 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test` exited 0.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
URL authority parser and narrows the normalization to URL-form SSH hosts; no
shared dependency row or activation gate is proposed.

## Non-Overlap

This extends URL/refspec parity beyond accepted file-authority/SCP IPv6,
forced fetch-only normalization, one-sided push writer normalization,
short-hex prefix expansion, and URL length guards. It is bounded to
`gix-url` empty SSH port marker normalization and does not touch transport,
protocol v2, pack/object database, references, merge, pathspec, sparse
checkout, or the stale smart HTTP receive-pack rework notes from May 25.
