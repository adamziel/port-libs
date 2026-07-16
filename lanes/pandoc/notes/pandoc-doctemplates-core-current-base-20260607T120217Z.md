# Pandoc doctemplates core current-base 2026-06-07T12:02:17Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` variable parsing with colon-qualified
  metadata keys used by XML/ODF-style review packets, such as
  `style:font-name`, `style:name`, and `xlink:href`.
- Applied-partial parsing now skips namespace-style colons until the suffix is
  a real partial call, so `${ style:family:components/style-row() }` can bind
  the `style:family` value and still render through a path-style partial.
- Updated the WordPress doctemplate review-packet smoke with visible
  `style:*` metadata output.

## Source Truth

- The lane manifest already maps Pandoc doctemplate variable interpolation,
  dotted metadata lookup, applied partials, resource-map partial discovery, and
  Unicode identifier handling as bounded native PHP support rows.
- ODF/DOCX/OPC reader fixtures in this lane expose XML namespace-qualified
  metadata names (`style:*`, `text:*`, `office:*`, `xlink:*`) that need to
  survive review-packet template rendering without lossy key renaming.
- No Pandoc binary, Cabal build/solver/test command, Haskell runner, external
  template engine, Word, LibreOffice, zip/unzip, TeX/PDF engine, browser
  renderer, online service, live provider test, or live-service provider test
  was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 398 assertions, 0 failures.
- Red-first check before implementation:
  `php -r 'require "tools/bootstrap.php"; $r=new PortLibs\Pandoc\DocTemplate(); echo $r->render("<" . "$" . "style:font-name" . "$" . ">\n", ["style:font-name"=>"Atkinson"]);'`
  failed with `Unsupported doctemplate directive style:font-name`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 399 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, `$~$` markers, parameter-free pipes, parameterized pipes, map-pairs
ordering, missing lookup pipe behavior, partial inclusion, partial final-newline
handling, partial recursion guards, path-style partial lookup, applied-partial
variable rebinding for non-colon paths, braced directive tokenizer behavior,
default template fallbacks, filesystem resource loading, source-location
diagnostics, boolean false rendering, or Unicode-only identifier parsing. It
only extends variable identifier segments to accept namespace-qualified colon
parts and keeps applied-partial colon detection unambiguous.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`pandoc-doctemplates-core` renderer, resource-map partial discovery, and
WordPress doctemplate review-packet smoke. Full upstream Pandoc runner parity,
external template engines, and broader doclayout wrapping remain separate
activation slices.

Root harness: not run - isolated micro-slice.
