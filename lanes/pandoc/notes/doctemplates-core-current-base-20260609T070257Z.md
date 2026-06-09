# pandoc-doctemplates-core-current-base-20260609T070257Z

Base accepted HEAD: `53cc273b044292e061f08ae6f6fdabc37210dcb0`

## Source Truth

- Pinned Pandoc source: `jgm/pandoc` commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, `data/templates/default.dzslides`.
- Upstream doctemplates parser reference: `jgm/doctemplates` tag `0.11.0.1`, `src/Text/DocTemplates/Parser.hs`.
- No local Pandoc upstream cache was available for this worktree, so the pinned upstream template/parser files were read directly from their primary GitHub sources.
- No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, external converter, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Behavior

The bundled `default.dzslides` fallback in `DocTemplate` now matches the pinned Pandoc no-custom-CSS branch more closely. When no `css` variable is supplied, native rendering preserves the upstream Google font link plus slide sizing, slide counters, heading, blockquote, figure/media, footer, transition, selected-slide, incremental, and progress-bar CSS.

This is a bounded support-library fidelity slice for WordPress review decks that render Pandoc's legacy DZSlides template through the native PHP doctemplate renderer.

## Files

- `lanes/pandoc/src/DocTemplate.php`
- `lanes/pandoc/tests/DocTemplateTest.php`
- `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `lanes/pandoc/notes/doctemplates-core-current-base-20260609T070257Z.md`

## Status Delta

- `phpPass`: `2467 -> 2468`
- `benchmarkDenominator.mapped`: `2851 -> 2852`
- `mappedDoctemplateDefaultTemplateCases`: `1 -> 2`
- `doctemplateDefaultTemplateAssertions`: `9 -> 28`
- New focused case: `mappedDoctemplateDzslidesNoCssCases = 1`
- New focused assertions: `doctemplateDzslidesNoCssAssertions = 19`

## Verification

- `php -l lanes/pandoc/src/DocTemplate.php`
  - `No syntax errors detected in lanes/pandoc/src/DocTemplate.php`
- `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/DocTemplateTest.php`
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f . " OK\n"; }'`
  - `lanes/pandoc/lane-status.json OK`
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json OK`
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - `1 test files, 1162 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - `OK wordpress doctemplate review packet`
- `git diff --check -- lanes/pandoc`
  - passed with no output

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native support component is needed. The patch reuses the existing `DocTemplate` bundled default-template registry, resource fallback logic, focused doctemplate tests, and lane-local WordPress doctemplate review-packet smoke.

## Non-Overlap

This slice does not repeat accepted doctemplate parser diagnostics, wrapping, partial/resource fallback, HTML/RTF/chunked HTML/default HTML template cases, legacy slide custom-CSS resource wiring, CSL/BibTeX/YAML/ODF/DOCX/PDF/XML/archive work, or any external Pandoc runner audit. The follow-up should choose a different bounded default-template fidelity edge, parser diagnostic, or doclayout wrapping gap.
