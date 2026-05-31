# Sparse Checkout Default Pathspec Mode Parity - 2026-05-31

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260531T111028Z`

Base accepted HEAD: `efb7686a64aa17164b1273c5c931fa92a9a94c6c`

## Upstream Source Truth

- `gix-pathspec/src/defaults.rs`: `Defaults` maps Git pathspec defaults for literal pathspecs, no-glob literal matching, glob/path-aware matching, and inherited `icase`.
- `gix-pathspec/src/parse.rs`: literal defaults bypass pathspec magic parsing, while default search modes still parse magic and let explicit `:(glob)` / `:(literal)` override the default.
- `gix-pathspec/src/pattern.rs` and `gix-pathspec/src/search/init.rs`: normalized prefixes remain case-sensitive even when a pathspec is matched case-insensitively.
- `gix/tests/gix/repository/pathspec.rs`: repository pathspec defaults inherit `icase` from config and still reject nonmatching prefixes.

## Native PHP Delta

- `SparseCheckoutSpec::fromPathspecs()` now accepts `defaultSearchMode` with Gitoxide-shaped shell-glob, path-aware glob, and literal/no-glob modes.
- `SparseCheckoutSpec::fromPathspecs(..., literalDefault: true)` now treats inputs such as `:(glob)...` and `:` as literal path bytes instead of pathspec magic.
- Explicit long magic still overrides default no-glob/path-aware behavior, matching upstream `gix-pathspec::Defaults`.
- Non-cone pathspec matching no longer lowercases the full candidate path globally for inherited ignore-case. Per-rule `icase` still folds the actual pattern comparison, while normalized caller/absolute prefixes remain case-sensitive.
- `examples/wordpress-sparse-checkout.php` now records default path-aware, no-glob, and literal-default deployment pathspec behavior for WordPress plugin selection.

## Verification

- Red-first precheck before implementation:
  - `SparseCheckoutSpec::fromPathspecs(["wp-content/plugins/*.php"], defaultSearchMode: "literal")` failed with `Unknown named parameter $defaultSearchMode`.
  - After the first implementation pass, the absolute literal `icase` prefix check incorrectly included `WP-CONTENT/*/README.md`; this exposed and fixed the global non-cone lowercasing bug.
- Syntax:
  - `php -l lanes/gitoxide/src/SparseCheckoutSpec.php`
  - `php -l lanes/gitoxide/tests/SparseCheckoutTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`
- Focused sparse checkout: `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `1 test files, 162 assertions, 0 failures`.
- Related pathspec/tree guard: `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `2 test files, 246 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `39 test files, 4356 assertions, 0 failures`.
- Example smoke:
  - `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; ...'`
  - Result: `sparse default pathspec example ok`.
- Whitespace check: `git diff --check -- lanes/gitoxide`
  - Result: no output, exit 0.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP path normalization and regex-based pathspec matching; no upstream binary, live Git provider, credential store, or shared support-library activation gate is required.

## Non-Overlap

This extends accepted sparse checkout/pathspec work without repeating cone rules, non-cone pattern-file ordering, wildcard bracket/POSIX matching, directory-only excludes, cwd prefix normalization, absolute-root normalization, tree pathspec walking, attributes/pathspec filters, protocol, pack, object, or reference behavior. The new behavior is limited to upstream `gix-pathspec::Defaults` search-mode parity and case-sensitive prefix preservation under inherited `icase`.
