# LightningCSS CSS Modules Pure License No-Check Parity 2026-05-31T18:11Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T181155Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine reads:
  - `src/lib.rs` pure CSS Modules selector tests around `/*! some license */ /* cssmodules-pure-no-check */ :global(.foo) { ... }`.
  - `src/stylesheet.rs`, where leading license comments are collected before parsing, while a later plain `cssmodules-pure-no-check` comment disables pure mode for the following rule.

Implementation:

- `CssModulesTransformer::stripComments()` now preserves `/*! ... */` license comments instead of dropping them during the CSS Modules comment-strip pass.
- Plain `cssmodules-pure-no-check` comments still become the internal no-check marker, so the following `:global(...)` selector bypasses pure-mode validation.
- License comments are prepended after CSS Modules rewriting and nesting/minification, matching the existing lane-local license-comment minifier behavior.
- The focused test also keeps a later pure-mode local rule with `composes: base`, proving the no-check marker does not leak past the following global rule and does not corrupt local composes metadata.
- `wordpress-css-modules-transformer.php` now models a build-free block module with a preserved license comment, a public WordPress selector protected by `cssmodules-pure-no-check`, and a later local composed class export.

Evidence:

- Pre-fix PHP spot-check for `/*! some license */ /* cssmodules-pure-no-check */ :global(.foo){color:red}` emitted `.foo{color:red}` and dropped the license comment.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` passed: `1 test files, 159 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 2883 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` passed: `OK`.
- `git diff --check -- lanes/lightningcss` passed.
- Root harness status: not run - isolated micro-slice.

Status delta:

- Full LightningCSS PHP evidence moves from `2881` to `2883 pass / 0 fail`.
- Conservative mapped coverage moves from `1637 / 3532` to `1638 / 3532`.

Dependency closure:

- No new support component is needed. This reuses the lane-local CSS Modules selector scanner, pure-mode marker handling, comment stripper, `NestingTransformer`, and `CssMinifier` output path. No Node, Rust, WASM, browser service, or external CSS parser is required at runtime.

Non-overlap:

- This does not repeat accepted CSS Modules selector-list validation, nested global/local mode precedence, escaped identifiers/specifiers, functional `:local()` composes rejection, top-level pure boundaries, `@scope` prelude pure validation, animation/keyframes scoping, counter-style/list-style export scoping, container-query name scoping, view-transition scoping, content-hash patterning, dashed-ident dependency graphs, missing-export bundle flattening, or bundler import graph behavior. It only closes the leading license comment plus `cssmodules-pure-no-check` pure-mode selector boundary while preserving later local composes exports.
