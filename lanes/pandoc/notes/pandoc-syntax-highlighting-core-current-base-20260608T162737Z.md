# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260608T162737Z`
Base accepted HEAD: `b8fc3dc35cad8883c4e7b35e071a03df537be407`

## Behavior Added

- Added bounded TOML syntax-highlighting table-header parsing for quoted table
  and array-of-table segments.
- Simple unquoted `[table]`, `[table.child]`, and `[[package]]` headers retain
  the existing single `KeywordTok` handoff.
- Quoted table header segments now hand off bracket punctuation as
  `OperatorTok`, bare dotted names as `DataTypeTok`, and quoted names as
  `StringTok`.
- Extended the WordPress static-export fixture with a TOML review packet that
  covers a quoted array-of-tables header, a local date-time with trailing
  comment, dotted local-time metadata, and a quoted key.

## Source Truth

- Pandoc delegates code-block highlighting to Skylighting while preserving code
  block language classes, attributes, style selection, and start-line metadata.
- TOML syntax definitions distinguish table delimiters, bare keys, quoted keys,
  strings, local date/time values, and comments. This slice ports the bounded
  token handoff needed by the PHP lane, not the full KDE/Skylighting grammar or
  a validating TOML parser.
- No Pandoc, Cabal, Haskell, Skylighting, external highlighter, browser,
  JavaScript renderer, online service, or TOML parser was executed.

## Verification

- Baseline before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`:
  `1 test files, 1720 assertions, 0 failures`.
- Red-first check:
  `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`:
  `1 test files, 1716 assertions, 1 failures`; the quoted
  `[[theme."palette variants"]]` header was emitted as one keyword token.
- Final focused check:
  `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`:
  `1 test files, 1724 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`:
  `syntax highlighting handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/SyntaxHighlighter.php`,
  `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`:
  no syntax errors.
- Whitespace check:
  `git diff --check -- lanes/pandoc`: clean.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

- No new support component is needed. The patch reuses the lane-local
  `SyntaxHighlighter`, `MarkdownReader`, `AstNode`, and `WordPressBlockWriter`
  support already present for code block handoff.

## Non-Overlap

- This does not repeat accepted CSS, Rust, AsciiDoc, HCL, Typst, PHP
  attributes, or existing unquoted TOML alias/table/date-time coverage.
- This slice owns only quoted TOML table-header token boundaries plus the
  static-export review fixture metadata needed to prove the WordPress handoff.

## Follow-Up

- Full Skylighting/KDE syntax-definition parity, complete TOML validation,
  multiline TOML arrays/tables, and broader theme/config language aliases
  remain separate future slices.
