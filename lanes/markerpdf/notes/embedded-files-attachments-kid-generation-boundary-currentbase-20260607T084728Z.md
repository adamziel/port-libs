# EmbeddedFiles Kid Generation Boundary Current Base

Slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260607T084728Z`
Accepted base: `bed5eb0577e7b3da6f9d9150fbc09175dc986376`
Date: 2026-06-07 UTC

## Source Truth

markerPDF upstream behavior keeps native searchable-PDF parsing in the no-GPU path before any OCR/model fallback. PDF name-tree `/Kids` entries are indirect references that include both object number and generation, so a damaged no-xref fallback must not treat `7 0 R` and `7 1 R` as the same visited node. This slice keeps xref-selected files strict while letting no-xref fallback resolve generation-specific child nodes from direct object definitions.

## Implemented Boundary

- `PdfAttachmentExtractor` now keeps a generation-indexed direct-object fallback only when no live xref selection is available.
- `PdfAttachmentExtractor::nameTreeEntries()` now tracks visited name-tree nodes as `object:generation` instead of only object number.
- `PdfEmbeddedFileExtractor` mirrors the same no-xref generation fallback and `object:generation` name-tree kid tracking.
- The focused fixture has no xref and uses `/Kids [7 0 R 8 0 R]`, where `7 0 R` points to child `7 1 R`; both `current-generation-kid.xml` and `summary-generation-kid.xml` must survive attachment summary and full payload extraction.

## Evidence

Red-first before source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNameTreeKidGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps same-object EmbeddedFiles name-tree kid generations distinct before WordPress attachment review
  Expected: 2
  Actual: 1
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNameTreeKidGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps same-object EmbeddedFiles name-tree kid generations distinct before WordPress attachment review

1 test files, 48 assertions, 0 failures
```

Attachment regression subset:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNameTreeKidGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentGenerationReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileAttachmentGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentNameTreeKidLimitsOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentNameTreeDuplicateNodeKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1112 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-kid-generation-boundary-currentbase.php
exit 0; emits attachment_count=2, nested_generation_kid_resolved=true, sibling_generation_kid_resolved=true, payload_bytes_omitted_from_summary=true, payload_bytes_available_to_full_extractor=true, executes_python_or_models=false, executes_external_pdf_tools=false, and two wp:file blocks.
```

Syntax and patch checks:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php

php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfEmbeddedFileExtractor.php

php -l lanes/markerpdf/tests/PdfAttachmentNameTreeKidGenerationBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAttachmentNameTreeKidGenerationBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-attachment-kid-generation-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-attachment-kid-generation-boundary-currentbase.php

php -r '$json = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid JSON\n";'
lane-status.json valid JSON

git diff --check -- lanes/markerpdf
exit 0
```

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat the accepted generation-exact xref attachment slices or duplicate name-tree key handling. Those cover xref-selected generations, duplicate traversal keys, and name-tree ordering/limits; this patch is limited to no-xref fallback where a name-tree kid and its child share the same object number with different generations.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP PDF object parser, name-tree traversal, checksum review, and stream extraction helpers. No Python, OCR, Surya, Texify, Torch, GPU/model execution, live services, or external PDF tools were used.

## Next Task

Continue with non-overlapping native markerPDF parser behavior: xref repair, CMaps/fonts, annotations/forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
