# Custom At-Rule Visitor Factory Dependency Parity 2026-05-31T16:10Z

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, specifically:

- `node/test/composeVisitors.test.mjs` `visitor function`, where composed
  visitor factories receive `addDependency` and remove unknown dependency
  at-rules.
- `node/test/visitor.test.mjs` `visitor function works with bundler`, where
  visitor factories collect dependencies after import resolution.

Native PHP delta:

- `CustomAtRuleTransformer::composeVisitors()` now accepts visitor factories
  as well as visitor arrays. Factories receive a bounded context with
  `addDependency`, and the resolved visitor arrays are composed through the
  existing custom/unknown/style/function/value visitor pipeline.
- Added `transformWithDependencies()` and `bundleWithDependencies()` while
  keeping `transform()` and `bundle()` string-returning compatibility.
- The WordPress custom at-rule smoke now collects `@asset` and `@asset-style`
  dependencies via visitor factories instead of side-channel arrays.

Evidence:

- Baseline focused run before this slice:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 39 assertions, 0 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/lightningcss/tests/CustomAtRuleTransformerTest.php`
  => `1 test files, 43 assertions, 0 failures`.
- Full lane:
  `php tools/run-tests.php lanes/lightningcss/tests`
  => `13 test files, 2096 assertions, 0 failures`.
- Example smoke:
  `php lanes/lightningcss/examples/wordpress-custom-at-rules-transformer.php --self-test`
  exits `0`.
- Full upstream Rust/Node/WASM runners: not run for this isolated micro-slice.

Coverage accounting:

- Conservative mapped coverage moves from `1349 / 3532` to `1351 / 3532`.
- Local LightningCSS PHP assertions move from `2092` to `2096`.

Dependency closure:

- No new support component is needed. This reuses `CustomAtRuleTransformer`,
  the existing visitor composition pipeline, `CssBundler`, declaration parsing,
  and native minification helpers; no external parser, browser, Node, Rust, or
  shell-out is introduced.

Non-overlap:

- This does not repeat accepted custom at-rule parser, custom/unknown/token,
  style, FunctionExit/Length, env()/var(), SourceProvider, CSS Modules escaped
  identifier, text-decoration CSSOM, media-range, or SourceMap project-root
  slices.
- The stale lane rework note about `CustomMediaTransformer` import-tail
  conflicts was inspected; the current accepted lane already includes that
  custom-media scanner cluster, so this handoff stays on visitor-factory
  dependency parity for the assigned custom at-rule slice.

Next task:

- Continue visitor parity with `Declaration`, `DeclarationExit`, `Selector`,
  `DashedIdent`, `CustomIdent`, `StyleSheet`/`StyleSheetExit`, or URL visitor
  shapes, or pivot to source-map/bundler/CSS Modules/media-query/CSSOM
  clusters with focused PHP evidence.
