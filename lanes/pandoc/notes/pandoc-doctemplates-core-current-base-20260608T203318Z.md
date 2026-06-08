# pandoc-doctemplates-core-current-base-20260608T203318Z

Base accepted HEAD: `bb37a42dff2002404bb134df44da31542c787c36`

## Source truth

- Upstream source: the pinned `jgm/pandoc` commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83` includes `data/templates/default.rtf` at `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/data/templates/default.rtf`.
- Pandoc user-facing template behavior treats `templates/default.FORMAT` as the system/user-data default template lookup shape for output format `FORMAT`.

## Behavior

- Added bounded native `templates/default.rtf` support to `DocTemplate`.
- The RTF default handles header-includes, title, repeated authors, date, spacer, table-of-contents, include-before, body, and include-after through the existing doctemplate renderer.
- The resource resolver now supports extension-qualified `rtf+...` fallback to `default.rtf`, direct `templates/default.rtf`, user custom overrides, and nested default partial calls such as `${ default.rtf() }`.

## Evidence

- No active `port-pandoc-*.needs-lane-rework.md` rework notes existed for this slice.
- Baseline before implementation: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 902 assertions, 0 failures`.
- Focused after implementation: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 920 assertions, 0 failures`.
- Syntax checks passed for:
  - `php -l lanes/pandoc/src/DocTemplate.php`
  - `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
- Example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` passed with `OK wordpress doctemplate review packet`.
- JSON sanity check passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace check: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status delta

- `phpPass`: `1816 -> 1817`
- Focused assertion count: `902 -> 920` in `DocTemplateTest.php`
- Manifest mapped denominator: `2240 -> 2241`
- `mappedDoctemplateDefaultTemplateCases`: `1 -> 2`
- `doctemplateDefaultTemplateAssertions`: `9 -> 27`

## Dependency closure

No new support component is needed. This slice reuses the native PHP `DocTemplate` parser, bundled default-template registry, resource fallback logic, and lane-local WordPress doctemplate example. No Pandoc, Cabal/Haskell runner, external template engine, roff renderer, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not touch recent doctemplate braced separator, user-data default partial, man/ms/beamer default fallback, breakable-space wrapping, or applied-partial rebinding slices. A useful follow-up would be another upstream default-template fallback not yet in the bundled registry, such as ANSI or BibTeX-family templates, if it remains absent on the accepted base.
