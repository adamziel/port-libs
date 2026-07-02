# Pandoc BibLaTeX CSL Subtitle Family Handoff - 2026-07-01

Slice: `plib-5uxmy`

## Scope

- Promoted BibLaTeX subtitle-family fields to first-class CSL handoff metadata:
  `subtitle`, `container-subtitle`, `reviewed-subtitle`, `main-subtitle`,
  `volume-subtitle`, `part-subtitle`, `issue-subtitle`, and `original-subtitle`.
- Kept both BibTeX item producers aligned:
  `BibtexCslProcessor::cslItems()` and `CitationCslProcessor::fromBibtex()`.
- Added renderer/sort support for the new split subtitle variables without
  double-composing already-composed title strings.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibliographyReaderTest.php`
