# Pandoc doctemplates core current-base 2026-06-04T23:36:36Z

## Slice

- Extended `PortLibs\Pandoc\DocTemplate` with bounded Pandoc `$~$`
  breakable-space marker support.
- The native string renderer now treats `$~$` as outputless template syntax,
  preserving the enclosed text for review-packet output instead of leaking
  marker directives or shelling out to Pandoc.
- Updated the WordPress doctemplate review-packet smoke so reviewer summaries
  and warning rows can use Pandoc-style breakable-space regions.

## Source Truth

- Pandoc User's Guide `Template syntax`, `Breakable spaces`
  (https://pandoc.org/demo/example33/6.1-template-syntax.html): `$~$` starts
  and ends template regions where spaces are breakable.
- Pandoc User's Guide `Template syntax`, `Pipes`: `nowrap` disables line
  wrapping on breakable spaces. This PHP renderer does not implement the
  doclayout line-wrapper backend, so the bounded behavior here is to omit the
  markers and preserve rendered text.
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, external template engine, TeX/PDF engine, browser renderer,
  roff, Typst, MathJax, KaTeX, or online service was executed.

## Evidence

- Red-first probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $renderer = new PortLibs\Pandoc\DocTemplate(); echo $renderer->render("A\$~\$long review line\$~\$", []);'`
  failed with `Unsupported doctemplate directive ~`.
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed:
  1 test file, 26 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed.
- Full focused lane, PHP lint, and whitespace checks are recorded in the final
  worker handoff.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
interpolated variables, conditionals, loops, separators, `$it$`, `$^$`,
automatic multiline nesting, parameter-free or parameterized pipes, or partial
inclusion/application. It does not touch ZIP/OPC package primitives, YAML
metadata, Citation/CSL, Markdown/HTML readers, Markdown/WordPress writers,
DOCX/ODT/EPUB/legacy-DOC parsing, table geometry, math conversion, PDF handoff
planning, archive compression, syntax highlighting, or upstream-runner
dependency audit behavior.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `pandoc-doctemplates-core` support row and extends the lane-local renderer
with a bounded outputless marker for breakable-space template regions. Full
doclayout line wrapping for breakable spaces, inferred partial file-extension
lookup, filesystem/user-data template discovery, and broader default-template
parity remain separate activation slices. Full upstream Pandoc runner parity
remains out of scope for this isolated micro-slice because the hydrated
upstream checkout is not available locally and running Cabal would require
rebuilding the broader Pandoc dependency graph.

Root harness: not run - isolated micro-slice.
