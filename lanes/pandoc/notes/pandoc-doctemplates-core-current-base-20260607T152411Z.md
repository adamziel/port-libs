# Pandoc doctemplates core current-base DocBook5 default fallback

Slice: `pandoc-doctemplates-core-current-base-20260607T152411Z`
Base: `6a3ea0f4861660790e73a0b7403add52995f31fa`
Lane: `pandoc`

## Source truth

- Pandoc `Text.Pandoc.Templates` at pinned upstream commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83` maps the `docbook` writer to the `docbook5` default template resource.
- Pandoc `data/templates/default.docbook5` renders a bounded DocBook5 article/chapter wrapper with title, subtitle, author, date, abstract, include-before, body, and include-after slots.
- Primary source references used for the bounded port:
  - https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Templates.hs
  - https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.docbook5

## Implementation

- `DocTemplate` now canonicalizes the `docbook` format alias to `docbook5`.
- `templates/default.docbook5` is available as a native built-in default resource.
- Direct `templates/default.docbook5` lookup and caller-supplied override precedence are preserved.
- The WordPress doctemplate review-packet smoke now exercises the DocBook5 fallback through `templates/default` plus the `docbook` writer alias.

## Evidence

- Baseline focused command before the slice:
  - `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 403 assertions, 0 failures`
- Red-first command after adding the focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 403 assertions, 1 failures`
  - Failure: `Missing doctemplate resource templates/default` for the `docbook` alias.
- Final focused command after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 421 assertions, 0 failures`
- PASS/assertion delta:
  - `+1` PHP PASS case
  - `+18` focused assertions
- Manifest/status delta:
  - `benchmarkDenominator.mapped`: `1943 -> 1944`
  - `mappedDoctemplateDefaultTemplateCases`: `1 -> 2`
  - `doctemplateDefaultTemplateAssertions`: `9 -> 27`
  - `lane-status.json phpPass`: `1523 -> 1524`

## Dependency closure

No new support component is needed. This reuses native `DocTemplate` resource resolution, built-in default-template fallback loading, focused doctemplate tests, and the existing WordPress doctemplate review-packet example.

Pandoc, Cabal/Haskell runners, external template engines, TeX/PDF engines, browser renderers, online services, live provider tests, and live-service provider tests were not run.

## Non-overlap

This slice does not touch the accepted Markdown/CommonMark, Beamer, ms, man, HTML5, LaTeX, OpenXML, OpenDocument, EPUB3, pipe, partial, nesting, Unicode variable, or eager-validation doctemplate behavior. It is limited to DocBook5 default-template fallback and the `docbook` alias.

## Next

Potential follow-up: choose a non-overlapping native doctemplate gap such as JATS writer aliases, custom partial fallback diagnostics, or remaining bounded template resource behavior. Keep external Pandoc/Cabal/template-engine execution out of scope.
