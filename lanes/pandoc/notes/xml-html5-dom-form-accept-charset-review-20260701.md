# XML/HTML5 DOM form accept-charset review

Slice: `plib-tgg5t` XML/HTML5 DOM core blocker.

`XmlHtmlDom::summarizeHtmlFragment()` now emits additive metadata-only review
fields for form-level `accept-charset` attributes. Existing `acceptCharsetRaw`
and `acceptCharsets` fields remain unchanged, while forms that declare the
attribute now also expose:

- raw and normalized charset tokens with case-insensitive token counts;
- duplicate, invalid-token, legacy non-UTF-8, empty-list, and missing-UTF-8
  issue codes;
- UTF-8 presence, conforming-state, and an explicit no-transcoding review flag.

The slice does not transcode form payloads, submit forms, fetch action URLs,
invoke browser form algorithms, or change serialized HTML. It only preserves
bounded reviewer metadata for direct XML/HTML DOM handoff while direct-format
parity remains active in blocker notes.

Focused validation:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomFormAcceptCharsetReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormAcceptCharsetReviewTest.php`
  - Result: 1 file, 33 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFormAcceptCharsetReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/XmlHtmlDomFormSubmitterActionProvenanceTest.php`
  - Result: 3 files, 6425 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDom*Test.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 63 files, 11755 assertions, 0 failures.

No external Pandoc, office-suite, TeX/browser-engine, Typst, Jupyter, Node,
ZIP/unzip, validator, or live-service tooling was used.
