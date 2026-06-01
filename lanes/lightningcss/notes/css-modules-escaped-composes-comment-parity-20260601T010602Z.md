# CSS Modules Escaped Composes Comment Parity

## Source Truth

- Upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant source: `src/properties/css_modules.rs::Composes::parse`, where comments are token whitespace in CSS declaration values, and `src/css_modules.rs::CssModule::handle_composes`, where local, global, and dependency references are recorded from parsed `composes` names.
- Local NAPI oracle spot-check: escaped `c\6f mposes` property names with `/* comment */` between compose identifiers export separate local/global/dependency references, not a concatenated class name.

## Behavior

- `CssModulesTransformer::stripComments()` now recognizes escaped CSS property names before deciding whether comments are inside a `composes` declaration value.
- Comments inside `c\6f mposes` / `C\6f MPOSES` values are preserved as token separators before the regular composes parser runs.
- Added focused local, global, and dependency assertions for escaped composes property names with comment-separated identifiers.
- Updated the WordPress escaped CSS Modules smoke so block module composition covers local layout classes, public utility classes, and dependency classes separated by comments.

## Evidence

- Red-first PHP spot-check before the fix collapsed `base/* comment */tone` under escaped `c\6f mposes` into a single `EgL3uq_basetone` local reference, while the pinned upstream native artifact exported `EgL3uq_base` and `EgL3uq_tone` separately.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php`
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-css-modules-escaped-composes-property.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php`: `1 test files, 351 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 5256 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-escaped-composes-property.php --self-test`: `OK`.
- `git diff --check -- lanes/lightningcss`: passed.

## Coverage Delta

- Focused CSS Modules assertions moved from `342` to `351` (`+9`) for local/global/dependency comment separator cases under escaped composes property names.
- Full LightningCSS lane evidence moved from `5247` to `5256` assertions.
- Conservative mapped coverage remains `2248 / 3532`; this deepens the existing CSS Modules local/global/composes and escaped composes-property cluster rather than claiming a new upstream denominator row.

## Dependency Closure

No new support component is needed. This reuses the lane-local CSS Modules transformer, CSS identifier decoder, comment scanner, composes parser, minifier/nesting path, and existing PHP example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

## Non-Overlap

This does not repeat accepted CSS Modules local/global selector-list validation, nested global/local mode precedence, escaped selector identifiers, escaped dependency specifiers, functional `:local()` composes rejection, pure-mode boundaries, declaration-priority `composes`, normal unescaped comment separators, grid/container/scope/view-transition/animation/dashed-ident behavior, unused-symbol pruning, or bundle import graph work. The stale May 25 `CustomMediaTransformer` rework note was inspected and is unrelated to this CSS Modules slice.

## Next

Continue CSS Modules parity on non-overlapping selector/value integration, dependency graph flattening, or unused-symbol boundaries not covered by accepted escaped composes property, local/global/composes, dashed-ident, and bundle-import slices.
