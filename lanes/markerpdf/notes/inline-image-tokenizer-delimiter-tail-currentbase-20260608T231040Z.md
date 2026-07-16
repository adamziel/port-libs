# markerpdf inline image tokenizer delimiter-tail current-base

- Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260608T231040Z`
- Accepted base: `e4c5b8530d7050cd247624ff66dfa0499e76de2a`
- Scope: native no-GPU searchable-PDF text extraction and inline-image review planning. No OCR, Surya/Texify/Torch, PDFium/PIL, external PDF tools, or model workers were run.

## Source Truth

Upstream markerPDF treats `BI ... ID ... EI` image bytes as image payload, not searchable text. PDF inline-image dictionaries are malformed when a closing array or dictionary delimiter appears as an orphan operand after valid image keys, but the tokenizer still needs to fail closed so later `EI BT ... ET` bytes inside image data do not become WordPress paragraphs.

## Behavior

Before the patch, `readInlineImageToken()` returned `null` for orphan `]` and `>>` delimiters after valid `/W`, `/H`, `/CS`, and `/BPC` keys. `readInlineImageDictionary()` then abandoned the inline image, and text extraction leaked fake `BT ... Tj ... ET` payload bytes.

The patch routes that parse stop through the malformed dictionary tail scanner and accepts orphan `]` and `>>` as review-only malformed tail operands only after real inline-image keys have been seen. `PdfImageRenderer` now gives the same dictionaries an `inline_image_dictionary_operand_review_only` plan so raster preview remains failed closed.

## Red/Green Evidence

- Red before source fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerDelimiterTailBoundaryCurrentBaseTest.php`
  failed both new cases. Actual text lines included `Array Close Tail Inline Noise` and `Dictionary Close Tail Inline Noise`.
- Green focused test:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerDelimiterTailBoundaryCurrentBaseTest.php`
  => `1 test files, 34 assertions, 0 failures`.
- Adjacent inline-image tokenizer/tail family:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBareTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerDotNumericTailCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerNameTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerStructuredTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerDelimiterTailBoundaryCurrentBaseTest.php`
  => `6 test files, 931 assertions, 0 failures`.

## WordPress Smoke

- Added `lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-delimiter-tail-currentbase.php`.
- The smoke imports only the before/after WordPress paragraphs for both orphan delimiter cases, excludes payload noise/rawtail/filter names, marks the dictionaries review-only, and confirms preview fails closed.

## Non-Overlap

This does not repeat the accepted bare-word, dot-leading numeric, name-valued, literal/hex/direct-dictionary/array structured tail, indirect marked-content property, Form XObject text-producing resource, path construction, or text-positioning inline-image tokenizer slices. This slice owns only orphan closing delimiter tails that previously produced no token at all.

## Dependency Closure

No new support component is needed. The existing native PHP tokenizer, malformed-tail review plan, and focused `TestRunner` coverage are reused.
