# markerPDF outline missing-parent boundary current-base slice

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260604T225410Z`

Base accepted HEAD: `524dc40526b2fcb46fefc7d28613d818c4db4c08`

## Source truth

- Upstream markerPDF receives bookmark/TOC rows through PDF parser/pdfium-style outline traversal, where outline sibling lists are parent-scoped review metadata rather than visible page text.
- PDF outline item dictionaries are parent-scoped; a child outline item's `/Next` row that omits `/Parent` must not pull an orphan bookmark into the child list.
- This is native no-GPU searchable-PDF behavior only. It does not use OCR, Surya, Texify, pypdfium, Python models, or external PDF tools.

## Red-first failure

After adding `PdfOutlineMetadataMissingParentBoundaryCurrentBaseTest.php`, the current base failed:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataMissingParentBoundaryCurrentBaseTest.php
```

Observed result:

```text
1 test files, 9 assertions, 2 failures
```

Failure:

- `document_outline.item_count` was `4` instead of `3`.
- TOC/navigation rows imported `Orphan Outline After Child`.

## Implementation

- `PdfOutlineExtractor::outlineItemParentMatches()` now treats a missing `/Parent` as valid only when the expected parent is an outline root object.
- `PdfMetadataExtractor::documentOutlineItemParentMatches()` applies the same boundary to `document_outline` metadata rows.
- Root-level compatibility remains bounded: outline roots are recognized by `/Type /Outlines` or root-like outline dictionaries with `/First`, `/Last`, or `/Count` and no `/Title`.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataMissingParentBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 33 assertions, 0 failures
```

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataMissingParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLastBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataGenerationBoundaryCurrentBaseTest.php
```

Result:

```text
4 test files, 147 assertions, 0 failures
```

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutline*CurrentBaseTest.php
```

Result:

```text
28 test files, 1804 assertions, 0 failures
```

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Test.php
```

Result:

```text
16 test files, 1504 assertions, 0 failures
```

```bash
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineMetadataMissingParentBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-metadata-missing-parent-boundary-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-missing-parent-boundary-currentbase.php
```

The WordPress smoke emits `orphan_outline_excluded=true`, `orphan_action_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the existing native PDF dictionary/reference parser and outline/metadata review extractors.

## Non-overlap

This does not repeat the accepted outline parent-boundary slice for wrong-parent `/Next` links, the outline `/Last` terminal-boundary slice, or the generation-exact outline boundary slice. The bounded new behavior is missing-`/Parent` orphan rejection inside child outline lists before WordPress TOC/navigation/action review metadata is emitted.
