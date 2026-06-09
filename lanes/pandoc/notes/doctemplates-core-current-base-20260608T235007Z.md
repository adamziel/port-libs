# Doctemplates Core Current Base - Unbraced Dollar Delimiters

Slice: `pandoc-doctemplates-core-current-base-20260608T235007Z`

Accepted base: `e0b67d45019623ea8bcde064daa47ff3c086103d`

## Source Truth

Pinned upstream Pandoc commit: `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

The upstream doctemplates parser opens `$...$` with `pOpenDollar`, then parses
`pVar`, `pSep`, and `pBlockBorders` before consuming the close delimiter.
`pSep` accepts any characters except `]`, and block-pipe quoted borders accept
any non-quote character or escaped character, so literal dollar signs inside
those bounded spans are directive content rather than closing delimiters.

Static reference inspected only:
https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs

No Pandoc executable, Cabal solver/build/test command, Haskell runner, external
template engine, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Implementation

- Added `DocTemplate::findDollarDirectiveClosing()` so unbraced `$...$`
  tokenization skips over quoted pipe-border strings and bracketed separators
  before choosing the closing dollar delimiter.
- Preserved upstream separator behavior where `[` inside a separator is
  content and the first `]` closes the separator.
- Added focused tests for dollar-valued variable separators, applied-partial
  separators, direct partial block-pipe borders, escaped dollar borders, and
  unbraced separators containing `[` content.
- Extended the WordPress doctemplate review-packet smoke with the same
  dollar-valued separator and border behavior.
- Added lane manifest metrics for the mapped doctemplate dollar-delimiter
  support case.

## Evidence

Baseline before this patch:

- `php -r 'require "tools/bootstrap.php"; $r = new PortLibs\Pandoc\DocTemplate(); echo $r->render("Cost: $" . "title/left 8 \"$\" \" USD\"$", ["title"=>"42"]);'`
- Result: failed with `Unclosed doctemplate $...$ directive at <template>:1:31`
- `php -r 'require "tools/bootstrap.php"; $r = new PortLibs\Pandoc\DocTemplate(); echo $r->render("Rows: $" . "sources[ $ ]$", ["sources"=>["media","links"]]);'`
- Result: failed with `Unclosed doctemplate $...$ directive at <template>:1:20`
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
- Result: `1 test files, 1092 assertions, 0 failures`

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
- Result: `1 test files, 1093 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
- Result: `OK wordpress doctemplate review packet`

Required verification:

- `php -l lanes/pandoc/src/DocTemplate.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/DocTemplateTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
- Result: no syntax errors
- JSON metadata validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc`
- Result: passed

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice only changes unbraced `$...$` delimiter scanning for parser spans
that already belong to variables, partials, and applied partials. It does not
repeat accepted doctemplate map-pairs, applied-partial rebinding, breakable
space wrapping, braced separator parsing, variable separator order validation,
Unicode diagnostic columns, default Markdown/CommonMark, Beamer, man/ms, HTML,
Jira/wiki, OPML/Djot/Textile/Markua/Haddock/TEI/XWiki/ZimWiki, EPUB, JATS,
Typst, or other default-template fallback clusters.

## Dependency Closure

No new support component is needed. This reuses the native PHP `DocTemplate`
tokenizer, variable/partial pipe parsing, focused `DocTemplateTest.php`
coverage, and the lane-local WordPress doctemplate review-packet smoke.

Full upstream runner parity remains gated on a reviewed non-mutating
Pandoc/doctemplates runner plan; this slice intentionally does not run Pandoc,
Cabal, Haskell test binaries, external template engines, or online services.

## Next

For doctemplate follow-up, choose a non-overlapping bounded parser/resource
gap such as additional `pEnclosed` error parity, default-template fallback edge
cases, or partial path resolution semantics.
