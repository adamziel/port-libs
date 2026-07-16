# CSS Modules Animation Timeline Dashed Ident Parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260601T152533Z`

## Source Truth

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream files consulted:
  - `src/properties/animation.rs`
  - `src/values/ident.rs`
  - `src/printer.rs`
- Upstream behavior: `animation-timeline` dashed identifiers are serialized through the CSS Modules dashed-ident printer, producing a dashed export with `isReferenced: false`. In `animation` shorthand, a dashed identifier after the animation name is treated as the timeline and scoped through dashed-id exports; a first-position dashed identifier remains the animation name and is scoped through animation exports.

## Behavior Ported

- `CssModulesTransformer` now scopes bare dashed identifiers in `animation-timeline` longhand values when `dashedIdents` is enabled.
- `CssModulesTransformer` now scopes post-name dashed timeline tokens in `animation` shorthand, including the upstream `animation: false, dashedIdents: true` split-config case.
- First-position dashed animation names are preserved as animation-name exports, not dashed timeline exports.
- Quoted reserved animation names such as `"none"` still count as names, so following dashed tokens are scoped as timelines.
- `CssMinifier` now preserves dashed animation timeline custom-ident case instead of lowercasing scoped CSS Modules names.

## Focused Evidence

- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `1 test files, 653 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8444 assertions, 0 failures`
- `php -l lanes/lightningcss/src/CssModulesTransformer.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/src/CssMinifier.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/examples/wordpress-css-modules-animation-timeline.php`
  - `No syntax errors detected`
- `php lanes/lightningcss/examples/wordpress-css-modules-animation-timeline.php --self-test`
  - `OK`
- `git diff --check -- lanes/lightningcss`
  - passed with no output

## Non-Overlap

This slice does not repeat accepted CSS Modules transition-property, position-try, view-transition, grid, list-style, nested local/global, or unknown at-rule composes behavior. It targets the upstream animation timeline dashed-ident printer path that was not mapped in the PHP CSS Modules transformer.

## Dependency Closure

No new support component is needed. The slice reuses existing CSS Modules hashing/export plumbing, declaration rewriting, animation value minification, and the existing PHP test runner.
