# Pandoc JSON/Native Mixed Link Raw Containers - 2026-06-25

Hook bead: `plib-ftnaq`.

Scope: close the remaining mixed-content round-trip gap where inline runs with
links, raw inlines, and raw blocks sit next to nested block containers in
Pandoc JSON and native output. This stays inside the native PHP reader/writer
fixtures and does not invoke Pandoc, Haskell, filters, browsers, or external
validators.

## Implementation

`JsonWriter` and `NativeWriter` now normalize block-container payloads through a
shared inline-run flusher before serializing `BlockQuote`, `Div`, `Figure`, and
inline `Note` block lists. Inline runs are wrapped in `Plain` blocks, while
existing block children and raw block payloads keep their block identity. This
prevents invalid mixed children inside Pandoc block-list payloads and keeps the
reader round trip stable.

The focused fixture in `JsonReaderWriterTest` covers a document with link and
raw-inline runs before and after nested block containers, plus a raw block
inside the same payload shape, then round trips both JSON and native writer
output back through the corresponding readers.

## Verification

- `php -l lanes/pandoc/src/JsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/JsonReaderWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/tests/JsonReaderWriterTest.php lanes/pandoc/tests/LatexWriterTest.php lanes/pandoc/tests/MediaBagTest.php`
  - 4 files, 4,870 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 15 files, 22,873 assertions, 0 failures.
