# pandoc-odf-open-document-core-current-base-20260605T174619Z

Base accepted HEAD: `165d00972e222ec74a0a4ac65ceaafba6ceef98e`

## Behavior

This slice maps bounded ODF inline index markers into reviewable Pandoc-like AST spans:

- `text:toc-mark`
- `text:alphabetical-index-mark` and `text:alphabetical-index-mark-start` / `text:alphabetical-index-mark-end`
- `text:user-index-mark` and `text:user-index-mark-start` / `text:user-index-mark-end`

The reader preserves marker fallback text, marker range content, ODF `text:*` metadata, WordPress `data-odf-index-mark-*` attributes, Markdown/WordPress span output, and `importReport.content.indexMarkCount`.

## Evidence

Red-first command:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result before implementation: `1 test files, 964 assertions, 1 failures`; the new inline index-mark case failed because `text:toc-mark` and `text:user-index-mark` text was dropped and no review spans were produced.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 992 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` -> `odf open document handoff self-test ok`
- `php -l lanes/pandoc/src/OdfReader.php && php -l lanes/pandoc/tests/OdfReaderTest.php && php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php` -> no syntax errors
- `git diff --check -- lanes/pandoc` -> no whitespace errors

Focused delta over the accepted ODF slice: `+1` PASS case and `+29` assertions in `OdfReaderTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP `OdfReader` inline parsing, `AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, and existing in-memory ODT/ZIP fixtures.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, `zip`/`unzip`, external converter, browser renderer, online sanitizer, or online service was executed.

## Non-Overlap

This does not repeat the accepted ODF text:tab normalization, paragraph blockquote/style modifier mapping, table/list/section/form/annotation/reference/sequence/bibliography/generated-index behavior, or OPC content-type inventory slice. It only covers inline index marks that previously had no native ODF review handoff.

## Follow-Up

Keep index source validation/coverage, table-caption style modifiers, advanced paragraph layout/style rendering, and full upstream Haskell runner parity as separate bounded slices.
