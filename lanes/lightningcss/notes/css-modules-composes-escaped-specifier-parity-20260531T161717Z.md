# CSS Modules Composes Escaped Specifier Parity

- Slice: `lightningcss-css-modules-local-global-compose-parity-20260531T161209Z`.
- Targeted upstream area: `src/css_modules.rs::CssModule::handle_composes` dependency references and the CSS parser's string-token escape decoding used by `composes: ... from "<specifier>"`.
- Source truth: pinned native LightningCSS artifact at manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9` decodes CSS string escapes in dependency specifiers. Spot checks:
  - `from "./theme\ components.css"` exports specifier `./theme components.css`.
  - `from "./theme\000020components.css"` exports specifier `./theme components.css`.
  - `from "./icons\2f arrow.css"` exports specifier `./icons/arrow.css`.

## Implementation

- `CssModulesTransformer::parseQuotedSpecifier()` now decodes CSS string escapes before recording dependency `composes` metadata.
- The decoder handles simple escaped characters, hex escapes with optional terminator whitespace, and escaped line continuations.
- `wordpress-css-modules-transformer.php` now models an escaped dependency path in a build-free block module and asserts the decoded specifier in export metadata.

## Evidence

- Red-first spot check before the patch: PHP preserved raw backslashes in all three dependency specifiers while the pinned native artifact decoded them.
- Focused verification after the patch:
  - `php -l lanes/lightningcss/src/CssModulesTransformer.php && php -l lanes/lightningcss/tests/CssModulesTransformerTest.php && php -l lanes/lightningcss/examples/wordpress-css-modules-transformer.php`
  - `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` => `1 test files, 100 assertions, 0 failures`
  - `php lanes/lightningcss/examples/wordpress-css-modules-transformer.php --self-test` => `OK`
  - `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 2095 assertions, 0 failures`
  - `git diff --check -- lanes/lightningcss` => passed
- This deepens the already mapped CSS Modules local/global/composes cluster and does not increase conservative mapped upstream denominator coverage.

## Non-Overlap

This does not repeat accepted pure-mode selector boundaries, functional `:local()` composes rejection, escaped local selector identifiers, dashed-ident import graphs, missing-export bundle flattening, CSS Modules view-transition scoping, or bundle SourceProvider/import graph behavior. The older `CustomMediaTransformer` rework note remains unrelated to this CSS Modules slice.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local `CssModulesTransformer` tokenizer and existing UTF-8 codepoint helper; no Node, Rust, or external parser is required at runtime.
