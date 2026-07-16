# markerPDF Object Stream Nested Token Boundary

Session: `port-dev-markerpdf-objstm8-20260602T070540Z`
Micro-slice: `markerpdf-object-stream-nested-token-boundary-current-base-20260602T070540Z`
Base accepted HEAD: `2150870c3981148e8455712606b95ba150778d23`

## Source-Truth Boundary

Upstream markerPDF routes page text extraction through `marker/pdf/extract_text.py` `naive_get_text()` and `get_text_blocks()`, delegating PDF syntax recovery to pypdfium/pdftext before WordPress-ready block rendering. At the native PHP parser boundary, PDF object-stream payload bytes are stream data, so literal strings inside unfiltered `/ObjStm` members that contain tokens such as `obj`, `endobj`, `stream`, dictionaries, or arrays must not terminate the enclosing direct object.

## Native Behavior Added

`PdfTextExtractor::directObjectDefinitions()` now scans direct object bodies with PDF token awareness instead of ending at the first regex `endobj`. The scanner skips literal strings, hex strings, comments, nested dictionary/array tokens, and stream payloads before accepting an `endobj` boundary. This lets unfiltered object streams expand members whose dictionaries contain nested tokens and object-like literal strings while preserving the current xref-stream membership guard that excludes unlisted compressed members and unrelated fallback streams.

## Evidence

Red-first focused test before the source fix:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps unfiltered object stream members inside nested token boundaries before WordPress text extraction (lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php)
Values are not identical
Expected: array (
  0 => 'Nested object stream page',
  1 => 'Boundary parser survived',
)
Actual: array (
  0 => 'Nested object stream page',
  1 => 'Boundary parser survived',
  2 => 'Unreferenced nested fallback noise',
)

1 test files, 1 assertions, 1 failures
```

Green focused tests after the source fix:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS keeps unfiltered object stream members inside nested token boundaries before WordPress text extraction

1 test files, 9 assertions, 0 failures
```

```text
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect object stream length filter count and first offsets before WordPress text extraction

1 test files, 8 assertions, 0 failures
```

```text
Focused test run: 1 selected test files (root lock skipped)
PASS parses object streams through xref stream entries before WordPress text extraction
PASS suppresses object stream members when current xref free entries reserve a reused generation
PASS honors startxref current section before stale appended object-stream rebuild entries
...
1 test files, 407 assertions, 0 failures
```

The WordPress smoke `examples/wordpress-pdf-object-stream-nested-token-boundary-import.php` emits two Gutenberg paragraphs, `Nested object stream page` and `Boundary parser survived`, with `executes_python_or_models=false`, `executes_external_pdf_tools=false`, `recovered_catalog_from_object_stream=true`, `excluded_unreferenced_fallback_stream=true`, `excluded_unlisted_compressed_catalog=true`, and `ignored_fake_obj_endobj_tokens_in_stream_payload=true`.

Final lane verification:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-object-stream-nested-token-boundary-import.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests` passed with 59 files, 2479 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-object-stream-nested-token-boundary-import.php` emitted the expected native-only Gutenberg paragraphs and exclusion flags.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, stream decoder, xref stream parser, object-stream expansion path, page-tree walker, and content-token text extractor. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya, tabled, Texify, Torch, and live benchmark/runtime dependencies.

## Non-Overlap

This does not repeat accepted object-stream xref membership recovery, indirect object-stream `/Length`/`/Filter`/`/N`/`/First` recovery, object-generation free-entry reuse guards, latest startxref precedence, xref stream `/Index` and zero-width `/W` defaults, stream-filter error boundaries, stale `/Length` recovery, or linearized hint-table exclusion. The new behavior is specifically direct-object boundary parsing for unfiltered object-stream payloads that contain nested PDF tokens and fake `obj`/`endobj` strings.
