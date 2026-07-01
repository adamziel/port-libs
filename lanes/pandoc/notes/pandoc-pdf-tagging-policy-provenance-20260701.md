# PDF Tagging Policy Provenance

Slice: `plib-ns6oq`

`PdfEngineHandoff` now derives a bounded produced-PDF tagging review policy from already-captured byte metadata. The policy summarizes `/MarkInfo` flags, `/StructTreeRoot`, role-map and child counts, structure element counts, parent tree and ID tree coverage, page `/StructParents`, marked-content property/artifact counts, and review issues.

The handoff carries this summary through fake-run diagnostics and sequence metadata only. It does not invoke Pandoc, Typst, TeX/PDF engines, browsers, office suites, archive tools, or external PDF validators.

Focused validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` with 1 file, 3,725 assertions, 0 failures
