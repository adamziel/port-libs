# pandoc-docx-openxml-core-current-base-20260604T190247Z

Base: `8472f7334b2926430a03f40ded45a873c583696c`

Source truth:

- Upstream Pandoc `Text.Pandoc.Readers.Docx` at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` documents DOCX link support as internal links to arbitrary bookmarks via span targets, and its reader maps non-dummy bookmarks to empty spans with the `anchor` class while suppressing `_GoBack`.

Implementation:

- `DocxReader` now maps `w:bookmarkStart` to an empty inline `span` with `id` set to `w:name` and class `anchor`.
- `w:bookmarkEnd` is consumed as a structural close marker and does not render.
- Dummy Word return bookmarks named `_GoBack` are suppressed.
- Existing `w:hyperlink w:anchor` output continues to render as `#anchor`, now with a preserved DOCX bookmark target in the AST, Markdown, and WordPress block HTML.

Focused evidence:

- Before this slice, `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 230 assertions, 0 failures`.
- After this slice, `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 249 assertions, 0 failures`.
- Broader focused lane check `php tools/run-tests.php lanes/pandoc/tests` passed with `11 test files, 3,376 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` passed with `docx body handoff self-test ok`.

Status delta:

- `phpPass`: `364 -> 365`
- mapped native checks: `821 -> 822`
- DOCX/OpenXML focused cases: `28 -> 29`
- DOCX/OpenXML focused assertions: `230 -> 249`

Dependency closure:

- No new support component is needed. This reuses the existing OPC package reader, DOCX XML parser, Markdown writer span attribute renderer, and WordPress inline span attribute renderer.

Exclusions:

- Did not execute Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF engines, MathJax, KaTeX, Typst, browser renderers, roff, or online services.
- Full upstream-runner parity remains gated on hydrating the Pandoc checkout and Cabal package/project files already described in lane status.
