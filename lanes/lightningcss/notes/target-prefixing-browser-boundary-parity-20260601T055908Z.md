# LightningCSS Target Prefixing Selector-List Autofill Boundary Parity

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T055908Z`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source file: `/home/claude/port-libs/.upstream-cache/lightningcss/src/lib.rs`.
- Upstream cases: `test_selectors` prefix tests around selector lists combining `.foo:placeholder-shown .bar` with `.foo:autofill .baz`.
- Native oracle at the pinned NAPI build emits `:-webkit-any(.foo:placeholder-shown .bar,.foo:-webkit-autofill .baz)` plus `:is(.foo:placeholder-shown .bar,.foo:autofill .baz)` for Chrome 109, and preserves the unprefixed selector list at Chrome 110.

## Implementation

- `TransitionPrefixer` now handles comma selector lists in the target-prefix path instead of returning early.
- For selector-list targets that still require WebKit `:autofill` and can use modern forgiving selector grouping, it emits the upstream `:-webkit-any(...)` and `:is(...)` wrapper pair.
- Older selector-list fallback targets still split each selector through the existing single-selector prefix pipeline, preserving the existing older-browser behavior without forcing `:is()`.
- `examples/wordpress-selector-target-prefixer.php` now includes a WordPress search input selector-list smoke for Chrome 109 versus Chrome 110.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/examples/wordpress-selector-target-prefixer.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1026 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6325 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-selector-target-prefixer.php --self-test`
  - `selector target prefixer example self-test passed`
- `git diff --check -- lanes/lightningcss`
  - exited `0`
- JSON validity check for `lane-status.json`
  - decoded successfully

## Non-Overlap

This does not repeat accepted standalone selector pseudo boundaries for `::placeholder`, `:autofill`, `:fullscreen`, `::backdrop`, `::file-selector-button`, `:any-link`, `:read-only`, or `:read-write`. It only closes the upstream selector-list boundary where an unsupported `:autofill` branch would invalidate an otherwise valid comma selector list unless LightningCSS wraps the prefixed and unprefixed variants.

## Dependency Closure

No new support component is needed. This slice reuses native PHP target parsing, selector splitting, selector pseudo replacement helpers, and the existing WordPress selector prefixer example harness. No Node, Rust, WASM, browser service, or external CSS engine is required at runtime.
