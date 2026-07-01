# BibLaTeX name annotations CSL handoff

Slice: `plib-ufskx`
Date: 2026-07-01

## Scope

- `BibtexCslProcessor` now attaches BibLaTeX `+an` name annotations to legacy CSL name objects instead of dropping them or exposing them as field annotations.
- The legacy processor now maps `editortranslator` / `editor-translator` to CSL `editor-translator` names.
- Proceedings/conference `organization` values now fall back to CSL `event-organizer` names when explicit `eventorganizer` / `organizer` fields are absent.
- The slice does not invoke Pandoc, citeproc, BibTeX, Biber, network lookup, or external validators.

## Direct-format parity

- `UPSTREAM_TEST_MANIFEST.json` mapped count increased by 1 to 2877 on the rebased main baseline.
- Added 1 mapped legacy BibLaTeX name annotation case with 28 focused assertions.
- Current slice accounting records Citation/CSL handoff parity for auxiliary creator-role annotations and organization event-organizer fallback.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorNameAnnotationTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorNameAnnotationTest.php`
  - 1 file, 28 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessor*.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibliographyReaderTest.php`
  - 8 files, 7674 assertions, 0 failures
