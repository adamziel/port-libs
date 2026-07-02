# PDF/Typst output-format summary provenance

`PdfEngineHandoff` now carries compact Typst output-format review buckets through
`typstBoundarySummary` without executing Typst or a PDF engine.

The summary includes:

- selected output format;
- output-format history count;
- safe versus unsafe output-format entry counts;
- PDF versus non-PDF output-format entry counts.

This complements the existing `typstOutputFormatPolicy`,
`typstBoundaryProvenance.outputFormatPolicy`, boundary matrix, artifact review,
and fake-run sequence handoff.

Focused accounting:

- `mappedTypstOutputFormatSummaryProvenanceCases`: `1`
- `typstOutputFormatSummaryProvenanceAssertions`: `6`

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoff*Test.php`
