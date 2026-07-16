# Sparse checkout absolute backslash pathspec parity

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260601T013633Z`

Base accepted HEAD: `388d75493f253681c7862bcbbc85820a181fa9e0`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/pattern.rs`
  preserves Unix backslash bytes while normalizing a pathspec relative to a
  repository root. It only converts platform separators with
  `to_unix_separators_on_windows()` after the relative path is computed.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  computes directory traversal for wildcard and escaped pathspecs from the
  first wildcard/escape byte and the rightmost slash before that boundary.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/parse.rs`
  treats `\` as a glob metacharacter, while `/` remains the special path
  separator.

## Native PHP delta

- `SparseCheckoutSpec::pathRelativeToRoot()` no longer rewrites Unix
  backslash bytes in absolute pathspecs into `/`.
- Wildcard pathspec directory traversal now keeps the upstream conservative
  parent-directory prefix before the first wildcard or escape byte, while still
  requiring the common literal prefix to match.
- `SparseCheckoutTest.php` covers absolute literal, `:(glob)`, and ordinary
  shell-glob pathspecs whose bytes contain `\`, including slash-path rejection
  and conservative tree traversal.
- `wordpress-sparse-checkout.php` now exercises the same absolute-backslash
  sparse checkout behavior in the lane example smoke.

## Red-first probe

Before the change, an absolute Unix sparse pathspec such as
`/repo/wp-content/plugins/f\oo/block.json` was normalized to
`wp-content/plugins/f/oo/block.json` and could include the slash path while
missing the literal backslash path. Escaped glob traversal also excluded the
parent directory too early because it used the raw escaped-byte prefix as the
directory prefix.

## Verification

- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php` passed with no syntax
  errors.
- `php -l lanes/gitoxide/tests/SparseCheckoutTest.php` passed with no syntax
  errors.
- `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php` passed with
  no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "gitoxide json ok\n";'`
  reported `gitoxide json ok`.
- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `1 test files, 296 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `2 test files, 462 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 6746
  assertions, 0 failures`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; foreach (["absoluteBackslashLiteralIncluded", "absoluteBackslashLiteralSlashSkipped", "absoluteBackslashGlobIncluded", "absoluteBackslashGlobSlashSkipped", "absoluteBackslashOrdinaryEscapesNextByte", "absoluteBackslashOrdinaryVerbatimFallbackIncluded"] as $key) { if (($out[$key] ?? null) !== true) { fwrite(STDERR, $key . " failed\n"); exit(1); } } echo "sparse absolute backslash example ok\n";'`
  reported `sparse absolute backslash example ok`.
- `git diff --check -- lanes/gitoxide` exited `0`.

Focused assertion delta: `SparseCheckoutTest.php` moved from `282` to `296`
assertions. Full Gitoxide lane verification moved from `6732` to `6746`
assertions. Conservative mapped coverage moved from `1696 / 2886` to
`1697 / 2886`.

## Non-overlap

This slice extends accepted sparse-checkout parity beyond cone rules, relative
backslash-byte sparse pathspecs, absolute-root pathspec normalization without
backslashes, absolute wildcard icase prefixes, default and environment search
modes, POSIX classes, double-star component boundaries, negative wildcard
traversal, tree pathspec walks, attributes, transport, protocol, pack/object,
reference, and merge behavior.

## Dependency closure

No new support component is needed. The patch reuses the lane-local sparse
pathspec parser, wildmatch/PCRE matcher, tree filtering, and WordPress sparse
checkout example.
