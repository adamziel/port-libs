# Pandoc PDF Destination Policy Slice

`plib-41xqt` adds a bounded produced-PDF inspection policy for destination
metadata in `PdfEngineHandoff`.

The slice keeps existing destination extraction intact and adds
`pdfDestinationPolicy` to fake-run results, artifact provenance review, and
final sequence metadata. The policy summarizes named destinations, destination
options, page-object targets, named-target references, unresolved named targets,
fit-mode counts, explicit coordinate arguments, and zoom boundaries before PDF
handoff.

Validation on 2026-07-02:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffDestinationPolicyTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffDestinationPolicyTest.php`
  passed with 1 file, 24 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffDestinationPolicyTest.php lanes/pandoc/tests/PdfEngineHandoffTypstBoundaryMatrixSummaryTest.php lanes/pandoc/tests/PdfEngineHandoffTest.php lanes/pandoc/tests/PdfReaderTest.php`
  passed with 4 files, 5,272 assertions, 0 failures.
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check -- lanes/pandoc`
- Conflict-marker scan over touched Pandoc files returned no matches.

The implementation does not invoke Typst, TeX/PDF engines, external validators,
browser tooling, or Pandoc shell-outs.
