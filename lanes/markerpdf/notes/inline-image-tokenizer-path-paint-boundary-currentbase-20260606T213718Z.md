# Inline Image Tokenizer Path-Paint Boundary Current Base

## Scope

- Lane: markerpdf
- Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260606T213718Z`
- Accepted base: `04ca88552bf0295efe9452d727ef7ee43b7b6a35`

## Source Truth

PDF content streams may continue with valid path-painting operators after an
inline-image terminator candidate. The native tokenizer must keep preview-only
inline image payload bytes excluded, then recover visible text after valid
graphics path-paint operators and before later stray `EI` tokens. This is
native searchable-PDF parser behavior and does not require OCR, raster models,
Surya, Texify, Torch, external PDF tools, or upstream model parity.

## Change

- Added a focused current-base tokenizer case covering preview-only JBIG2 inline
  images followed by path-paint operators `S`, `s`, `f`, `f*`, `B*`, and `b`.
- Updated the WordPress smoke metadata and generated paragraphs for the same
  path-paint boundary.
- Updated the upstream manifest mapped current-base tokenizer behavior count
  from 1 to 2.
- Updated lane status evidence for this isolated slice.

Production parser code did not need a new branch: the current content-stream
path-state tokenizer already recognizes these path-paint operators as safe
boundaries. This patch makes that current-base behavior countable and guards it
against regression.

## Verification

- `php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`
  - `No syntax errors detected`
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php`
  - `No syntax errors detected`
- `php -r '$files=["lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json","lanes/markerpdf/lane-status.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . ": valid\n"; }'`
  - both JSON files valid
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`
  - `1 test files, 672 assertions, 0 failures`
- `php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name '*InlineImage*Test.php' -o -name '*ImageInline*Test.php' \) | sort)`
  - `11 test files, 1749 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php | rg "preview_only_path_paint|Path Paint|executes_python_or_models|executes_external_pdf_tools"`
  - `preview_only_path_paint_stray_ei_text_preserved_after_safe_boundary=true`
  - printed `Before Path Paint Stray`, `Visible Path Paint Before Stray`, and
    `Visible After Path Paint Stray`
  - reported `executes_python_or_models=false` and
    `executes_external_pdf_tools=false`

Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat earlier inline-image tokenizer slices for malformed BI
preambles, tight ID/EI separators, comments, NUL whitespace, vertical-tab
non-separators, sample-floor terminators, visible literal text, TJ arrays,
ActualText, same-line text/graphics prefixes, graphics-state `q/Q`, `cm`,
nonzero/even-odd clipping paths, XObject `Do`, marked-content `MP/DP`, color
state, pattern tint, shading paint, dash pattern, text-state operators,
compatibility sections, externally closed `Q`/`EMC`/`EX` scopes, open scoped
text before/after stray `EI`, or Type3 glyph metric operators. This slice is
bounded to path-paint operator sequences after preview-only fallback boundaries.

## Dependency Closure

No new support component is needed. The behavior reuses the native PHP
content-stream tokenizer and focused PDF fixtures. No GPU/model execution,
Python workers, OCR, external PDF engines, online services, or additional
runtime dependencies were used.
