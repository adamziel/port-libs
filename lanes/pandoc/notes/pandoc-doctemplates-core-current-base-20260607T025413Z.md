# Pandoc Doctemplates Core Current Base - Default Man Template

Slice: `pandoc-doctemplates-core-current-base-20260607T025413Z`
Base accepted HEAD: `daddb71fc75dfb1aeafa7cb832e2daaad4824205`

## Source Truth

- Upstream template dispatch: `Text.Pandoc.Templates.getDefaultTemplate` maps a writer format to `templates/default.<format>` at `jgm/pandoc` `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
  Source: https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs
- Upstream bounded resource: `data/templates/default.man`.
  Source: https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.man

## Implementation

- Added a native PHP bounded `default.man` doctemplate resource in `DocTemplate`.
- Mapped `templates/default` plus writer format `man` to `templates/default.man`.
- Preserved caller-supplied `templates/default.man` override behavior.
- Added focused coverage for tbl marker, generator comment, adjustment, `.TH` metadata, header includes, include-before/after blocks, body, authors, direct `templates/default.man` lookup, and custom resource override.
- Added the same bounded man fallback to the WordPress doctemplate review-packet self-test.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 331 assertions, 0 failures`
- Red-first gap: `templates/default` with writer format `man` raised `Missing doctemplate resource templates/default`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 344 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `DocTemplate` renderer, existing resource fallback mechanism, focused lane test harness, and WordPress doctemplate review-packet smoke.

Full Pandoc default-template parity beyond this bounded embedded `default.man` resource remains separately scoped native support-library work, or would require explicit authorization for external Pandoc/Haskell runner work. No Pandoc, Cabal, Haskell runner, external template engine, roff renderer, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted default Markdown/CommonMark, plain, HTML5, LaTeX, Beamer, Office/EPUB, Typst, applied-partial, breakable-space, or braced-separator doctemplate work. It owns only the bounded man writer default-template fallback.

## Next

Remaining doctemplate work should stay on non-overlapping parser/rendering or default-resource gaps, such as remaining writer default resources, filter-chain edge behavior, partial resolution diagnostics, or template error-reporting parity.
