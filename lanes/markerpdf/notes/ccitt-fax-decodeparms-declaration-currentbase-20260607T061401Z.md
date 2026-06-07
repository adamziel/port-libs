# CCITT Fax DecodeParms declaration boundary current-base slice

- Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260607T061401Z`
- Base accepted HEAD: `0b156309dc95b4072c2ccb7cc4b489a6967b1646`
- Source truth: markerPDF/PDFium treats CCITT Fax as an image/raster filter boundary. Under the current no-GPU markerPDF scope this port keeps CCITT raster bytes image-only, records native parser review metadata, and does not run OCR, PIL, pypdfium, Python, or model workers.

## Behavior

- Duplicate top-level `/DecodeParms` declarations on a CCITT Fax image dictionary now fail closed with `duplicate_ccitt_decodeparms_declaration_fail_closed`.
- The parser rejects the duplicate declaration itself instead of trusting either competing dictionary. The CCITT effective review therefore falls back to PDF defaults: `K=0`, `Columns=1728`, `Rows=0`, `BlackIs1=false`, `EncodedByteAlign=false`, `EndOfLine=false`, `EndOfBlock=true`, and `DamagedRowsBeforeError=0`.
- Inline-image `/DP` duplicate declarations use the same fail-closed review and expose the same payload-exclusion boundary.
- While reproducing the focused file, the accepted current-base test for trailing extra DecodeParms arrays exposed one adjacent CCITT alignment failure: a non-null DecodeParms entry paired with an explicit `null` filter slot was treated as stray. This patch treats explicit null filter slots as declared alignment slots while still rejecting genuinely unapplied non-null DecodeParms entries after the filter stack.

## Red-First Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`
- Result before source fix: `1 test files, 955 assertions, 2 failures`.
- Failures: the new duplicate top-level CCITT `/DecodeParms` declaration test accepted the first dictionary instead of failing closed; the existing trailing explicit-null filter slot case returned `k=null` instead of the valid aligned `k=-1` review.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` -> `1 test files, 1026 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeDecodeParmsDeclarationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeDuplicateDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfImageStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php` -> `5 test files, 1489 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-decodeparms-declaration-currentbase.php` -> exits `0`; reports `duplicate_ccitt_decodeparms_declaration_fail_closed`, `inline_duplicate_declaration_rejected=true`, `xobject_duplicate_declaration_rejected=true`, `payload_excluded_from_paragraphs=true`, and no Python/model/external PDF execution.
- `php -l lanes/markerpdf/src/PdfImageRenderer.php`, `php -l lanes/markerpdf/src/PdfTextExtractor.php`, `php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`, and `php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-decodeparms-declaration-currentbase.php` -> no syntax errors.
- `git diff --check -- lanes/markerpdf` -> no output.

## Non-Overlap

This does not repeat accepted CCITT image-only review, CCITT DecodeParms parameter validation, duplicate fields inside one DecodeParms dictionary, unresolved/malformed DecodeParms operands, prefix native-filter decoding, EOFB/RTC/EOL tokenizer ownership, image-mask polarity, filter operand fail-closed review, duplicate `/Filter` declarations, DCT/JBIG2/JPX declaration boundaries, or generic stream-filter extra DecodeParms handling. The new behavior is specifically duplicate top-level CCITT `/DecodeParms` declarations plus the explicit `null` filter-slot alignment repair needed by this current-base focused file.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP dictionary parser, image renderer review metadata, image XObject review, and focused WordPress smoke path. Full CCITT raster decoding/OCR/model parity remains outside this no-GPU lane scope.
