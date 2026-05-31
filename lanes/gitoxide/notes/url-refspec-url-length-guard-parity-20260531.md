# Gitoxide URL/Refspec URL Length Guard Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260531T120647Z`

Accepted base: `e4074c45f1e9d3c2408ad3ef65aec8f4e6ec75cf`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/parse.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/fuzzed.rs`

`gix-url` bounds URL-form parsing before invoking the URL parser: the protocol
text and the pre-path host/authority portion are limited to `1024` bytes. The
focused fuzz regression also keeps very long URL inputs from becoming slow or
allocation-heavy parse cases.

## Native PHP Delta

- `GitUrl::parseUrlForm()` now rejects protocol text longer than `1024` bytes.
- URL authority bytes before the repository path are now capped at the same
  `1024` byte boundary before host/port parsing.
- Local file paths and SCP-like alternatives keep their existing behavior; the
  guard is scoped to URL-form inputs like upstream `gix-url::parse::url()`.
- The WordPress URL/refspec example now records that an oversized deployment
  remote host is rejected before normalizing fetch/push refspecs.

## Focused Evidence

- Red-first after adding assertions:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` failed with
  `Expected exception InvalidArgumentException was not thrown`.
- Focused after fix:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 444 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `39 test files, 4487 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test`
  exited 0.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP URL parser
and adds the upstream pre-parser byte guard directly; no shared dependency row
or activation gate is proposed.

## Non-Overlap

This extends URL/refspec parity beyond accepted file-authority/SCP IPv6,
forced fetch-only normalization, one-sided push writer normalization, and short
hex prefix expansion. It is bounded to the `gix-url` URL-form length preflight
and does not touch transport, protocol v2, pack/object database, references,
merge, pathspec, sparse checkout, or the stale smart HTTP receive-pack rework
notes from May 25.
