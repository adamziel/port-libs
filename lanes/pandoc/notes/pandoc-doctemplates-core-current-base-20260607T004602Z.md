# Pandoc doctemplates core current-base 2026-06-07T00:46:02Z

## Scope

- Aligned `PortLibs\Pandoc\DocTemplate` automatic nesting with upstream
  doctemplates line-ending parsing for CR-only legacy template resources.
- Standalone indented variable and bare-partial lines now detect `\r`, `\n`,
  and `\r\n` boundaries before deciding whether multiline rendered content
  should be nested under the source indentation.
- Added a focused WordPress review-packet self-test probe for CR-only
  automatic nesting.

## Source Truth

- Pandoc User's Guide `Template syntax`, `Nesting` documents automatic nesting
  for standalone indented variables with multiline values:
  https://pandoc.org/demo/example33/6.1-template-syntax.html
- Hackage `doctemplates-0.11.0.1` documents doctemplates as Pandoc's template
  renderer and accepts CR, LF, and CRLF line endings in the parser source:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Parser` parses `\n`, `\r\n`, and `\r` in
  `pLineEnding`, so the PHP renderer should not make automatic nesting depend
  on LF-only source lines:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- No Pandoc binary, Cabal build/solver/test command, Haskell runner, external
  template engine, browser renderer, JavaScript runtime, online sanitizer,
  online service, live provider test, or live-service provider test was
  executed.

## Evidence

- Baseline focused run:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 327 assertions, 0 failures`.
- Red-first probe before the patch:
  `php -r 'require "tools/bootstrap.php"; $r = new PortLibs\Pandoc\DocTemplate(); $out = $r->render("<section>\r  \$body\$\r</section>", ["body" => "First\rSecond"]); var_export($out);'`
  - Result: `'<section>\r  First\rSecond\r</section>'`, showing the second
    CR-only body line was not indented.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  - Result: `1 test files, 328 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  - Result: `OK wordpress doctemplate review packet`.
- Syntax and whitespace checks:
  - `php -l lanes/pandoc/src/DocTemplate.php`
  - `php -l lanes/pandoc/tests/DocTemplateTest.php`
  - `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  - `git diff --check -- lanes/pandoc`

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiter trimming,
conditionals, loops, `sep`, parameter-free or parameterized pipes, Unicode
display-width padding, partial lookup, applied-partial rebinding, default
Markdown/CommonMark/LaTeX/Beamer/OpenXML/OpenDocument/EPUB3/Typst fallbacks,
braced separators, breakable-space wrapping, final newline stripping, or
parser source-location diagnostics. It only closes the LF-only automatic
nesting detector for CR-only template text.

## Dependency Closure

No new support component is needed. The patch reuses native PHP
`DocTemplate`, existing `UnicodeText` display-width indentation, and the
accepted WordPress doctemplate review-packet example path. Full upstream runner
parity still requires a hydrated pinned Pandoc checkout and explicit
authorization for Haskell/Cabal runner work.
