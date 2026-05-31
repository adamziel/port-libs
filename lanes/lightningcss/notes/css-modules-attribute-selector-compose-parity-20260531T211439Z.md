# CSS Modules Attribute Selector / Compose Parity

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant upstream surfaces: `src/lib.rs::test_css_modules`, selector serialization in `src/selector.rs`, and CSS Modules scoping/export behavior in `src/css_modules.rs`.
- Native NAPI spot check at the pinned commit:
  - `.foo[data-x=".bar"]{color:red}` serializes to `.EgL3uq_foo[data-x=\.bar]{color:red}`.
  - `.foo[data-x="bar baz"]{color:red}` serializes to `.EgL3uq_foo[data-x=bar\ baz]{color:red}`.
  - `.foo[class~="bar"]{color:red}` serializes to `.EgL3uq_foo[class~=bar]{color:red}`.
  - `.foo[data-x="Hello, world!"]{color:red}` remains quoted.
  - `.foo[data-x=.bar]{color:red}` is rejected with `Invalid value in attribute selector: Delim('.')`.
  - With `minify: false`, `.foo[data-x=".bar"] { color: red }` preserves the authored quoted attribute value.

## Implementation

This isolated slice deepens the already represented CSS Modules local/global/composes cluster. Native PHP now normalizes attribute selector values while rewriting minified local and global CSS Modules selectors, including quoted values that can be emitted as escaped identifiers, `~=` attribute operators, escaped whitespace after minification, and quoted values that must stay quoted. Invalid unquoted delimiter values are rejected before CSS is emitted, while `minify: false` preserves authored quoted attribute selector values like upstream.

The patch also preserves CSS escapes in the shared minifier so escaped attribute selector identifiers such as `bar\ baz` are not collapsed by whitespace removal before the CSS Modules post-minify selector pass.

## Evidence

- Red-first gap: before this patch, PHP preserved quotes for values such as `[data-x=".bar"]` and accepted invalid unquoted values like `[data-x=.bar]`, while upstream minified or rejected them.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php`: `1 test files, 273 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 4395 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-attribute-selectors.php --self-test`: `OK`.
- Syntax checks passed for `CssModulesTransformer.php`, `CssMinifier.php`, `CssModulesTransformerTest.php`, and `wordpress-css-modules-attribute-selectors.php`.
- `git diff --check -- lanes/lightningcss`: passed.
- Root harness status: not run - isolated micro-slice.

## Non-Overlap And Dependencies

This does not repeat the accepted CSS Modules host-context, pseudo-element boundary, escaped selector, pure-mode, unused-symbol, bundle-composes, or CSS Modules source-map slices. It only deepens attribute selector serialization inside the existing local/global/composes behavior cluster, so conservative mapped coverage remains unchanged at `2117 / 3532` while lane-focused PHP assertions move from `4390` to `4395`.

Dependency closure: no new support component is needed; this reuses the bounded `CssModulesTransformer`, `CssMinifier`, `NestingTransformer`, selector scanner, CSS string decoder, and CSS identifier escape/token readers.

## Next

Next CSS Modules work should target remaining upstream-backed gaps such as exported dashed-ident interaction across bundler/source-map paths, parser recovery around invalid nested selectors, or additional `composes/from` import graph diagnostics rather than another status-only CSS Modules note.
