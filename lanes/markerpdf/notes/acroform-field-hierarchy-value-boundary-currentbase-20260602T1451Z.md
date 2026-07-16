# markerPDF AcroForm Field Hierarchy Value Boundary

Micro-slice: `acroform-field-hierarchy-value-boundary-currentbase-20260602T1451Z`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `marker/pdf/extract_text.py` and `pdftext.dictionary_output`, converting low-level PDF output into page/span structures without executing form actions or JavaScript. The pinned source was checked through GitHub raw because the local markerPDF upstream cache was absent in this worker.
- PDF AcroForm field trees allow non-terminal field dictionaries to contribute inheritable attributes such as `/FT`, `/Ff`, `/V`, and `/DV` to terminal descendants. Terminal child fields can override inherited values, and password text fields must keep their current/default values redacted in WordPress import review.

## Implementation

- `PdfAcroFormExtractor` now emits `field_hierarchy` metadata for terminal fields, including the object/name path, ancestor objects, inherited/local attributes, source objects for `/V` and `/DV`, and non-executing flags.
- `value_state` now includes a compact `hierarchy_boundary` block showing whether the current value came from a parent field dictionary, a terminal override, or a redacted boundary.
- Existing field value behavior remains authoritative: terminal `/V` overrides inherited parent `/V`, inherited parent `/V` is used when no terminal value exists, and password values remain redacted.

## Verification

- Red-first check after adding the focused fixture failed as expected:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php`
  reported `1 test files, 612 assertions, 1 failures`; `field_hierarchy` was missing.
- Post-change focused check passed:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php`
  reported `1 test files, 650 assertions, 0 failures`.
- Adjacent AcroForm/annotation/link check passed:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php`
  reported `3 test files, 994 assertions, 0 failures`.
- WordPress smoke passed:
  `php lanes/markerpdf/examples/wordpress-pdf-acroform-field-hierarchy-value-boundary.php`
  emitted `field_count=3`, inherited value fields `registration.contact.email` and `registration.secret`, terminal override field `registration.title`, redacted field `registration.secret`, and all form-action, JavaScript, Python/model, and external-tool execution flags false.
- PHP lint passed for `lanes/markerpdf/src/PdfAcroFormExtractor.php`, `lanes/markerpdf/tests/PdfAcroFormExtractorTest.php`, and `lanes/markerpdf/examples/wordpress-pdf-acroform-field-hierarchy-value-boundary.php`.
- Lane JSON validation passed for `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` and `lanes/markerpdf/lane-status.json`.
- `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This does not repeat accepted AcroForm `/V` `/DV` `/I` `/Opt` current-value extraction, widget `/AS` default-state fallback, SubmitForm/ResetForm review metadata, field/widget action `/Next` walking, selected appearance stream review, XFA signature boundaries, signature lock/seed metadata, or annotation widget appearance/link slices. The new behavior is the field-tree hierarchy/value source boundary for terminal fields.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary parser, AcroForm field/widget traversal, value decoder, and WordPress smoke harness. Full upstream markerPDF parity remains dependency-gated by pdftext, pypdfium2, Surya/Torch, tabled, Texify, live app/server execution, and benchmark tooling.
