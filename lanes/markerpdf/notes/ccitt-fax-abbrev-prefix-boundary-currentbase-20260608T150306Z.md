# CCITT Fax Abbreviated Prefix Boundary Current Base

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260608T150306Z`

Accepted base: `daf1297ec7ca92d379d36088cbd404afd750eb24`

## Behavior

Native image-filter reviews now expose `canonical_native_prefix_filters` for
CCITT Fax boundaries when declared native prefixes use PDF abbreviations such
as `/AHx`, `/RL`, or `/Fl` before a preview-only `/CCF` stage. The declared
`filters` and `native_prefix_filters` remain source-preserving, so accepted
`A85`/`CCF` alias metadata is not renamed.

The focused fixture keeps RunLength and Flate streams owned past stale
`endstream` markers embedded in the native-prefix bytes until the decoded CCITT
EOFB boundary is reached, and it verifies that decoded image payload text is
not emitted as WordPress-visible paragraphs or review JSON.

## Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxAbbreviatedPrefixCurrentBaseTest.php`
  - `1 test files, 78 assertions, 0 failures`
  - Adds 2 focused PASS cases.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php`
  - `1 test files, 1176 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-abbrev-prefix-boundary-currentbase.php`
  - exits 0 with visible text limited to the before/after paragraphs,
    `stream_filters=["RL","CCF"]`,
    `native_prefix_filters=["RL"]`,
    `canonical_native_prefix_filters=["RunLengthDecode"]`,
    `native_prefix_decoded=true`,
    and no model or external PDF tool execution.

## Dependency Closure

No new support component is needed. This reuses markerPDF's native PHP
ASCIIHex, RunLength, Flate, and CCITT preview-boundary stream handling. OCR,
Surya, Texify, Torch, raster model workers, and external PDF tools remain out
of scope under the current no-GPU markerPDF direction.

## Non-Overlap

This does not repeat the accepted primary long-form Flate CCITT prefix,
ASCII85 abbreviation smoke, CCITT DecodeParms alignment, DCT/JBIG2/JPX preview
filter metadata, or encrypted permission trust-boundary slices. The new surface
is the source-preserving XObject abbreviation metadata for `/AHx`, `/RL`, and
`/Fl` native prefixes before `/CCF`.
