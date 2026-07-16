# Pandoc doctemplates core current-base 2026-06-05T04:13:21Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` boolean rendering with upstream
  doctemplates: `false` now interpolates as empty text instead of the literal
  string `false`.
- Covered the behavior in direct variables, list concatenation with literal
  separators, loop item rendering, map truth rendering, and non-text block-pipe
  passthrough.
- Updated the WordPress doctemplate review-packet smoke so a false reviewer
  audit flag stays silent in text and attributes.

## Source Truth

- Hackage `doctemplates-0.11.0.1` README documents boolean interpolation as
  rendering `true` for true values and empty text for false values, while maps
  render as `true`.
  https://hackage.haskell.org/package/doctemplates
- The same README lists condition truthiness separately, including boolean
  true and non-empty strings, so this slice changes output rendering without
  changing existing conditional semantics.
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
  1 test file, 43 assertions, 0 failures.
- Red-first probe before implementation:
  `False:<false> List:<true, false, kept>` showed false booleans leaking into
  rendered text.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 44 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- Lane JSON validation passed for:
  `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, `$~$` markers, parameter-free pipes, parameterized pipes, Unicode
display-width padding, inline partial arrays, resource-map partial discovery,
path-style partial names, applied partials, partial final-newline handling,
partial recursion guards, braced directive tokenizer behavior, alpha overflow
labels, or loop item scoping. It only changes boolean false output rendering.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `pandoc-doctemplates-core` renderer and in-memory resource map. Full
doclayout line wrapping for `$~$`, filesystem-backed template discovery beyond
the existing resource map, writer-extension template selection, default-template
parity, richer parser source-location diagnostics, and full upstream Pandoc
runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
