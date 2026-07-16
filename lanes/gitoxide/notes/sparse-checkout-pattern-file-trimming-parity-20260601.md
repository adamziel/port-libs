# Sparse Checkout Pattern File Trimming Parity

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260601T050038Z`

Base accepted HEAD: `9d2966b89133306c89e1d8c9ef9d120cd603e55f`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-index/src/access/sparse.rs` treats non-cone sparse checkout rules as ignore-pattern matches from `.git/info/sparse-checkout`.
- `gix-ignore/src/parse.rs` strips a UTF-8 BOM at the beginning of the pattern buffer, skips lines whose first byte is `#`, truncates non-escaped trailing space bytes, skips all-ASCII-whitespace patterns, and delegates leading `!`, escaped `\!`, and escaped `\#` handling to glob parsing.
- `gix-glob/src/parse.rs` preserves escaped literal leading `!` and `#`, rejects all-ASCII-whitespace patterns, and keeps escaped trailing spaces as part of the glob.

## Native Delta

- `SparseCheckoutSpec::fromNonConePatternFile()` now strips a leading UTF-8 BOM before line parsing.
- Non-escaped trailing spaces are removed before pattern parsing, while escaped trailing spaces remain matchable.
- ASCII-whitespace-only non-cone pattern lines are ignored after trailing-space handling.
- Existing leading `!` exclusion and escaped `\!` / `\#` literal handling now runs on the trimmed pattern text.
- `wordpress-sparse-checkout.php` includes a WordPress deployment smoke for BOM-prefixed `mu-plugins`, trailing-space plugin/cache rules, escaped literal marker filenames, and escaped trailing-space media paths.

## Verification

- Pre-change focused baseline: `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php` -> `1 test files, 326 assertions, 0 failures`.
- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php` -> no syntax errors.
- `php -l lanes/gitoxide/tests/SparseCheckoutTest.php` -> no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php` -> no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php` -> `1 test files, 338 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-sparse-checkout.php` -> exit 0.
- `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 7501 assertions, 0 failures`.

## Non-Overlap

This slice does not repeat accepted sparse-checkout/pathspec work for directory-type boundaries, negative wildcard directory traversal, absolute Unix backslash pathspecs, reversed bracket ranges, LF-byte wildmatch, quoted attributes, POSIX class pathspecs, double-star component boundaries, directory-only excludes, or absolute wildcard icase prefixes. It is limited to upstream non-cone sparse-checkout pattern-file parsing boundaries from `gix-ignore` / `gix-glob`.

## Dependency Closure

No new support component is needed. The existing native sparse-checkout/pathspec matcher is reused; the change is parser normalization before existing rule construction.
