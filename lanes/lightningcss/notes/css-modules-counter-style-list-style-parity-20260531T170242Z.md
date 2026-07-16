# CSS Modules Counter-Style/List-Style Parity

- Slice: `lightningcss-css-modules-local-global-compose-parity-20260531T170242Z`.
- Upstream source truth: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_css_modules` counter-style/list-style cases plus `src/css_modules.rs` custom-ident export semantics.
- Bounded behavior: CSS Modules now scopes `@counter-style` names and `list-style` / `list-style-type` custom counter-style references, marks referenced counter-style exports, preserves built-in list-style keywords such as `disc`, `none`, and `square`, honors `customIdents => false` by keeping authored CSS public while still reporting referenced exports, and keeps dependency `composes` metadata on the owning local class.

## Evidence

- Pre-fix behavior left counter-style/list-style names public and did not export referenced counter-style metadata.
- Pinned local NAPI oracle confirmed `@counter-style circles` prints as `@counter-style EgL3uq_circles`, `list-style: circles` and `list-style-type: circles` reference `EgL3uq_circles`, built-in `square circles` does not reference the later token, and `customIdents: false` keeps CSS public while reporting the referenced export.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 127 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2496 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- Root harness status: not run - isolated micro-slice.

## Coverage Delta

- Focused CSS Modules test evidence moved from `120` to `127` assertions.
- Full LightningCSS PHP evidence moved from `2489` to `2496` pass / `0` fail.
- Conservative mapped upstream coverage moves from `1539 / 3532` to `1541 / 3532` for the direct counter-style/list-style CSS Modules helper cases.

## Non-Overlap

This does not repeat accepted CSS Modules hash/content-hash, escaped dependency specifier, escaped local selector, pure-mode selector boundary, functional local composes rejection, view-transition scoping, missing-export bundler, dashed-ident import graph, local/global selector-list validation, or animation/keyframes scoping slices. It closes the unhandled counter-style/list-style custom-ident subset inside the upstream CSS Modules cluster.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local `CssModulesTransformer`, native CSS identifier decoding, selector/export metadata, `NestingTransformer`, and `CssMinifier`; no Node, Rust, WASM, browser service, or external parser is required at runtime.
