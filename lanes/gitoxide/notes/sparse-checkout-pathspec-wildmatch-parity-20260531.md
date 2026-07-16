# Sparse Checkout Pathspec Wildmatch Parity - 2026-05-31

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260531T085406Z`

Base accepted HEAD: `c841d70129308d3dd7ed4bf017a34b5f5817e8d2`

## Upstream Source Truth

- `gix-pathspec/src/search/init.rs`: search initialization sorts exclude pathspecs as authoritative matches.
- `gix-pathspec/src/search/matching.rs`: sparse/worktree traversal uses positive prefix checks and all-excluded fallback conservatively.
- `gix-glob/src/wildmatch.rs`: pathspec glob matching supports bracket classes/ranges, `!`/`^` negation, escaped literals, POSIX character classes, `**/` zero-or-more directory matching, and path-aware slash refusal.
- `gix-glob/tests/wildmatch/mod.rs`: focused corpus cases include `foo[/]bar`, `[[:digit:]]`, escaped `?`, bracket ranges, and `foo/**/bar`.
- `gix-pathspec/tests/search/mod.rs`: focused search cases cover exclude precedence, directory prefix matching, empty searches, and all-excluded fallback.

## Native PHP Delta

- `SparseCheckoutSpec::fromPathspecs()` now partitions positive pathspecs before negative pathspecs so the existing last-match evaluator treats excludes as authoritative regardless of caller order.
- `SparseCheckoutSpec` now compiles sparse pathspec globs with Gitoxide-like wildmatch support for bracket classes, negated ranges, POSIX classes, escaped glob literals, `**/`, and slash-aware `:(glob)` matching.
- `normalizePathspecPath()` now preserves backslash escapes for pathspec matching instead of converting them into path separators.
- `examples/wordpress-sparse-checkout.php` records bracket pathspec inclusion, cache exclusion precedence, and recursive escaped theme pathspec matching for WordPress deployment selection.

## Verification

- Red-first precheck before implementation:
  - `SparseCheckoutSpec::fromPathspecs([":(exclude,glob)wp-content/cache/**", "wp-content/**"])` incorrectly included `wp-content/cache/page.html`.
  - `SparseCheckoutSpec::fromPathspecs([":(glob)wp-content/plugins/[ag]*/block.[jt]son"])` failed to include `wp-content/plugins/akismet/block.json`.
- Focused after fix: `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `1 test files, 91 assertions, 0 failures`.
- Full Gitoxide lane after fix: `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `34 test files, 3190 assertions, 0 failures`.
- Syntax: `php -l lanes/gitoxide/src/SparseCheckoutSpec.php && php -l lanes/gitoxide/tests/SparseCheckoutTest.php && php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`
  - Result: no syntax errors detected in all three changed PHP files.
- Example smoke: `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; ...'`
  - Result: `sparse example ok`.
- Whitespace check: `git diff --check -- lanes/gitoxide`
  - Result: no output, exit 0.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP path normalization and regex matching inside `SparseCheckoutSpec`; no shell-out, upstream binary, credential store, or shared support-library activation gate is required.

## Non-Overlap

This extends the accepted sparse checkout pathspec slice from source commit `6d9f6eff` without repeating protocol v2 `ls-refs`, config include/includeIf, attributes/pathspec filters, pack delta guards, loose object integrity, or reference peeling behavior. It narrows to pathspec search/wildmatch parity for sparse checkout traversal and skip-worktree decisions.
