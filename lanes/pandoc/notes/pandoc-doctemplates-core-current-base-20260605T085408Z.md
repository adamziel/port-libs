# Pandoc doctemplates core current-base 2026-06-05T08:54:08Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` included-partial final-newline
  handling with upstream doctemplates.
- Included partials now remove exactly one trailing LF from the rendered
  partial source instead of stripping every trailing CR/LF sequence.
- Partial rendering no longer runs the resulting string through the normal
  interpolated-variable final-newline stripper a second time, so a partial
  ending in two LFs preserves one LF.
- CRLF partial endings now preserve the CR after the upstream LF removal, and
  CR-only endings are left intact.
- Updated the WordPress doctemplate review-packet smoke with a partial that
  intentionally keeps one blank review spacer after its upstream final-LF
  omission.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents partial inclusion and says final
  newlines are omitted from included partials:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Parser` applies `removeFinalNewline` while
  loading partials; that helper removes one trailing `\n` byte and leaves other
  endings untouched:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Upstream `Text.DocTemplates.Internal` renders partials as already-rendered
  template content after pipe application, rather than resolving them through
  the interpolated-variable final-newline path:
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
  1 test file, 55 assertions, 0 failures.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 56 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators before or after pipes, `$it$`, `$^$`,
automatic multiline nesting, `$~$` breakable-space whitespace reflow,
parameter-free pipes, parameterized block pipes, Unicode display-width
padding, missing/null pipe handling, resource-map partial discovery,
path-style partial lookup, applied partial parsing, partial recursion guards,
braced directive tokenizer behavior, alpha overflow labels, boolean false
output rendering, Unicode identifier parsing, multiline control boundary
newline swallowing, empty standalone partial line swallowing, deterministic
map-pairs ordering, trailing separators after piped variables, or table
geometry colgroup metadata. It only changes exact final-LF omission for
included partial content.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`pandoc-doctemplates-core` renderer and keeps behavior in the lane-local
partial renderer. Applied-partial variable rebinding parity, full doclayout
width-sensitive wrapping, richer source-location diagnostics,
filesystem-backed template discovery beyond the existing resource map,
writer-extension template selection, default-template parity, and full
upstream Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
