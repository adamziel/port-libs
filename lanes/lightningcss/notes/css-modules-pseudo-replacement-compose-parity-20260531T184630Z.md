# LightningCSS CSS Modules Pseudo Replacement Compose Parity 2026-05-31T18:46Z

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T184630Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream reads: `src/lib.rs::test_pseudo_replacement`, `src/printer.rs::PseudoClasses`, and `src/selector.rs::serialize_pseudo_class`.
- Upstream behavior: when `PrinterOptions::pseudo_classes` is set, user-action pseudos serialize as classes. With CSS Modules enabled, replacement classes are also scoped and exported; inside `:global(...)`, the replacement class remains public because the CSS module context is disabled for that selector branch.

Implementation:

- `CssModulesTransformer` now accepts `pseudoClasses` and `pseudo_classes` options for `hover`, `active`, `focus`, `focusVisible` / `focus_visible`, and `focusWithin` / `focus_within`.
- Local selector pseudo replacements emit scoped CSS Modules classes and exports, e.g. `.foo:hover` with `hover => is-hovered` emits `.EgL3uq_foo.EgL3uq_is-hovered`.
- Global-mode selector pseudo replacements remain public, e.g. `:global(.wp-block-button:hover)` emits `.wp-block-button.is-hovered`.
- Existing local `composes` metadata remains attached to the owning local class while replacement classes are exported independently.
- `wordpress-css-modules-transformer.php` now smokes block CSS that maps hover/focus-visible state classes without Node or WASM while preserving a local composed class export.

Evidence:

- Red-first spot-check before this patch: passing `pseudoClasses` to `CssModulesTransformer` left `.EgL3uq_foo:hover` unchanged and produced no replacement-class export.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 183 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 3146 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` => passed.
- Root harness status: not run - isolated micro-slice.

Coverage delta:

- Focused CSS Modules transformer evidence moves to `183` assertions.
- Full LightningCSS PHP evidence moves from `3141` to `3146 pass / 0 fail`.
- Conservative mapped upstream coverage moves from `1696 / 3532` to `1697 / 3532` for the CSS Modules branch of `src/lib.rs::test_pseudo_replacement`.

Dependency closure:

- No new support component is needed. This reuses the lane-local CSS Modules selector scanner, CSS identifier escaping, scoped export map, minifier/nesting pipeline, and WordPress example harness. No Node, Rust, WASM, browser service, parser generator, or external CSS engine is required at runtime.

Non-overlap:

- This does not repeat accepted CSS Modules local/global selector-list validation, nested global/local mode precedence, escaped identifiers/specifiers, declaration-priority `composes`, functional `:local()` composes rejection, pure-mode no-check/license handling, animation/keyframes scoping, counter-style/list-style scoping, grid area/line-name scoping, container-name scoping, `@scope` prelude scoping, dashed `@property` / `@font-palette-values` / `font-palette` scoping, media `env()` dashed identifiers, view-transition scoping, content-hash/project-root hashing, missing-export bundling, or file-backed import graph resolution. It only closes pseudo-class replacement serialization under CSS Modules while preserving local/global mode and `composes` exports.

Next task:

- Continue CSS Modules parity on non-overlapping unused-symbol pruning, dependency flattening, or selector-valued option boundaries not covered by accepted local/global/composes, grid, container, scope, view-transition, dashed-ident, content-hash, or pseudo replacement slices.
