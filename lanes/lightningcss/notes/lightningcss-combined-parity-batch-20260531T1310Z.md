# LightningCSS Combined Parity Batch 2026-05-31T13:10Z

Accepted from isolated worker handoffs with stale shared metadata excluded:

- CSSOM border longhand removal: removes direct border longhands and splits `border`, `border-color`, and physical side shorthands into surviving component longhands.
- Property values Color 4 basics: minifies `hwb()`, bare `hsl()`/`hwb()` percentages, `none` components, system colors, and identical `light-dark()` hex arms.
- Target prefixes: adds upstream-aligned `user-select` and `appearance` browser-boundary prefix behavior.
- Custom at-rules: extends parser/visitor replacement coverage for custom rule arrays and composed visitors.
- CSS Modules: rejects bare nested `:global` / `&:global` selector mode pseudos while preserving functional local/global forms.
- Bundler/minifier comments: preserves `/*! ... */` license comments while dropping ordinary comments across minification and import graphs.
- Media query ranges: lowers range syntax for legacy targets, including recursive `@layer` wrapping, while preserving modern target boundaries.

Verification in the integration worktree:

- PHP lint on changed/new LightningCSS PHP files.
- Focused gate: `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php lanes/lightningcss/tests/CssMinifierTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php lanes/lightningcss/tests/CssModulesTransformerTest.php lanes/lightningcss/tests/CssBundlerTest.php lanes/lightningcss/tests/MediaQueryParserTest.php` => `7 files / 1178 assertions / 0 failures`.
- Full LightningCSS PHP lane: `php tools/run-tests.php lanes/lightningcss/tests` => `13 files / 1339 assertions / 0 failures`.
- Examples: `wordpress-border-cssom.php`, `wordpress-bundle-import-graph.php`, `wordpress-css-modules-transformer.php`, `wordpress-custom-at-rules-transformer.php`, `wordpress-target-prefix-boundaries.php`, `wordpress-color-value-minifier.php`, and `wordpress-media-range-layer-prefixer.php` all exit successfully.
- `git diff --check` passes.

Mapped coverage delta is conservatively counted as +46 checks: +23 property-value Color 4, +6 target prefix UI boundaries, +4 custom at-rules, +1 CSS Modules CLI regression, +3 license-comment bundler/minifier behavior, +9 media range fallback cases, and +0 for CSSOM border removal because it extends an already represented DeclarationBlock cluster.
