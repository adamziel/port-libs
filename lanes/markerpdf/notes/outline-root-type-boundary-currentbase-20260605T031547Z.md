# markerPDF outline root-type boundary current-base slice

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260605T031547Z`

Base accepted HEAD: `80e991a9a9260837e8690f88d3d4a67c380b7cf5`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF TOC/bookmark metadata separate from page text through PDF parser/pdfium-style outline traversal.
- PDF catalog `/Outlines` is expected to reference an outline root dictionary. Older/lightweight fixtures may omit `/Type`, but an explicit `/Type /Page` is not an outline root and must not be promoted to document outline metadata, TOC rows, navigation rows, or remote action review.
- This is native no-GPU searchable-PDF behavior only. It does not use OCR, Surya, Texify, pypdfium, Python models, or external PDF tools.

## Red-First Failure

Ad-hoc probe on the accepted base before the source edit:

```text
/Outlines 3 0 R
3 0 obj << /Type /Page ... /First 6 0 R /Last 6 0 R /Count 1 >>
6 0 obj << /Title (Page Root Spoofed Outline) /Parent 3 0 R /Dest /SpoofedTarget /A 12 0 R >>
12 0 obj << /S /GoToR /F (spoofed-outline-root.pdf) /D (spoofed-target) >>
```

Observed result:

```json
{
  "has_document_outline": true,
  "outline_titles": ["Page Root Spoofed Outline"],
  "toc_titles": ["Page Root Spoofed Outline"],
  "nav_titles": ["Page Root Spoofed Outline"],
  "remote_actions": ["Page Root Spoofed Outline"],
  "text": "Malformed outline root page body"
}
```

## Implementation

- `PdfOutlineExtractor` now validates resolved catalog `/Outlines` roots before `getPdfToc()`, `getPdfTocWithDestinationViews()`, `getRemoteGoToActions()`, `getOutlineStructureDestinationPageContext()`, and composite navigation review traversal.
- `PdfMetadataExtractor::document_outline` applies the same root validation before summarizing catalog outline metadata.
- Compatibility remains bounded: `/Type /Outlines` roots are accepted, untyped root-like dictionaries with `/First`, `/Last`, or `/Count` and no `/Title` are still accepted, and explicitly typed non-outline dictionaries fail closed.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRootTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects typed page objects that spoof catalog outline roots in document metadata
PASS applies the typed outline-root guard to TOC navigation and action review rows
1 test files, 22 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineRootTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadata*CurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
Focused test run: 14 selected test files (root lock skipped)
14 test files, 1669 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php
Focused test run: 36 selected test files (root lock skipped)
36 test files, 2106 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-root-type-boundary-currentbase.php
```

The WordPress smoke emits `document_outline_present=false`, `toc_count=0`, `navigation_outline_count=0`, `outline_action_review_count=0`, `remote_action_count=0`, `spoofed_outline_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass` moves `1341 -> 1343` from the two new focused PASS cases.
- Focused assertion coverage for the new root-type boundary test is 22 assertions.
- WordPress scenario count moves `1288 -> 1289` from the added smoke.

## Non-Overlap

This does not repeat accepted outline `/Last`, `/Prev`, parent/missing-parent, title, generation, xref-owner, EOF, named-destination, action-context, page-transition, structure, or metadata-source slices. The bounded behavior is only catalog `/Outlines` root-type validation before WordPress document metadata, TOC/navigation, and remote action review rows are emitted.

## Dependency Closure

No new support component is needed. This patch reuses the native PHP PDF object parser, outline resolver, destination name-tree resolver, metadata extractor, TOC/navigation review paths, remote action review path, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.
