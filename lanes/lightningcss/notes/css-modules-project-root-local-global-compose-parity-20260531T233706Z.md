# CSS Modules project_root local/global compose parity

Micro-slice: `lightningcss-css-modules-local-global-compose-parity-20260531T233706Z`

Upstream source truth:

- Pinned upstream cache `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/lib.rs::test_css_modules` covers CSS Modules hash pattern and local/global/composes export behavior.
- `src/css_modules.rs::Pattern` and `CssModule::handle_composes` use the filename relative to the configured project root when computing `[hash]`, while local and global `composes` entries remain in export metadata.

Implementation:

- `CssModulesTransformer` now accepts both `projectRoot` and snake_case `project_root` options for CSS Modules stable hash generation.
- Added a focused CSS Modules regression where `filename` is `/sites/a/theme/blocks/card.module.css`, `project_root` is `/sites/a/theme`, and `[name]__[hash]__[local]` produces `card-module__VKU3mq__*` classes while preserving local `base` and global `utility` composition.
- Added `examples/wordpress-css-modules-project-root-compose.php` to model a WordPress block-theme CSS module compiled without Node/WASM using snake_case `project_root`.

Red-first evidence:

- Before the source change, the same snake_case `project_root` input was ignored and hashed from the absolute filename, producing the non-upstream-root-relative segment `bCyVbG`.
- After the change, the snake_case option uses the project-root-relative path and produces the expected upstream-stable `VKU3mq` segment, matching the existing camelCase `projectRoot` path.

Verification:

- `php -l lanes/lightningcss/src/CssModulesTransformer.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-css-modules-project-root-compose.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php` -> 1 test file, 323 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-css-modules-project-root-compose.php --self-test` -> OK.
- `php tools/run-tests.php lanes/lightningcss/tests` -> 13 test files, 4880 assertions, 0 failures.

Dependency closure:

- No new support component is needed. This reuses `CssModulesTransformer` path normalization, hash/pattern substitution, selector/declaration scanning, composes metadata, and export class-list helpers.

Non-overlap:

- This does not touch the historical CustomMedia rework note.
- This avoids accepted CSS Modules escaped pseudos, state/highlight, host-context, unused-symbol pruning, pure selector, source-index, and bundler dependency slices. The only behavior delta is snake_case project-root hash option parity through the existing local/global/composes export path.

Root harness:

- Not run - isolated micro-slice.
