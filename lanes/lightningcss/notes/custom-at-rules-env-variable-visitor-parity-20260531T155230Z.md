# Custom At-Rule Env/Variable Visitor Parity 2026-05-31T15:52Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically:

- `node/test/composeVisitors.test.mjs` `environment variables`, where
  composed `EnvironmentVariable` visitors rewrite `env()` in a media query
  prelude and declaration value.
- `node/test/composeVisitors.test.mjs` `variables`, where composed `Variable`
  visitors rewrite `var()` declaration values.
- `node/test/visitor.test.mjs` `spacing with env substitution`, for
  upstream-style `{ raw: ... }` replacement serialization in the same
  value-visitor cluster.

Native PHP delta:

- `CustomAtRuleTransformer::composeVisitors()` now composes
  `EnvironmentVariable` and `Variable` visitors by exact custom name or generic
  handler.
- Declaration value rewriting recognizes `env()` and `var()` before generic
  function visitors and applies later `Length` visitors to structured length
  replacements.
- Unknown block at-rule fallback now emits through the shared unknown-rule
  serializer so media preludes can receive value visitor rewrites before the
  existing `MediaQueryParser` minifier normalizes `max-width` to range syntax.
- Upstream-style `{ raw: "..." }` value replacements serialize through the
  existing declaration/minifier pipeline.
- The WordPress custom at-rule smoke now models `@env-token` and `@var-token`
  collection feeding `env()` / `var()` replacements in a block stylesheet.

Red/green evidence:

- Pre-change probe from the accepted base left env replacements unresolved:
  `@media (width<=env(--branding-small)){body{padding:env(--branding-padding)}}`.
- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 34 assertions, 0 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 39 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 2030 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test`
  exits `0`.
- `php -l` passed on the changed PHP source, test, and example files.
- `git diff --check -- lanes/lightningcss` passed.
- Full upstream Rust/Node runners: not run for this isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `1340 / 3532` to `1342 / 3532`.
- Local LightningCSS PHP assertions move from `2025` to `2030`.

Dependency closure:

- No new support component is needed. This reuses the existing bounded
  `CustomAtRuleTransformer`, `DeclarationBlock`, `CssMinifier`, and
  `MediaQueryParser` helpers; no external parser, browser, Node, Rust, or
  shell-out is introduced.

Non-overlap:

- This does not repeat accepted custom at-rule declaration-list/mixin/rule-list
  parser behavior, composed unknown/token/function/style/FunctionExit/Length
  visitor behavior, CSS Modules pure selector handling, alpha-color fallback,
  outline CSSOM, or malformed resolver-shape slices.
- The stale lane rework note about `CustomMediaTransformer` import-tail
  conflicts was inspected; current accepted manifest/source already contains
  the custom-media import-tail and scanner rework cluster, so this handoff
  stays on the assigned custom at-rule visitor parity slice.

Next task:

- Continue visitor parity with selector, URL, DashedIdent, Declaration,
  StyleSheet/StyleSheetExit, or visitor-function dependency shapes, or pivot to
  source-map/bundler/CSS Modules/media-query/CSSOM property clusters with
  focused PHP evidence.
