# CSS Modules Local/Global Namespace Delimiter Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T080105Z`

## Upstream Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: CSS Modules selector rewriting in `src/css_modules.rs`, exercised through the pinned native Node transform.
- Native upstream oracle spot checks:
  - `:local(.foo|.bar)`, `:global(.foo|.bar) .card`, `.foo|.bar`, `.foo|bar`, `#foo|bar`, `foo|.bar`, and `:global(.foo) || .card` reject with namespace/delimiter diagnostics.
  - Valid namespace type selectors such as `foo|bar .card`, `*|bar .card`, and `|bar .card` still scope the trailing CSS Module class.
  - Forgiving selector lists omit invalid namespace-delimiter branches, e.g. `.card:has(.foo|.bar, .ok)` keeps only `.ok` and does not export the dropped local names.

## Implementation

- `CssModulesTransformer::rewriteSelectorFragment()` now validates raw namespace/column `|` delimiters before accepting class/id-local rewrites.
- The guard preserves valid namespace type-selector forms and still lets forgiving selector functions catch and drop invalid branches before exports are committed.
- The WordPress CSS Modules transformer smoke now checks that a namespaced-looking public block class split, `:global(.wp-block|.button)`, is rejected instead of serialized.

## Verification

- PHP lint:
  - `php -l lanes/lightningcss/src/CssModulesTransformer.php`
  - `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php`
  - all reported no syntax errors
- Focused test: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `1 test files, 479 assertions, 0 failures`
- Full LightningCSS lane: `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6824 assertions, 0 failures`
- Example smoke: `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test`
  - `OK`
- Whitespace: `git diff --check -- lanes/lightningcss`
  - exit 0

## Coverage And Non-Overlap

- `phpPass` moves from `6808` to `6824` in `lanes/lightningcss/lane-status.json`.
- Conservative mapped coverage remains `2360 / 3532`; this deepens the already represented CSS Modules selector/local/global/composes cluster rather than claiming a new upstream denominator row.
- This avoids accepted CSS Modules local/global selector-list rejection, escaped selector delimiters, pseudo-element boundaries, host/slotted, WebVTT cue, nth-child, source-index compose cycles, conditional compose rejection, source-map, media-query, CSSOM, property-value, custom at-rule, and target-prefixing clusters.

## Dependency Closure

- No new support component is needed. This reuses the native PHP CSS Modules selector scanner and existing transformer/export metadata representation.
