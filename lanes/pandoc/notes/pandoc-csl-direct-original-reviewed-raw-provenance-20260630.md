# Pandoc CSL Direct Original/Reviewed Raw Provenance 2026-06-30

- Added bounded direct CSL raw-variable rendering for original title, original subtitle, and reviewed title alias families.
- The new raw variables mirror the existing translated-title raw provenance path so CSL styles can inspect canonical, camel, flat, and compact source aliases after normalization chooses the rendered value.
- Covered the slice with `CitationCslProcessorTest.php` using CSL JSON ingestion, style summary checks, citation rendering, bibliography rendering, and WordPress bibliography output.

Verification:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Attempted broader lane gate:

- `php tools/run-tests.php lanes/pandoc/tests` (existing non-CSL failures remained in Markdown, table geometry, YAML metadata, syntax highlighting, and related suites)
