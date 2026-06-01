# CSS Modules Transition-Property Local/Global Compose Parity

Slice: `lightningcss-css-modules-local-global-compose-parity-20260601T134911Z`

Upstream source truth:
- Pinned upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Source: `src/lib.rs::test_css_modules` direct `css_modules_test` case for `test { transition-property: opacity; }`
- Supplemental NAPI oracle at the pinned commit confirms CSS Modules scopes `.card` and `var(--card-motion)` with `dashedIdents`, but leaves `transition-property: opacity, transform, --card-motion` as public property ids rather than CSS Modules custom-ident exports.

Implemented behavior:
- `CssModulesTransformer` now canonicalizes CSS identifier escapes in `transition-property` values, including `op\61 city -> opacity`.
- `transition-property` values remain property ids, so they are not scoped or exported as CSS Modules custom idents.
- Existing local/global selector rewriting, local `composes`, and dashed `var()` export/reference behavior remain active in the same rule.

Focused evidence:
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `1 test files, 609 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8111 assertions, 0 failures`

Dependency closure:
- No new support component is needed. The slice reuses the existing CSS declaration scanner, top-level comma splitter, CSS identifier decoder, and CSS identifier serializer in the LightningCSS PHP lane.

Non-overlap:
- Avoids the accepted CSSOM transition-property read/write cluster and target-prefix transition-property expansion clusters.
- This is CSS Modules-only parity for upstream property-id serialization inside CSS Modules local/global/composes output.

Next task:
- Continue CSS Modules parity on remaining upstream custom-ident boundaries where declarations use property-id grammar beside local/global selectors and composes metadata.
