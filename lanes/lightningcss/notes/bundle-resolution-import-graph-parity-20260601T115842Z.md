# LightningCSS Bundle Resolution Import Graph Parity

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T115842Z`

Source truth: upstream `parcel-bundler/lightningcss` at pinned manifest commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`.

## Behavior

Native LightningCSS rejects malformed statement-form top-level `@layer`
preludes before resolving or reading following imports:

- `@layer; @import "dep.css"`: `Unexpected token Semicolon`, `/entry.css`,
  line 1, column 8, reads only `/entry.css`.
- `@layer foo,; @import "dep.css"`: `Unexpected token Semicolon`,
  line 1, column 13, reads only `/entry.css`.
- `@layer .foo; @import "dep.css"`: `Unexpected token Delim(".")`,
  line 1, column 8, reads only `/entry.css`.
- `@layer foo bar; @import "dep.css"`: `Unexpected token Ident("bar")`,
  line 1, column 11, reads only `/entry.css`.

Valid layer-list comments remain import-transparent:
`@layer foo/*x*/,bar; @import "dep.css"` bundles successfully.

## Patch

- `CssBundler::topLevelItems()` now validates statement-form top-level
  `@layer` names before dependency collection and import graph reads.
- Invalid layer statements throw `CssBundleException` parser diagnostics with
  upstream-compatible message, source file, line, and column data.
- The WordPress import-graph example now asserts a malformed block-theme
  layer statement fails before `blocks/card.css` is read.

Non-overlap: this is limited to bundle/import graph statement-form `@layer`
validation. It does not touch source-map VLQ offsets, malformed inline
source-map suppression, import media/supports parsing, CSS Modules, CSSOM,
media-query, property-value, or target-prefix behavior.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`: no
  syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`: 1
  file, 752 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`:
  passed and printed `bad-layer-statement-import: rejected-before-read`.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 files, 7692
  assertions, 0 failures.
- `git diff --check -- lanes/lightningcss`: passed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
LightningCSS bundle parser, source-location, resolver, and reader callback
infrastructure.

## Next

Continue with non-overlapping upstream-backed LightningCSS bundle/import graph
or source-map parity, especially cases where parse-time errors must stop
resolver traversal before side effects.
