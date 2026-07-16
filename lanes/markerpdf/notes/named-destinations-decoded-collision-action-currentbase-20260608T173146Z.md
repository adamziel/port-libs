# Named Destinations Decoded Collision Action Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T173146Z`

Base: `bb0155ef4ba8e70b3abc02eb190fa91b5dd44102`

## Source Truth

- Upstream markerPDF keeps PDF navigation and link/action metadata separate from visible text extraction.
- PDF name-tree keys are byte-oriented PDF strings; two strings can decode to the same Unicode label while still being distinct keys.
- The existing standalone PHP named-destination extractor already preserved `name_bytes_hex` for this boundary, so this slice carries that raw-byte boundary into action, link, and outline destination resolution.

## Change

- `PdfActionReviewExtractor` now stores name-tree destinations under a raw PDF string byte key and keeps the decoded-name fallback only while it is unambiguous.
- `PdfOutlineExtractor` uses the same raw-byte lookup for outline, link, action, alias, and remote-go-to destination metadata while preserving decoded public labels.
- The focused fixture has two `/Names /Dests` entries that both decode to `Collision`: one ASCII literal string and one UTF-16BE hex string. Link annotations and outlines now resolve to separate page targets.
- The WordPress smoke verifies that only the safe URI appears as visible Markdown and named-destination operands remain metadata-only.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionActionBoundaryCurrentBaseTest.php`

Result: `1 test files, 18 assertions, 2 failures`; both decoded-collision actions resolved to the UTF-16BE target page.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionActionBoundaryCurrentBaseTest.php` => `1 test files, 40 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationDecodedCollisionBoundaryCurrentBaseTest.php` => `2 test files, 66 assertions, 0 failures`
- Named-destination focused family => `68 test files, 2343 assertions, 0 failures`
- Outline destination focused family => `17 test files, 991 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-named-destination-byte-collision-currentbase.php` => exits `0`

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted standalone destination metadata, duplicate key ordering, destination alias cycles, page-label limits, outline traversal, or table geometry work. The owned boundary is action, link, and outline resolution when distinct PDF string keys decode to the same label.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP tokenizer, PDF string decoder, action-review extractor, outline extractor, and link-span metadata path. GPU/model execution, OCR, Surya, Texify, raster rendering, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.
