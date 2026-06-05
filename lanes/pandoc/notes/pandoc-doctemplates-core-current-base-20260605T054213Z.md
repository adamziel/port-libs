# Pandoc doctemplates core current-base 2026-06-05T05:42:13Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` multiline control blocks with upstream
  doctemplates newline swallowing.
- Multiline `$if(...)$`, `$elseif(...)$`, `$else$`, `$for(...)$`, `$sep$`,
  `$endif$`, and `$endfor$` boundaries now drop the single structural newline
  used to put control directives on their own lines.
- Updated the WordPress doctemplate review-packet self-test to reject blank
  lines introduced by warning-list multiline controls.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents Pandoc-style templates with
  conditionals, loops, `sep`, partials, and pipes:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Parser` uses `skipEndline` after multiline
  `if`, `elseif`, `else`, `for`, `sep`, `endif`, and `endfor` control
  boundaries:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine,
  browser renderer, roff, Typst, MathJax, KaTeX, online sanitizer, or online
  conversion service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 46 assertions, 0 failures.
- Red-first focused run after adding the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed with
  1 test file, 47 assertions, 1 failure because multiline control directives
  leaked blank lines.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 47 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- Full lane-focused verification:
  `php tools/run-tests.php lanes/pandoc/tests` passed with 20 test files,
  7686 assertions, 0 failures, and 663 PASS lines.
- PHP lint passed for `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, `$~$` markers, parameter-free pipes, parameterized pipes, Unicode
display-width padding, missing/null pipe handling, inline partial arrays,
resource-map partial discovery, path-style partial lookup, applied partials,
partial final-newline handling, partial recursion guards, braced directive
tokenizer behavior, alpha overflow labels, boolean false output rendering, or
Unicode identifier parsing. It only changes the structural newlines around
multiline control directives.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode
display-width helpers, or upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`pandoc-doctemplates-core` renderer and token stream. Column-aware bare
partial line swallowing, line-source diagnostics, full doclayout wrapping for
`$~$`, filesystem-backed template discovery beyond the existing resource map,
writer-extension template selection, default-template parity, and full
upstream Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
