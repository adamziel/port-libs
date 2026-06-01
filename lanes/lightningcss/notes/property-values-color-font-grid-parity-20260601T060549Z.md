# LightningCSS Property Values Color/Font/Grid Parity 2026-06-01T060549Z

- Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_container`, helper case `@container style(color: yellow !important)`.
- Red-first probe on base: `php -r 'require "tools/bootstrap.php"; ... @container style(color: yellow !important) ...'` returned `@container style(color:#ff0){.foo{color:red}}`; upstream expects `@container style(color:yellow){.foo{color:red}}`.
- Behavior: container style queries still minify ordinary color declarations such as `style(color: yellow)` to `style(color:#ff0)`, but important style-query declarations now strip `!important` without applying the extra color-keyword compression pass.
- WordPress smoke: added `wordpress-container-style-important-color.php` to model block button container style queries. It verifies the important query stays `style(color:yellow)` while the non-important query remains `style(color:#ff0)`.
- Dependency closure: no new support component is needed; this reuses the native PHP `CssMinifier` container-query parser/minifier.
- Non-overlap: avoids accepted CSS Modules, CSSOM, custom at-rule, media-query range, source-map, alpha-color fallback, font target fallback, and direct grid/font/color-minifier clusters. This slice is limited to the remaining upstream container style-query important color parity case.
