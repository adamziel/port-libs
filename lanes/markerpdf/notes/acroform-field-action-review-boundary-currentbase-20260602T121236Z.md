# AcroForm Field Action Review Boundary, 2026-06-02

Micro-slice: `acroform-field-action-review-boundary-currentbase-20260602T121236Z`

Source truth:

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF import at the pdftext/pypdfium document-extraction boundary and does not execute PDF form actions, JavaScript, launch actions, or external PDF tools during conversion.
- Adobe PDF Reference 1.6 section 8.5 defines action dictionaries and `/Next` action chains; section 8.6 covers interactive forms, including field/widget action dictionaries such as `/A` and `/AA` trigger-event entries.
- This isolated worktree does not include a markerPDF upstream checkout, so source inspection relies on the accepted lane manifest plus the official PDF Reference action semantics.

Implemented behavior:

- `PdfAcroFormExtractor` now keeps non-JavaScript AcroForm field/widget actions as review-only metadata instead of dropping them.
- The added boundary covers `/S /URI`, `/Launch`, `/ImportData`, `/Hide`, `/Named`, `/GoTo`, and `/GoToR`, including `/Next` chain rows already walked by the extractor.
- The URI key lookup now parses top-level action dictionary entries, so `/S /URI` is not mistaken for the `/URI` operand. Unsafe `javascript:` form-action URIs are marked `blocked-unsafe-uri`.
- Hide actions report target field objects/names, including widget references mapped back to field names, while import-data and launch actions expose targets without importing data or launching processes.

Focused evidence:

- Red-first focused run after adding the fixture caught the existing boundary bug: `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` failed because `/S /URI` was read as the URI operand and the unsafe JavaScript URI was labeled `review-uri`.
- After the fix, `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` passed with `1 test files, 538 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-acroform-field-action-boundary.php` emitted action types `Named`, `URI`, `Launch`, `Hide`, and `ImportData`; safety labels `named-action-review`, `blocked-unsafe-uri`, `launch-action-review`, `hide-action-review`, and `import-data-action-review`; hide target `registration.url`; import target `review.fdf`; and all execution flags false.
- `php -l` passed for `PdfAcroFormExtractor.php`, `PdfAcroFormExtractorTest.php`, and `wordpress-pdf-acroform-field-action-boundary.php`.
- `php -r` JSON validation passed for `lanes/markerpdf/lane-status.json` and `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/markerpdf` passed.

Status delta:

- Behavior tests move `494 -> 495`.
- Mapped markerPDF/PDF semantics move `342 -> 343 / 78`.

Non-overlap:

This does not repeat accepted AcroForm SubmitForm/ResetForm review metadata, AcroForm field/widget JavaScript action review, calculation/signature state, widget appearance/value/action boundaries, catalog OpenAction review, link/markup annotation actions, or rich-media action review. The new behavior is the AcroForm field/widget non-JavaScript action boundary and the top-level `/URI` action operand parser fix.

Dependency closure:

No new support component is needed. The slice reuses the native PDF object scanner, dictionary/array parser, AcroForm field/widget traversal, field-name map, action-chain walker, and review metadata path. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.
