# Gitoxide Attributes Pathspec Valid-Bracket Fallback Parity

Micro-slice: `gitoxide-attributes-pathspec-match-parity-20260601T110711Z`

Accepted base: `87b9b5e4231e455752546908281e85ed6f228913`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-attributes/src/search/attributes.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`

`gix-pathspec` wildcard matching first checks the wildcard pathspec against the
repo-relative path, then falls back to a verbatim pathspec match when the
wildcard comparison misses. Attribute requirements are evaluated after that
path match. `gix-attributes` evaluates attribute patterns directly through glob
matching and does not perform the pathspec verbatim fallback, so a valid
bracket class such as `[abc]` can match `a`, `b`, or `c` while refusing a
literal `[` path unless the attribute pattern escapes that bracket literally.

## Native PHP Delta

- `AttributesPathspecTest.php` now pins the valid-bracket boundary where
  `:(glob,attr:...)foo[abc]` may fall back verbatim for the pathspec, but the
  attribute filter still decides inclusion from the literal path's attributes.
- The WordPress attributes/pathspec example now records both sides of that
  boundary: an attribute bracket class skips the literal bracket path, while an
  escaped literal bracket attribute admits the pathspec fallback match.
- No production source change was required; the existing PHP matcher already
  had the upstream wildcard-then-verbatim pathspec behavior and attribute
  filtering order.

## Focused Evidence

- Baseline focused check before this patch:
  `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `2 test files, 608 assertions, 0 failures`.
- `php -l lanes/gitoxide/tests/AttributesPathspecTest.php` reported no syntax
  errors.
- `php -l lanes/gitoxide/examples/wordpress-attributes-pathspec.php` reported
  no syntax errors.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  passed `1 test files, 324 assertions, 0 failures`.
- Focused attributes/pathspec family:
  `php tools/run-tests.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `2 test files, 625 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-attributes-pathspec.php` exited 0.
- Full Gitoxide lane after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 8912
  assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
attribute stack, pathspec parser/search, WordPress example fixture, and focused
test harness. It does not shell out to Git, run Cargo, contact live remotes, or
read credentials.

## Non-Overlap

This slice only covers valid bracket-class pathspec fallback plus attribute
filter gating. It does not repeat accepted malformed-bracket pathspec fallback,
POSIX class parsing, reversed ranges, dangling backslashes, double-star
handling, whitespace pathspecs, selected attribute assignments, tree-walk
pathspecs, sparse-checkout, reference transactions, smart HTTP/SSH transport,
object database, pack/index, merge-base, URL/refspec, or protocol parity work.
