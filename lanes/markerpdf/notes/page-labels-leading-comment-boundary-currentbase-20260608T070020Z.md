# markerpdf-page-labels-leading-comment-boundary-current-base-20260608T070020Z

## Source Truth

- Upstream markerPDF routes searchable PDF text through parser-backed PDF/PDFium layers before WordPress-style block rendering; the no-GPU PHP port owns this native PDF token boundary.
- PDF comments are lexical whitespace outside literal strings. pypdf's `/PageLabels` handling resolves catalog number-tree dictionaries into page-local labels with physical page-number fallback only when labels cannot be resolved.

## Implementation

- `PdfTextExtractor` now lets `pageLabelDictionaryFromValueResolved()` call the comment-aware dictionary reader directly, so indirect PageLabels dictionaries that begin with `% ...` comments are still structural dictionaries.
- `PdfTextExtractor::pageLabelNameValue()` now skips PDF whitespace/comments before direct name tokens, so indirect `/S` style operands can begin with comments before `/D`, `/r`, etc.
- `MarkerAppPreview` mirrors the comment-aware dictionary/array token entry points for its local PageLabels fallback parser.

## Evidence

Red-first before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsLeadingCommentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps PageLabels leading comments as whitespace before indirect dictionaries and style operands
Expected: ['Cover-', 'Body 8', 'End-']
Actual: ['1', '2', '3']
1 test files, 1 assertions, 1 failures
```

Focused pass after the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsLeadingCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsNullValueBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsScalarCommentBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
PASS keeps PageLabels leading comments as whitespace before indirect dictionaries and style operands
PASS resets PageLabels ranges on direct and indirect null values before WordPress page metadata
PASS keeps PageLabels indirect scalar comments as whitespace before WordPress page metadata
3 test files, 36 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-leading-comment-currentbase.php
```

The smoke exits 0 and emits `leading_comment_dictionary_resolved=true`, `leading_comment_style_resolved=true`, `physical_fallback_labels_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted direct `/Nums`, indirect `/Kids`, `/Limits` ordering, null PageLabels resets, scalar trailing comments, escaped catalog names, PDFDocEncoding prefixes, object-stream PageLabels, top-level operand rejection, duplicate key behavior, trailer-root selection, encrypted preview fallback, outline/page-transition/action review, or image/filter metadata slices. The bounded behavior is only leading PDF comments before indirect PageLabels dictionaries and style-name operands.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object table, PageLabels number-tree parser, comment-aware token scanners, MarkerAppPreview metadata path, and WordPress smoke harness. Live OCR, Surya/Texify/Torch, pypdfium/PIL rendering, Python models, and exact upstream benchmark parity remain out of scope under the current markerPDF no-GPU directive.
