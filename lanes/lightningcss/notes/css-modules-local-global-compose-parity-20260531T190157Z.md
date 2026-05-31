# LightningCSS CSS Modules Pattern Guard Parity 2026-05-31T19:01Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T190157Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream area: `src/css_modules.rs::Pattern::parse` and the CSS Modules local/global/composes transform path.
- Local pinned NAPI oracle confirmed invalid patterns reject before output:
  - `[oops]-[local]` => `Error parsing CSS modules pattern: unknown placeholder "[oops]" at index 0`.
  - `[hash` => `Error parsing CSS modules pattern: unclosed brackets at index 0`.

Implementation:

- `CssModulesTransformer` now validates CSS Modules `pattern` values up front, accepting only `[name]`, `[hash]`, `[content-hash]`, and `[local]`.
- Invalid placeholders and unclosed brackets now throw upstream-style diagnostics before selector rewriting can emit escaped bogus classes or corrupt local/global/composes metadata.
- `wordpress-css-modules-transformer.php` now smokes a block module with `composes` plus an invalid `[block]-[local]` pattern and records the upstream error text.

Evidence:

- Red-first PHP spot-check before the fix emitted `.\[oops\]-test{...}` and returned `[oops]-test` export metadata for `.test { composes: foo }`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 182 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 3210 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness status: not run - isolated micro-slice.

Status delta:

- Focused CSS Modules transformer evidence moves from `178` to `182` assertions.
- Full LightningCSS PHP evidence moves from `3206` to `3210` pass / `0` fail.
- Conservative mapped coverage remains `1721 / 3532`; this closes source-level `Pattern::parse` behavior inside the already represented CSS Modules config/export cluster rather than a newly counted upstream helper row.

Dependency closure:

- No new support component is needed. This reuses the lane-local CSS Modules transformer, existing pattern substitution, selector scanner, composes parser, minifier/nesting output path, and WordPress example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

Non-overlap:

- This does not repeat accepted CSS Modules local/global selector-list validation, nested global/local precedence, escaped identifiers/specifiers, functional `:local()` composes rejection, pure-mode boundaries, `@scope`, grid, container, view-transition, animation/keyframes, counter-style/list-style, dashed-ident handling, content-hash/project-root hashing, missing-export bundling, or import-graph flattening. It only closes invalid CSS Modules pattern diagnostics before local/global/composes output.

Next task:

- Continue CSS Modules parity on selector-function options, unused-symbol handling, or dependency graph behavior not already covered by accepted local/global/composes, pattern validation, grid, container, scope, view-transition, animation, counter-style/list-style, dashed-ident, and bundle-import slices.
