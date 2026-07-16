# markerpdf-outline-metadata-boundary-current-base-20260606T023326Z

## Scope

- Lane: `markerpdf`
- Base accepted HEAD: `7e95b10d11f5767b21764022fd15eea3308c3829`
- Behavior cluster: native no-GPU PDF outline metadata boundary for indirect `/Title` operands.

## Source Truth

- Upstream markerPDF gets searchable-PDF text, document metadata, and outline TOC metadata through parser-backed PDF boundaries before OCR/model fallback.
- PDF outline item `/Title` is a text string. An indirect title reference can point at a string object, but an object body such as `(Title) /A ...` is not a single title value; silently importing the string prefix drops non-title action tokens.
- Existing rich outline/document metadata paths already rejected this malformed indirect-title shape. This slice applies the same fail-closed boundary to lightweight `PdfTextExtractor::extractOutlineMetadata()` so WordPress TOC metadata cannot diverge from navigation review.

## Patch

- `PdfTextExtractor::outlineItemsFromLinkedList()` now reads outline titles through `pdfSingleStringValueAfterName()`.
- Added a single-token string/name guard that resolves exact indirect references, accepts literal/hex/name tokens with only trailing whitespace/comments, and rejects referenced objects with extra tokens.
- Added focused coverage for lightweight `pdf_toc`, document outline metadata, navigation review, remote action exclusion, document Info preservation, and visible WordPress text isolation.
- Added `wordpress-pdf-outline-indirect-title-boundary-currentbase.php` smoke output for importer-facing metadata.

## Red First

Before source edits, an inline PHP probe over a PDF whose outline `/Title` referenced object `7 0 R` with body `(Malformed Indirect Outline Title) /A 8 0 R` reported:

```text
{"lite_titles":["Malformed Indirect Outline Title"],"outline_titles":[],"metadata_titles":[]}
```

That showed only `PdfTextExtractor::extractOutlineMetadata()` imported the malformed title.

## Evidence

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataIndirectTitleBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects indirect outline title objects with trailing action tokens in lightweight metadata
PASS keeps malformed indirect outline title actions out of visible WordPress text and navigation review

1 test files, 22 assertions, 0 failures
```

Outline family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataIndirectTitleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataTitleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataMalformedUtf16TitleBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDestinationActionChainCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 430 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-indirect-title-boundary-currentbase.php
```

Emits `malformed_indirect_title_excluded_from_lightweight_metadata=true`, `malformed_indirect_title_excluded_from_document_metadata=true`, `malformed_indirect_title_excluded_from_navigation_review=true`, `malformed_indirect_action_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Adjacent Broad Check

I also ran a broad adjacent command including `PdfTextExtractorTest.php`; it exposed two current-base ToUnicode `usecmap` failures unrelated to this outline title helper:

```text
FAIL inherits ToUnicode usecmap mappings before WordPress text extraction
FAIL guards cyclic ToUnicode usecmap inheritance and codespace counts before WordPress text extraction
5 test files, 1034 assertions, 2 failures
```

This broad command is not used as acceptance evidence for this slice because the patch only changes the lightweight outline title path.

## Non-Overlap

- Does not repeat accepted outline title direct malformed UTF-16 handling, untitled outline child traversal boundaries, `/Prev` and `/Last` sibling boundaries, name-tree destination/action context, root type/count boundaries, or xref/Prev outline row repair.
- This slice specifically covers indirect outline title object bodies with trailing extra tokens in lightweight `extractOutlineMetadata()`.

## Dependency Closure

- No new support component is needed. This reuses the native PDF object resolver, exact-reference lookup, PDF value tokenizer, outline metadata parser, and WordPress smoke path.
- GPU/OCR/model execution, Surya/Texify/Torch, Streamlit/FastAPI model workers, pypdfium/PIL rendering, and external PDF tools remain intentionally out of scope.

## Root Harness

- Not run - isolated micro-slice.
