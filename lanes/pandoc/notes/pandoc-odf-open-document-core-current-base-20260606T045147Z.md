# ODF/OpenDocument list prefix/suffix delimiter handoff - 2026-06-06

Slice: `pandoc-odf-open-document-core-current-base-20260606T045147Z`
Base: `bd267d6c7c3b75fd2d89153f838d469484d0ec30`

## Source truth

- Pinned upstream: `jgm/pandoc` `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- `Text.Pandoc.Readers.ODT.StyleReader.readListLevelStyle` reads `style:num-prefix`, `style:num-suffix`, `style:num-format`, and `text:start-value` from ODT list-level styles.
- `Text.Pandoc.Readers.ODT.ContentReader.toListNumberDelim` maps empty prefix plus `.` to period, empty prefix plus `)` to one-parenthesis, `(` plus `)` to two-parentheses, and all other explicit prefix/suffix shapes to the default delimiter.

## Implementation

- `OdfReader` now preserves `numPrefix` and `numSuffix` in parsed ODT list-style metadata.
- Ordered list AST nodes now expose the existing Markdown writer `delimiter` metadata for `period`, `one_paren`, `two_parens`, and default fallback, plus source `numberPrefix` and `numberSuffix` metadata when present.
- The WordPress ODF handoff example now includes prefixed/suffixed list styles and asserts the Markdown handoff markers while keeping HTML ordered-list output stable.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 1132 assertions, 0 failures`.
- Red-first: same command failed with `1 test files, 1133 assertions, 1 failures` because `numPrefix` was not parsed.
- After implementation: same command passed with `1 test files, 1169 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` passed with `odf open document handoff self-test ok`.

## Dependency closure

No new support component is needed. This reuses native PHP `OdfReader`, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter` behavior. No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, or live provider test was executed.

## Non-overlap

This slice avoids the accepted ODF text:tab, paragraph blockquote, and heading auto-identifier clusters. It covers only list style prefix/suffix delimiter mapping for richer ODT numbered-list conversion.
