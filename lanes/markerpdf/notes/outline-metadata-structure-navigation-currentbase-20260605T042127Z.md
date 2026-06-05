# markerPDF outline metadata structure navigation boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T042127Z`

Base accepted HEAD: `722149fc43ce6f31b44b147893f0f41a97976a99`

## Source truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text and bookmark/navigation metadata through `pdftext` and PDFium-backed parsing. The native no-GPU PHP lane owns the parser boundary where PDF outline dictionaries, structure elements, action chains, and associated file metadata become WordPress review metadata without visible paragraph promotion or action execution.

PDF outline items can carry `/SE` references to tagged structure elements. Existing native document metadata already summarized those `/SE` objects as review-only `document_outline.items[*].structure_element`, including roles, MCIDs, language/title/alternate text, and associated FileSpec checksum metadata. The missing boundary was the composite navigation path: `PdfOutlineExtractor::getNavigationReviewMetadata()` and `getOutlineStructureDestinationPageContext()` emitted outline rows and action rows without the outline item `/SE` context.

## Change

- `PdfOutlineExtractor` now reuses the sanitized `PdfMetadataExtractor` document-outline `/SE` rows by `outline_object`.
- Composite navigation outline rows now include `structure_element`, `structure_element_role`, `structure_element_mcids`, and associated-file count metadata for the outline item itself.
- Outline action review rows receive the same context with an `outline_` prefix, such as `outline_structure_element_role`, so it does not collide with target-page tagged-content metadata.
- The WordPress structure-element smoke now asserts navigation review payload omission as well as document metadata payload omission.

## Red-first evidence

Before the extractor change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStructureNavigationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL carries outline SE structure metadata into navigation review rows and action rows
Values are not identical
Expected: 60
Actual: NULL
PASS keeps navigation outline SE metadata and associated payload out of visible WordPress text

1 test files, 14 assertions, 1 failures
```

After the change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStructureNavigationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries outline SE structure metadata into navigation review rows and action rows
PASS keeps navigation outline SE metadata and associated payload out of visible WordPress text

1 test files, 49 assertions, 0 failures
```

Affected outline/navigation family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfOutline.*Test\.php$|PdfMetadata.*Outline.*Test\.php$' | sort)
Focused test run: 40 selected test files (root lock skipped)
40 test files, 2331 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-structure-element-metadata-currentbase.php
```

The smoke emits `navigation_outline_structure_role=H1`, `navigation_payload_content_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff hygiene:

```text
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfOutlineExtractor.php

php -l lanes/markerpdf/tests/PdfOutlineMetadataStructureNavigationCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfOutlineMetadataStructureNavigationCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-outline-structure-element-metadata-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-outline-structure-element-metadata-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` exited 0.

## Non-overlap

This does not repeat accepted document-outline `/SE` metadata extraction, outline destination view rows, PageLabels/transition/action review rows, name-tree action structure target context, outline `/Parent`/`/Prev`/`/Last` traversal guards, generation-exact outline references, named-destination generation boundaries, or AcroForm field-generation work. The bounded behavior is only carrying already-sanitized outline item `/SE` metadata into composite navigation rows and action rows.

## Dependency closure

No new support component is needed. The slice reuses native PDF object scanning, document outline metadata extraction, structure-tree review metadata, embedded FileSpec checksum review, and existing navigation/action review rows. Full upstream model parity remains out of scope under the current no-GPU markerPDF directive: no `pdftext`, PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI worker, benchmark model download, external OCR, or external PDF tool was executed.
