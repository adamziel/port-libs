# CSS Modules ::part and ::picker Pseudo-Element Parity

Slice: `lightningcss-css-modules-local-global-compose-parity-20260601T093718Z`

Base: `9495523910adeabd01c9bc2c77431af9d8027200`

## Source Truth

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Pristine upstream `src/selector.rs` recognizes `::part(...)` as `Component::Part` and `::picker(...)` as a functional pseudo-element (`PickerFunction`).
- Targeted upstream native transform evidence:
  - `.card::part(icon)` scopes only `.card` and preserves `icon`.
  - `.card::picker(select)` scopes only `.card` and preserves `select`.
  - `.card::part(icon) .title` and `.card::picker(select) .title` are rejected because pseudo-elements cannot be followed by descendant selectors.
  - `.card::part(icon) { composes: base }` is rejected because `composes` requires a simple local class selector.

## Implementation

- `CssModulesTransformer::cssModulesPseudoElementAt()` now classifies functional `::part(...)` and `::picker(...)` as terminal pseudo-elements for CSS Modules selector-boundary validation.
- The pseudo-element arguments are intentionally left raw/public, matching upstream behavior and avoiding local export creation for `part`/`picker` names.
- Existing local/global scoping and composes metadata behavior is preserved for the owning class.

## Verification

- Baseline before this change: `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 506 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-terminal-pseudos.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> `1 test files, 512 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-css-modules-terminal-pseudos.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7213 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Status Delta

- `lanes/lightningcss/lane-status.json` `phpPass`: `7207` -> `7213`.
- `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json` conservative mapped coverage remains `2365 / 3532` because this deepens the already represented CSS Modules pseudo-element/local-global-composes cluster.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP CSS Modules selector scanner, composes validator, and WordPress terminal-pseudo example smoke.

## Non-Overlap

This does not repeat the accepted terminal pseudo-element batch. That prior batch covered non-functional terminal pseudo-elements such as `::selection`, `::marker`, placeholder/file-selector-button, and related selector-tail guards. This slice adds the upstream functional pseudo-elements `::part(...)` and `::picker(...)`, including the local/global recursion and composes restrictions around them.
