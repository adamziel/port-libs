# ODF Preformatted Paragraph Handoff

Micro-slice: `pandoc-odf-open-document-core-current-base-20260606T001001Z`

Pinned source truth: Pandoc `src/Text/Pandoc/Readers/ODT/ContentReader.hs` at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`. The upstream ODT paragraph reader checks resolved paragraph styles and maps `Preformatted_20_Text`, or a direct child of that style, to a code block by stringifying the paragraph inline content.

## Changes

- `OdfReader` now maps resolved `Preformatted_20_Text` paragraph styles and direct child styles into `code_block` AST nodes.
- The generated code block keeps ODF provenance via `odfPreformatted`, `styleName`, `style`, `data-odf-preformatted`, and `data-odf-style-name`.
- The ODF import report now includes `preformattedCodeBlockCount`.
- `OdfReaderTest` covers inherited preformatted style resolution, line-break and tab normalization inside code text, Markdown fenced-code handoff, and WordPress code block output.
- `wordpress-odf-open-document-handoff.php` now includes a source-code paragraph smoke path for WordPress import review.
- `lane-status.json` records `phpPass` `1117`; `UPSTREAM_TEST_MANIFEST.json` records mapped coverage `1569`.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` failed before implementation with `1 test files, 1011 assertions, 1 failures` because an inherited `Preformatted_20_Text` paragraph stayed a `paragraph` node.
- Focused green: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 1025 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` passed with `odf open document handoff self-test ok`.
- Syntax and metadata checks: `php -l` passed for changed PHP files, lane-status and manifest JSON decoded cleanly, and `git diff --check -- lanes/pandoc` produced no output.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `OdfReader`, `ZipPackage`, `MarkdownWriter`, `WordPressBlockWriter`, and the shared AST.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, office tool, online sanitizer, online service, or live provider test was executed for progress.

## Non-Overlap

This patch owns only ODF paragraph preformatted-style-to-code-block mapping. It avoids the accepted ODF text:tab, blockquote style, table-template, table-caption, section, form, field, citation, index, object, and table-span slices.

## Follow-Up

Keep language-aware source style metadata, export-side ODT preformatted writing, deeper inherited style-chain traversal, and full upstream Haskell runner parity as separate bounded slices.
