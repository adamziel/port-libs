# Pandoc doctemplates core current-base 2026-06-05T11:36:45Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` explicit `$^$` nesting with upstream
  doctemplates when the next template source line dedents before the captured
  nesting column.
- Pending `$^$` nesting now stops before a dedented nonblank source line
  instead of padding that line and all following text under the earlier
  nesting point.
- Source-aligned literal continuation lines and blank lines inside nested
  content are preserved.
- Updated the WordPress doctemplate review-packet smoke with a dedented
  reviewer note boundary.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents `$^$` nesting and shows aligned
  continuation lines under the nesting point:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Parser` stores `nestedCol` for `$^$`; `pEndline`
  only remains inside nested parsing when the following source column reaches
  the nested column, so dedented lines are parsed outside the nested template:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Upstream `Text.DocTemplates.Internal` renders `Nested` as a template node
  over the content captured by the parser:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine,
  browser renderer, roff, Typst, MathJax, KaTeX, online sanitizer, online
  conversion service, or live service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 60 assertions, 0 failures.
- Red-first focused run after adding the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed with
  1 test file, 61 assertions, 1 failure because dedented closing and following
  paragraph source lines were still padded under the pending `$^$` column.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 61 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators before or after pipes, `$it$`,
automatic standalone multiline nesting, `$~$` breakable-space whitespace
reflow, parameter-free pipes, parameterized block pipes, display-width padding,
missing/null pipe handling, resource-map partial discovery, path-style partial
lookup, applied partial parsing and rebinding, partial final-newline handling,
partial recursion guards, braced directive tokenizer behavior, alpha overflow
labels, boolean false output rendering, Unicode identifier parsing, multiline
control boundary newline swallowing, empty standalone partial line swallowing,
deterministic map-pairs ordering, trailing separators after piped variables,
included-partial final-LF omission, explicit `$^$ display-width calculation,
literal prefixes before multiline variables, or source-aligned literal
continuation-line dedenting.

It only changes pending `$^$` handling for nonblank template text lines that
dedent before the captured source column. It does not touch ZIP/OPC package
primitives, YAML metadata, Citation/CSL, BibTeX/CSL, Markdown/HTML readers,
Markdown/WordPress writers, DOCX/ODT/EPUB or legacy-DOC parsing, table
geometry, math conversion, PDF handoff planning, archive compression, syntax
highlighting, XML/HTML5 DOM, charset/Unicode source primitives, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`DocTemplate` renderer and the existing `UnicodeText` display-column support.
Full doclayout width-sensitive wrapping, richer source-location diagnostics,
filesystem-backed template discovery beyond the existing resource map,
writer-extension template selection, default-template parity, partial CR-only
final-newline parity, and full upstream Pandoc runner parity remain separate
activation slices.

Root harness: not run - isolated micro-slice.
