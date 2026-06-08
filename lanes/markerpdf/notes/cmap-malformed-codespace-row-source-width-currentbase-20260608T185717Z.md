# markerpdf-cmap-source-width-fallback-current-base-20260608T185717Z

- Lane: markerpdf
- Session: port-dev-markerpdf-source-width-20260608T185717Z
- Accepted base: be1daac3955666cd7f4550d89b27b78d713e0ae0
- Scope: native PHP searchable-PDF CMap/font-width behavior only. GPU/model/OCR,
  Surya/Texify/Torch, PDFium, and external PDF tools were not invoked.

## Behavior

This slice covers Encoding CMaps whose declared `begincodespacerange` block has
a syntactically valid count but contains malformed non-hex rows, then later
defines local `begincidchar` or `begincidrange` source mappings. Without valid
codespace rows, those local source-byte-to-CID mappings are unsafe for
source-width fallback because they can apply wide CID widths to direct source
bytes and erase the spacing signal between words.

`PdfTextExtractor::cMapHasUnderdeclaredCodeSpaceRangeBlock()` now treats a
declared codespace block as unsafe when row parsing reports malformed rows, the
top-level hex-pair fallback cannot recover any token rows, and no valid hex
pairs remain. `parseCidCMap()` already uses that unsafe signal to skip local
CID char/range maps, so source-width fallback returns to the direct source CIDs
and preserves the WordPress-visible `Wide Thin` gap.

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction through PDF parser
font/CMap state before the converter emits Markdown/WordPress text. In this
native PHP port, the equivalent no-GPU source of truth is the parser path that
decodes Type0 Encoding CMaps, ToUnicode maps, CIDFont `/W` widths, and styled
text geometry without OCR/model fallback.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMalformedCodespaceRowSourceWidthCurrentBaseTest.php`

Result: `1 test files, 2 assertions, 2 failures`. Both cidchar and cidrange
variants joined `WideThin` because the malformed codespace-row CMap mapping was
still applied to source bytes before width fallback.

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMalformedCodespaceRowSourceWidthCurrentBaseTest.php`

Result: `1 test files, 24 assertions, 0 failures`.

Adjacent family check:

`php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfCMap*SourceWidth*Test.php' -o -name 'PdfFontCid*Width*Test.php' -o -name 'PdfFontCMap*Width*Test.php' -o -name 'PdfFontType0*Width*Test.php' \) | sort)`

Result: `49 test files, 1054 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-cmap-malformed-codespace-row-source-width-currentbase.php`

Result: exits 0 with cidchar and cidrange malformed codespace rows rejected,
`source_width_word_gap_preserved=true`, and no Python/model/external PDF
execution flags.

Syntax and diff checks:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` -> no syntax errors
- `php -l lanes/markerpdf/tests/PdfCMapMalformedCodespaceRowSourceWidthCurrentBaseTest.php` -> no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-cmap-malformed-codespace-row-source-width-currentbase.php` -> no syntax errors
- `php -r '$json = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status json ok\n";'` -> lane-status json ok
- `git diff --check -- lanes/markerpdf` -> no output

Status delta for this isolated patch:

- `phpPass`: 3409 -> 3411
- `wordpressScenarios`: 2772 -> 2773
- mapped upstream inventory: unchanged

## Non-Overlap

This does not repeat the earlier malformed declared-count CID row-slot slices,
missing or malformed codespace declared-count slices, underdeclared encoding
codespace fallback, array-decoy CMap payloads, object-valued `UseCMap` prelude
handling, stream-filter predictor boundaries, xref repair, encryption preflight,
or OCR/model behavior. The new case is specifically a syntactically declared
codespace block whose row body contains no recoverable hex-pair source ranges.

## Dependency Closure

No new support component is required. The patch reuses the existing native PHP
PDF object scanner, CMap tokenizer/block parser, CID char/range parser, CIDFont
width parser, styled-span geometry path, and WordPress smoke harness. Remaining
OCR/model parity remains intentionally out of scope for the current no-GPU
markerPDF lane.
