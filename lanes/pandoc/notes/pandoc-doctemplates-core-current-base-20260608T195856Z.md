# Pandoc doctemplates core current-base user-data default partial precedence

Slice: `pandoc-doctemplates-core-current-base-20260608T195856Z`

Accepted base: `5ab7f3dd2c18dec97fb5d2517ffc7501ba04e5b8`

## Scope

This slice tightens native `DocTemplate` partial resource discovery so real
resource-map partials are registered before bundled default-template fallback
partials. A caller-supplied main-template partial still wins first, a
user-data partial such as `wp-data/templates/default.plain` now wins before
the built-in `default.plain`, and the bundled default remains available when
no real resource exists.

No Pandoc executable, Cabal solver/build/test command, Haskell runner,
external template engine, browser renderer, TeX/PDF engine, Word, LibreOffice,
zip/unzip, online service, live provider test, or live-service provider test
was executed.

## Source Truth

- Existing accepted doctemplate slices established resource-map partial
  discovery, user-data `templates/` fallback, and bundled default-template
  fallback as the bounded PHP support contract for Pandoc-style templates.
- This patch preserves that contract while correcting precedence: concrete
  caller resources are resolved before synthetic bundled fallback resources.
- The WordPress review-packet smoke covers the user-visible path where import
  tooling supplies `wp-data/templates/default.plain` to customize a bundled
  default partial.

## Evidence

Baseline before edit:

- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
- Result: `1 test files, 897 assertions, 0 failures`

Red-first probe before the source fix:

- Rendering a custom review template with `${ default.plain() }` and a real
  `wp-data/templates/default.plain` resource returned the bundled
  `default.plain` body instead of `user-data default: ...`.

Final focused verification:

- `php -l lanes/pandoc/src/DocTemplate.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/DocTemplateTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
- Result: no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
- Result: `1 test files, 900 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
- Result: `OK wordpress doctemplate review packet`
- `git diff --check -- lanes/pandoc`
- Result: passed

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted doctemplate comments, delimiter whitespace,
conditionals, loops, pipes, false-boolean rendering, `$^$` nesting, `$~$`
wrapping, partial final-newline handling, recursion guards, path-style
partials, extension-qualified top-level template lookup, extension-qualified
sibling partial fallback, default-template bodies, basename default fallback,
filesystem resource loading, source-location diagnostics, colon-qualified
metadata names, or digit-leading child metadata keys.

It owns only precedence between real partial resources and bundled default
partial fallback resources.

## Dependency Closure

No new support component is needed. This reuses the native PHP `DocTemplate`
resource resolver, partial discovery, default-template fallback map, focused
`DocTemplateTest.php` coverage, and the WordPress doctemplate review-packet
example. Full upstream Pandoc/Haskell doctemplates runner parity and external
template engines remain out of scope for this bounded support-library slice.

## Next

Doctemplate follow-up can choose a non-overlapping renderer/resource gap such
as source-location diagnostics for user-data template overrides, remaining
parser parity, or bounded default-template drift checks.
