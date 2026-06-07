# markerPDF outline next operand boundary current-base slice

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260607T143442Z`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF bookmarks/TOC as metadata through `marker/cleaners/toc.py::get_pdf_toc`, with page and destination resolution delegated to the PDF backend.
- Upstream visible text extraction remains separate through `marker/pdf/extract_text.py::get_text_blocks`; outline titles and remote action targets must not become page text.
- Native no-GPU boundary: outline sibling references are single PDF object operands. A malformed `/Next 8 0 R 7 0 R` is ambiguous, so WordPress outline metadata should preserve the first object id for review but must not traverse into stale sibling/action metadata.

## Red-First Evidence

Before the source patch, the new focused test failed on accepted base `d30a47d3f1909bba68426d3e20e0f67927b5f01d`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataNextOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects outline Next references with trailing operands before document metadata traversal
Expected: 1
Actual: 2
FAIL applies malformed Next operand boundary to TOC navigation and remote action review
Actual: stale GoToR action review row for stale-next-operand-review.pdf
1 test files, 16 assertions, 2 failures
```

## Implementation

- `PdfMetadataExtractor` now rejects selected outline traversal references with extra top-level operands before the next dictionary key for `/First`, `/Last`, `/Next`, `/Parent`, and `/Prev`.
- `PdfOutlineExtractor` now applies the same boundary to TOC, destination-view, navigation, action-review, and remote-GoTo sibling traversal.
- Row metadata still preserves the raw first `/Next` object number as review metadata, but traversal stops before stale outline rows and remote actions.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataNextOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects outline Next references with trailing operands before document metadata traversal
PASS applies malformed Next operand boundary to TOC navigation and remote action review
1 test files, 37 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-next-operand-boundary-currentbase.php
```

The smoke emits `stale_next_operand_excluded=true`, `remote_actions_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted outline root/type/title/comment/parent/missing-parent/Prev/Last/root-count/zero-count/generation/EOF/xref-owner, duplicate navigation key, catalog `/Outlines` operand, outline `/Metadata` operand, structure-element `/SE` operand, destination alias, page operand, name-tree, remote GoTo/GoToE, destination action context, transition/thread/page-label enrichment, xref repair, metadata, AcroForm, image, font, stream-filter, or encrypted-permission slices. The bounded behavior is only malformed top-level operands after selected outline sibling traversal references, proven with `/Next`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, outline resolver, metadata extractor, name-tree destination resolver, navigation review paths, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.
