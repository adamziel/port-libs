# markerPDF page resource entry comment current-base

Slice: `markerpdf-page-resource-inheritance-current-base-20260606T085206Z`
Session: `port-dev-markerpdf-resource-inherit-20260606T085206Z`
Base accepted HEAD: `9bad70694349fdf8df2944b1d0fdaa86a6613e3b`

## Source Truth

Upstream markerPDF routes searchable-PDF text extraction through parser/PDF text layers before OCR/model fallback. In the native no-GPU scope, PDF comments are whitespace, including between operands of an indirect object reference. The existing page `/Resources` reference path already honored that boundary, but inherited resource entry references under `/Font` and `/Properties` still used compact regex parsing.

This slice makes inherited page resource entries use the token-aware indirect-reference reader, so entries such as `/Font << /F1 5 % comment\n 0 % comment\n R >>` resolve the same way `/XObject` entries and page `/Resources` references already do.

## Behavior

- `PdfTextExtractor::fontResourceMapsFromResourceDictionary()` now resolves Font resource entries through `topLevelResourceReferenceEntries()`, which preserves escaped names, top-level dictionary boundaries, duplicate last-entry behavior, and PDF comments as whitespace inside references.
- `PdfTextExtractor::markedContentPropertiesFromResourceDictionary()` now walks top-level property entries with the same tokenizer-backed indirect reference parsing instead of a regex, while preserving direct property dictionaries.
- The WordPress smoke proves ToUnicode font text and marked-content `/ActualText` win over raw glyph text and stale private page-resource decoys.

## Red-First Evidence

Command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceEntryCommentBoundaryCurrentBaseTest.php
```

Result before source edit:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL treats PDF comments as whitespace inside inherited resource entry references (lanes/markerpdf/tests/PdfPageResourceEntryCommentBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Comment entry inherited font text',
  1 => 'Comment entry inherited form text',
  2 => 'Comment entry inherited actual text',
)
Actual: array (
  0 => 'A',
  1 => 'Comment entry inherited form text',
  2 => 'Glyph actual leak',
)

1 test files, 1 assertions, 1 failures
```

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceEntryCommentBoundaryCurrentBaseTest.php
```

```text
Focused test run: 1 selected test files (root lock skipped)
PASS treats PDF comments as whitespace inside inherited resource entry references

1 test files, 19 assertions, 0 failures
```

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
```

```text
Focused test run: 23 selected test files (root lock skipped)
...
23 test files, 877 assertions, 0 failures
```

```bash
php lanes/markerpdf/examples/wordpress-pdf-page-resource-entry-comment-currentbase.php
```

Emits `font_entry_comment_reference_resolved=true`, `xobject_entry_comment_reference_resolved=true`, `property_entry_comment_reference_resolved=true`, `glyph_actual_text_excluded=true`, `stale_private_resource_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page `/Resources` comment-delimited references, wrapper references, direct dictionary tail fail-closed behavior, indirect null inheritance, explicit empty dictionaries, resource entry generation filtering, resource entry stream rejection, page Parent/Kids generation checks, catalog path recovery, escaped page-tree keys, Form XObject null-resource inheritance, optional-content Properties wrappers, image XObject inherited-owner review, xref/object-stream repair, annotation/form/security/image metadata, or OCR/model handoffs.

The bounded behavior is only PDF comments inside inherited resource entry references for Font and Properties lookup, with XObject coverage retained as the already-green comparison path.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary tokenizer, indirect-reference parser, page-resource resolver, font/CMap text extraction, marked-content property replacement path, page-boundary metadata extractor, and WordPress smoke harness. Live OCR, PDFium rendering, Surya/Texify/Torch model execution, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
