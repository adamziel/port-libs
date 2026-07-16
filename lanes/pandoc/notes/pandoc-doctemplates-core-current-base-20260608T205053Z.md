## Source truth

- Upstream template source: the Pandoc templates repository includes `default.ansi`, `default.bibtex`, and `default.biblatex` in `https://github.com/jgm/pandoc-templates`.
- The upstream ANSI and BibTeX-family defaults use the same bounded fallback shape: optional `titleblock`, repeated `header-includes`, repeated `include-before`, optional `table-of-contents`, `body`, and repeated `include-after`.
- Pandoc user-facing template lookup treats `templates/default.FORMAT` as the system/user-data default template resource for output format `FORMAT`; this slice ports that bounded format contract without invoking Pandoc or external template engines.

## Behavior

- Added bundled native `templates/default.ansi`, `templates/default.bibtex`, and `templates/default.biblatex` resources to `DocTemplate`.
- Format fallback now resolves `templates/default` for `ansi+...`, `bibtex`, and `biblatex+...` through the existing extension-qualified resolver.
- Direct resource lookup and caller-supplied user-data overrides remain preserved for the new defaults.
- The WordPress doctemplate review-packet example now exercises ANSI and BibTeX-family default fallback paths.

## Evidence

- No active `port-pandoc-*.needs-lane-rework.md` rework notes existed for this slice.
- Baseline before implementation: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 920 assertions, 0 failures`.
- Red-first after adding the focused test but before registry/default implementation: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed as expected with `1 test files, 920 assertions, 1 failures` because `templates/default` had no ANSI/BibTeX-family bundled resource.
- Focused after implementation: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 935 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` passed with `OK wordpress doctemplate review packet`.
- Root harness: not run - isolated micro-slice.

## Status delta

- `phpPass`: `1834 -> 1835`
- Focused assertion count: `920 -> 935` in `DocTemplateTest.php`
- Manifest mapped denominator: `2258 -> 2259`
- `mappedDoctemplateDefaultTemplateCases`: `1 -> 2`
- `doctemplateDefaultTemplateAssertions`: `9 -> 24`

## Dependency closure

No new support component is needed. This slice reuses the native PHP `DocTemplate` parser, bundled default-template registry, resource fallback logic, and lane-local WordPress doctemplate example. No Pandoc, Cabal/Haskell runner, external template engine, BibTeX, Biber, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not touch recent doctemplate braced separator, user-data default partial, Markdown/CommonMark, man/ms/beamer/RTF default fallback, wiki/vimdoc/lightweight-markup default fallback, breakable-space wrapping, or applied-partial rebinding slices. A useful follow-up is a non-overlapping template-resolution behavior such as user-data override source-location diagnostics or another still-absent upstream default resource on the accepted base.
