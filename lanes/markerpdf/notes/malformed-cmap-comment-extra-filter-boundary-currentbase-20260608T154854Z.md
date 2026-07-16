# Malformed CMap Comment-Extra Filter Boundary Current Base

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py`, which delegates page text extraction to `pdftext.extraction.dictionary_output` before downstream Markdown/WordPress assembly: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

This no-GPU PHP lane owns the equivalent native parser boundary. PDF comments are lexical whitespace, so a CMap stream dictionary shaped like `/Filter /FlateDecode % comment\n/ASCIIHexDecode` contains an extra decoder-name operand after the declared filter. The native parser already failed the decode closed; this slice preserves that behavior and adds explicit review provenance via `extra_filter_operand_after_comment=true` on the offending filter operand.

## Implementation

- Added comment-boundary tracking for extra CMap stream filter operands in `PdfTextExtractor`.
- Propagated the boundary into `extractCMapStreamFilterLengthOwnerReview()` operand metadata as `extra_filter_operand_after_comment`.
- Added a focused filtered ToUnicode CMap fixture and a WordPress smoke that preserve fallback text while excluding decoded CMap payload text and recording the comment-hidden extra decoder.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapCommentExtraFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when a CMap Filter decoder name is hidden after a PDF comment (lanes/markerpdf/tests/PdfParserMalformedCMapCommentExtraFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: true
Actual: NULL

1 test files, 52 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserMalformedCMapCommentExtraFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserMalformedCMapCommentExtraFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-comment-extra-filter-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-comment-extra-filter-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapCommentExtraFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when a CMap Filter decoder name is hidden after a PDF comment

1 test files, 56 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapCommentExtraFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapReferenceExtraFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectArrayTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapPostDecodeParmsFilterBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
Result: all 37 selected tests passed.

6 test files, 1857 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-comment-extra-filter-currentbase.php --self-test
{"self_test_passed":true,"source":"native-pdf-cmap-comment-extra-filter-boundary","support_component":"pdf-text-dictionary-core","native_boundary":"CMap Filter extra decoder names hidden after PDF comments fail closed before WordPress paragraph import","safe_text_imported":true,"visible_text_excludes_cmap_program":true,"comment_hidden_extra_filter_rejected":true,"filter_resolution_failed":true,"paragraphs":["WP Comment Extra Safe Import"],"executes_python_or_models":false,"executes_external_pdf_tools":false}
```

Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed CMap filter boundaries for scalar/array/literal/dictionary filter values, selected indirect helper tails, stale/free filter owners, duplicate `/Filter` or `/DecodeParms`, escaped/unsupported filter names, DecodeParms fail-closed behavior, explicit CMap EOD enforcement, post-`endcmap` payload exclusion, literal-string operator decoys, nested `bfchar`/`bfrange` arrays, declared-count row slots, codespace row handling, UseCMap inheritance, WMode, CID source-width fallback, stream-filter stack comment-split references, xref repair, image/filter metadata, annotations, forms, security preflight, OCR/model handoffs, or supplied-boundary table/equation work. The bounded behavior is only review provenance for a CMap extra decoder operand hidden after a PDF comment boundary.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, stream filter review, CMap parser, searchable text fallback, and WordPress smoke path. No Python pdftext execution, PDFium, OCR, Surya/Texify/Torch, GPU/model execution, raster image decoding, live services, or external PDF tools were run.
