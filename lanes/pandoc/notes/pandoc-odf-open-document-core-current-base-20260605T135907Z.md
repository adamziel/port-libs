# pandoc-odf-open-document-core-current-base-20260605T135907Z

## Scope

- Base accepted HEAD: `6c7fbdbc9a9ca213f1352fe9f3decddcfc22e1de`.
- Implemented bounded native ODF `text:table-of-content` import support in `OdfReader`.
- Preserves table-of-content source settings, source-style/template metadata, protected key-presence metadata, index title/body boundaries, and entry links as Pandoc-like AST review divs for Markdown and WordPress handoff.
- Does not generate, refresh, or recalculate the table of contents.

## Evidence

- Baseline before implementation: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 773 assertions, 0 failures`
- Focused verification after implementation: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 825 assertions, 0 failures`
  - Focused assertion delta: `+52`
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- PHP syntax:
  - `php -l lanes/pandoc/src/OdfReader.php`
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
- JSON validation:
  - `lanes/pandoc/lane-status.json ok`
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok`
- Whitespace: `git diff --check -- lanes/pandoc`
  - no output

## Status Delta

- `lanes/pandoc/lane-status.json`
  - `phpPass`: `935 -> 936`
  - focused mapped checks: `1,383 -> 1,384`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - `benchmarkDenominator.mapped`: `1391 -> 1392`
  - `odfOpenDocumentCoreCases`: `10 -> 11`
  - `mappedOdfOpenDocumentCoreCases`: `10 -> 11`
  - `odfOpenDocumentCoreAssertions`: `217 -> 269`

## Dependency Closure

- Reused existing native PHP ODF package/content reader support plus Markdown and WordPress writers.
- No new support component is required for this bounded table-of-content review handoff.
- No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, browser renderer, JavaScript, online sanitizer, or online service was executed.

## Non-Overlap

- Avoided recent ODF slices for ruby annotations, typed table-cell formulas/values, field declarations, form controls, chart objects, link metadata, soft page breaks, lists, table geometry, and linked/protected sections.
- Follow-up ODF work should keep generated-index regeneration, alphabetical/user/table/illustration indexes, RDF metadata, page-number recalculation, style-driven TOC layout fidelity, and remote linked-section fetching as separate bounded slices.
