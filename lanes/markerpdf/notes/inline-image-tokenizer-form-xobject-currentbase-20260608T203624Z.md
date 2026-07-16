# Inline Image Tokenizer Form XObject Boundary Current Base

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction to parser-backed PDF text extraction before any image/OCR/model fallback. At that boundary, `BI ... ID ... EI` bytes remain inline image payload, while valid following page operators, including text-bearing Form XObject invocations, must stay available to visible text extraction for WordPress import.

Reference checked for no-GPU source truth: `sddai/markerPDF` `marker/pdf/extract_text.py` at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` uses `pdftext.extraction.dictionary_output` for searchable pages before OCR/model fallback.

## Behavior

Preview-only inline images such as JBIG2 can contain delimiter-looking `EI` bytes before the tokenizer can prove the image payload is complete. The existing fallback records a later candidate terminator, then closes at that candidate only when the segment before a still-later stray `EI` is recognizable PDF content.

This slice extends that segment validator with resource-aware Form XObject context during form expansion tokenization:

- page/form resource dictionaries collect known `/Subtype /Form` XObject names;
- the inline-image fallback validator treats `/FormName Do` as text-producing only when `/FormName` resolves to one of those Form XObjects;
- image XObject `Do` operators remain conservative and do not by themselves prove visible text.

The focused fixture has inline image payload bytes containing fake text and `rawtail`, then a real terminator, then `q cm /FormText Do Q`, then a stray `EI`. The tokenizer now closes before the form invocation, allowing normal Form XObject expansion to import `Visible Form XObject Boundary Text` while excluding inline image payload bytes and the `/FormText Do` operator token from paragraph text.

## Verification

Focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerFormXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS closes preview-only fallback before form xobject do followed by stray ei operator
1 test files, 12 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-form-xobject-currentbase.php
```

The smoke exits 0 and emits `form_xobject_text_imported=true`, `inline_payload_excluded=true`, `form_do_operator_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID`/`EI` sample floors, comment/NUL boundaries, nested dictionary/text-object decoys, JBIG2/CCITT/unsupported-filter payload closure, slash-delimited `EI`, direct/named/indirect marked-content ActualText/property boundaries, TJ/quote fallback, post-terminator comments, q/Q/cm/clipping/path/color/dash/text-state/shading/operator boundaries, Type3 metric fallbacks, image-mask dictionary tails, CMap source-width fallback, OCR/model behavior, or raster image decoding. The new boundary is specifically resource-aware Form XObject `Do` invocation after a preview-only inline image terminator and before a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, XObject resource reference resolver, Form XObject detector/expander, inline image dictionary parser, preview-only image fallback scanner, and WordPress smoke renderer. Live OCR, Surya/Torch/Texify, PDFium raster parity, and model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
