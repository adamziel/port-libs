# Gitoxide URL/Refspec Credential Mutation Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T025320Z`

Accepted base: `515fa94ece8af5512b4751f4654c8d7fe66ba5ec`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/lib.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/access.rs`

`gix-url` exposes credential accessors/mutators and verifies that a URL with a
changed username and password serializes to canonical bytes, reparses, and
redacts the password through display formatting.

## Native PHP Delta

- `GitUrl::withUser()` and `GitUrl::withPassword()` now provide immutable PHP
  equivalents for the upstream credential mutation boundary.
- Mutated credentials are validated as UTF-8 before serialization, percent
  encoded through the existing userinfo encoder, and preserved through
  `GitUrl::parse($url->toBytes())` round-trips.
- The WordPress URL/refspec normalization fixture/example now records a
  deployment remote whose user/token credentials are injected after parse,
  serialized canonically, and displayed with the password redacted.

## Focused Evidence

- Baseline focused URL/refspec check before this patch:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 581 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 608 assertions, 0 failures`.
- Full Gitoxide lane after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 7086
  assertions, 0 failures`.
- Syntax checks passed for the changed PHP files:
  `lanes/gitoxide/src/GitUrl.php`,
  `lanes/gitoxide/tests/UrlRefSpecTest.php`,
  `lanes/gitoxide/examples/wordpress-url-refspec-normalize.php`, and
  `lanes/gitoxide/fixtures/wordpress-url-refspec-normalize.php`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test`
  exited 0.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
URL parser, UTF-8 validation, userinfo percent-encoding, and display redaction
logic. No live remote, credential store, environment, upstream binary, or
shared support-library activation gate is required.

## Non-Overlap

This extends the accepted URL/refspec parse-normalize surface with upstream
`gix-url` credential mutation/access parity. It does not repeat accepted
baseline URL parsing, file authority handling, one-sided push writer
normalization, short-hex prefix handling, URL length guards, empty SSH port
normalization, SCP bracket boundaries, home-path expansion, canonicalized file
paths, pathless extension remotes, or argument-safety/root-path helpers. The
historical May 25 smart HTTP receive-pack rework notes target stale
transport/status metadata conflicts and are not part of this URL/refspec
cluster.
