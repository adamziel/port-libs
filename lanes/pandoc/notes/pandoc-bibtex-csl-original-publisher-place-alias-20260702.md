# Pandoc BibLaTeX CSL original publisher place alias slice 2026-07-02

Issue: `plib-6ecux`

This slice closes a compact BibLaTeX original imprint alias gap in the CSL handoff path. `origpublisherplace`, `orig-publisher-place`, `origpublisherplacelist`, and `orig-publisher-place-list` now resolve to the existing CSL `original-publisher-place` and `original-publisher-place-list` metadata in:

- legacy `BibtexCslProcessor` BibTeX parsing
- parser-backed `CitationCslProcessor::bibtexItems()` and `fromBibtex()` paths
- direct CSL item normalization
- CSL variable rendering aliases used by citation and bibliography styles

The focused fixture is `lanes/pandoc/tests/BibtexCslProcessorOriginalPublisherPlaceAliasTest.php`. It keeps the check metadata-only: no external citeproc, BibTeX, Biber, Pandoc, identifier lookup, package parser, or fetch is invoked.

Manifest accounting:

- `benchmarkDenominator.mapped`: `2316` to `2317`
- `legacyBiblatexOriginalPublisherPlaceAliasCases`: `1`
- `mappedLegacyBiblatexOriginalPublisherPlaceAliasCases`: `1`
- `legacyBiblatexOriginalPublisherPlaceAliasAssertions`: `43`

Validation:

- `php -l lanes/pandoc/tests/BibtexCslProcessorOriginalPublisherPlaceAliasTest.php`
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorOriginalPublisherPlaceAliasTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorOriginalPublisherPlaceAliasTest.php lanes/pandoc/tests/BibtexCslProcessorOriginalReprintLocatorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorBiblatexRelatedTypeLabelsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
