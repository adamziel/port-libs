# Sparse Checkout Malformed POSIX Class Resume Parity - 2026-06-01

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260601T095728Z`

Base accepted HEAD: `c6000a6885bc6b5b6b4980e335c606d935a6fb65`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-glob/src/wildmatch.rs`: while evaluating a bracket class, a malformed POSIX-class opener such as `[[:digit]` matches a literal `[` byte and resumes matching the remaining pattern instead of treating the whole pathspec as only a full literal.
- `gix-glob/tests/wildmatch/mod.rs`: the corpus expects `[[:]ab]` and `[[:digit]ab]` to match `[ab]`, while `[[::]ab]` remains a non-match.
- `gix-pathspec/src/search/matching.rs`: pathspec search first tries wildmatch, then falls back to verbatim pathspec matching if wildcard matching fails.

## Native PHP Delta

- `SparseCheckoutSpec::characterClassRegex()` now emits a literal `[` match for malformed POSIX class starts that have no closing `:]` before the bracket closes.
- Sparse checkout pathspec tests now cover `[[:alpha]/photo.jpg`, `[[:digit]ab]`, `[[:]ab]`, and `[[::]ab]` so the resumed wildcard match remains distinct from the existing full-verbatim fallback.
- `examples/wordpress-sparse-checkout.php` exposes the same WordPress upload selection behavior for deployment sparse-checkout diagnostics.

## Red-First Evidence

Before the implementation:

```bash
php -r 'require "tools/bootstrap.php"; $s=PortLibs\Gitoxide\SparseCheckoutSpec::fromPathspecs([":(glob)wp-content/uploads/[[:digit]ab]"]); var_export([$s->includesPath("wp-content/uploads/[ab]", false), $s->includesPath("wp-content/uploads/[[:digit]ab]", false)]); echo PHP_EOL;'
```

Result: `array ( 0 => false, 1 => true, )`

After the implementation the same probe returns `array ( 0 => true, 1 => true, )`.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `1 test files, 368 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `40 test files, 8653 assertions, 0 failures`.
- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php`
  - Result: no syntax errors detected.
- `php -l lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: no syntax errors detected.
- `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`
  - Result: no syntax errors detected.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; foreach (["pathspecMalformedPosixOpenBracketIncluded", "pathspecMalformedPosixLiteralFallbackIncluded", "pathspecMalformedPosixDigitPrefixResumed", "pathspecMalformedPosixEmptyNamePrefixResumed", "pathspecMalformedPosixDoubleColonSkipped"] as $key) { if (($out[$key] ?? null) !== true) { fwrite(STDERR, $key . " failed\n"); exit(1); } } echo "sparse checkout malformed posix resume example ok\n";'`
  - Result: `sparse checkout malformed posix resume example ok`.
- `git diff --check -- lanes/gitoxide`
  - Result: no output, exit 0.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local sparse checkout matcher, PHP PCRE byte matching, WordPress sparse checkout example, and PHP test harness. It does not shell out to Git, run live-service provider tests, read credential stores, or require a shared support-library activation gate.

## Non-Overlap

This does not repeat accepted sparse-checkout pathspec work for directory-only excludes, negative nil roots, default search modes, pathspec environment defaults, absolute-root normalization, absolute backslash bytes, escaped-byte traversal, LF-byte wildmatch, reversed ranges, unknown POSIX class full-literal fallback, pattern-file trimming, or double-star component boundaries. It is limited to the upstream gix-glob malformed POSIX-class resume behavior as observed through sparse checkout pathspec matching.
