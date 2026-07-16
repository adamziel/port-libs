# CSS Modules Terminal Pseudo Local Global Compose Parity - 2026-06-01

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source files checked: `src/selector.rs` pseudo-element parsing and `src/css_modules.rs` CSS Modules `:local()` / `:global()` and `composes` handling.
- Behavior: upstream treats standard pseudo-elements such as `::selection`, `::marker`, placeholder/file-selector-button aliases, scrollbar pseudos, and highlight grammar/spelling pseudos as pseudo-elements. CSS Modules local/global rewriting must preserve valid selectors, including upstream-lenient pseudo-classes after pseudo-elements, but reject descendant selectors after terminal pseudo-elements before producing composes output.

## Patch

- `CssModulesTransformer::cssModulesPseudoElementAt()` now recognizes the upstream terminal pseudo-element set used by CSS Modules boundary validation.
- Valid local/global CSS Modules selectors with terminal pseudo-elements remain serializable and keep composes exports, for example `.card::selection`, `:global(.wp-block-list)::marker`, and `.card::file-selector-button`.
- Invalid selectors such as `.card::selection .child`, `:global(.legacy::marker .child) .card`, and `.card::placeholder::before` now throw `CSS pseudo-elements cannot be followed by selectors`.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` - passed.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` - passed.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-terminal-pseudos.php` - passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` - passed, `1 test files, 502 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-terminal-pseudos.php --self-test` - passed.
- `git diff --check -- lanes/lightningcss` - passed.

## Coverage Delta

- Focused assertion delta: +10 assertions in `CssModulesTransformerTest.php`.
- `lane-status.json` `phpPass`: `6984 -> 6994`.
- Manifest mapped coverage is unchanged at `2360 / 3532`; this deepens the existing mapped CSS Modules local/global/composes cluster rather than claiming a new upstream inventory row.

## Dependency Closure

- No new support component is needed. The patch reuses the native PHP CSS Modules selector scanner and existing transformer/export metadata model.

## Non-Overlap

- This does not touch recent accepted escaped custom-ident scoping, CSS Modules view-transition selector functions, host/slotted/cue/highlight behavior, bundle/import graph work, source-map handling, CSSOM read/write, media queries, property/value minification, or target-prefixing slices.
