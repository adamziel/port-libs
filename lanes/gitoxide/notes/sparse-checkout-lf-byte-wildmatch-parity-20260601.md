# Sparse checkout LF-byte wildmatch parity

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260601T072116Z`

Base accepted HEAD: `80f68770eb80ae23d626c7edafcf276d6f4e32ec`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  treats LF as an ordinary byte in `*` and `?` matching unless path-aware mode
  is rejecting only `/`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  applies that wildmatch behavior to pathspec searches and falls back to exact
  verbatim matching only when wildcard matching fails.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/pattern.rs`
  requires the whole value to match; exact pathspecs must not match a trailing
  LF via PCRE-style end-of-line behavior.

## Native PHP delta

- `SparseCheckoutSpec::globRegex()` now emits DOTALL PCREs so sparse checkout
  pathspec shell globs match LF bytes like upstream wildmatch.
- The generated regex now uses `\z` to keep exact pathspecs anchored to the
  true end of the candidate rather than PHP's end-or-before-final-newline `$`
  behavior.
- `SparseCheckoutTest.php` adds focused LF-byte pathspec assertions for
  shell-glob `*`, shell-glob `?`, path-aware glob `?`, path-aware glob `*`,
  exact non-match boundaries, and tree-entry materialization.
- `wordpress-sparse-checkout.php` exposes the same deployment pathspec
  decisions for example smoke verification.

## Red-first probe

Before the change:

```sh
php -r 'require "tools/bootstrap.php"; $s=PortLibs\Gitoxide\SparseCheckoutSpec::fromPathspecs(["wp-content*"]); var_export($s->includesPath("wp-content\n/plugins/block.json", false)); echo PHP_EOL; $q=PortLibs\Gitoxide\SparseCheckoutSpec::fromPathspecs(["wp-content?/plugins/block.json"]); var_export($q->includesPath("wp-content\n/plugins/block.json", false)); echo PHP_EOL;'
```

reported `false` and `false`. An exact `wp-content` pathspec also incorrectly
matched `wp-content\n` because the PCRE used `$` as its end anchor.

## Verification

- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php` passed.
- `php -l lanes/gitoxide/tests/SparseCheckoutTest.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `1 test files, 355 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php lanes/gitoxide/tests/AttributesPathspecTest.php`
  passed `3 test files, 865 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 8009
  assertions, 0 failures`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; foreach (["pathspecLfByteShellStarIncluded", "pathspecLfByteShellQuestionIncluded", "pathspecLfBytePathAwareQuestionIncluded"] as $key) { if (($out[$key] ?? null) !== true) { fwrite(STDERR, $key . " failed\n"); exit(1); } } echo "sparse LF byte pathspec example ok\n";'`
  reported `sparse LF byte pathspec example ok`.
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "gitoxide json ok\n";'`
  reported `gitoxide json ok`.
- `git diff --check -- lanes/gitoxide` exited `0`.

Root harness: not run - isolated micro-slice.

## Non-overlap

This extends sparse-checkout pathspec parity without repeating accepted
tree-pathspec LF wildmatch, sparse absolute/backslash pathspec normalization,
POSIX class fallback, reversed bracket ranges, double-star component
boundaries, negative wildcard traversal, directory-only exclusions, attributes,
transport, protocol, pack/object, reference, or merge behavior.

## Dependency closure

No new support component is needed. The patch reuses the lane-local PHP
sparse-checkout pathspec parser, PCRE-backed wildmatch translator, tree
filtering, WordPress sparse-checkout example, and PHP test harness. It does not
shell out to Git, run live provider tests, read credentials, or require a
shared support activation gate.
