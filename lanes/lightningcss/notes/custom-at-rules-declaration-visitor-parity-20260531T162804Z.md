# Custom At-Rule Declaration Visitor Parity 2026-05-31T16:28Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically:

- `node/test/composeVisitors.test.mjs` `different properties`, `properties
  plus values`, `unparsed properties`, `returning unparsed properties`, `all
  property handlers`, and `all property handlers (exit)`.
- `node/test/visitor.test.mjs` `size`, where a `Declaration.custom.size`
  visitor expands a custom declaration into width/height declarations.

Native PHP delta:

- `CustomAtRuleTransformer::composeVisitors()` now composes bounded
  `Declaration` and `DeclarationExit` visitors alongside the existing
  custom/unknown/style/function/token/value visitor pipeline.
- Declaration values are exposed to visitors as upstream-shaped token/value
  arrays for custom declarations, known declarations, and unparsed known
  declarations such as `width: test`.
- Returned declaration arrays now serialize `unparsed`, `length-percentage`,
  `var()`, `function`, and `rgb` value shapes, and structured length
  replacements pass through the existing `Length` visitor.
- The WordPress custom at-rule smoke now models a build-free `size: 48px`
  declaration utility expanded by `Declaration.custom.size`.

Evidence:

- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 43 assertions, 0 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 51 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 2176 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test`
  exits `0`.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `1370 / 3532` to `1376 / 3532`.
- Local LightningCSS PHP assertions move from `2168` to `2176`.

Dependency closure:

- No new support component is needed. This reuses `CustomAtRuleTransformer`,
  `DeclarationBlock`, the existing native scanner helpers, and lane-local
  minification/value serialization. No external parser, browser, Node, Rust,
  or shell-out is introduced.

Non-overlap:

- This avoids accepted custom at-rule parser, custom/unknown/token, style,
  FunctionExit/Length-only, env()/var(), visitor-factory dependency,
  SourceProvider, CSS Modules escaped identifier, text-decoration CSSOM,
  media-range, and SourceMap project-root slices. The stale lane rework note
  about `CustomMediaTransformer` import-tail conflicts was inspected and left
  untouched because this handoff stays on custom at-rule declaration visitors.

Next task:

- Continue visitor parity with `StyleSheet`/`StyleSheetExit`,
  `DashedIdent`/`CustomIdent`, URL, selector, or broader declaration visitor
  shapes not covered by this bounded declaration/custom-at-rule slice.
