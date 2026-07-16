# Pandoc doctemplates core current-base 2026-06-08T13:51:29Z

## Slice

- Added `templates/default.docbook4` to the native `DocTemplate` built-in
  resource registry and default partial fallback inventory.
- The bounded default resource renders the upstream pandoc-templates DocBook 4
  structure, including the XML declaration, DocBook XML 4.5 DOCTYPE,
  MathML-specific DOCTYPE branch, `articleinfo`, authors, date,
  include-before/body/include-after handoff, and custom resource override path.
- Extended the WordPress doctemplate review-packet smoke so DocBook 4 default
  handoff is visible alongside the existing DocBook 5 fallback without using
  Pandoc or an external template engine.

## Source Truth

- Hackage `doctemplates` documents the Pandoc template engine as supporting
  variable interpolation, conditionals, loops, pipes, and partials, with no
  automatic output escaping.
- The upstream `jgm/pandoc-templates` repository contains a distinct
  `default.docbook4` resource with DocBook XML 4.5 and MathML DOCTYPE
  branches. The current master commit inspected was
  `6d3b0e89f62a345022ebe14b21cf8fd1c9cc5baa` (`Updated templates for pandoc
  3.10`).
- No Pandoc binary, Cabal build/solver/test command, Haskell runner, external
  template engine, TeX/PDF engine, browser renderer, online service,
  live provider test, or live-service provider test was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 748 assertions, 0 failures.
- Red-first probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $class="PortLibs\\Pandoc\\DocTemplate"; $r=new $class(); try { echo $r->renderResource("templates/default", [], ["title"=>"DocBook 4 Review", "author"=>["Migration bot"], "date"=>"2026-06-08", "body"=>"<para>Body</para>"], null, "docbook4"); } catch (Throwable $e) { fwrite(STDERR, get_class($e).": ".$e->getMessage()."\n"); exit(1); }'`
  failed with `UnexpectedValueException: Missing doctemplate resource
  templates/default`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 773 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, `$it$`, `$^$`, automatic multiline nesting, `$~$`
markers, parameter-free or parameterized pipes, map-pairs ordering, missing
lookup pipe behavior, partial inclusion, partial final-newline handling,
partial recursion guards, path-style partial lookup, applied-partial variable
rebinding, braced separator parsing, default Markdown/CommonMark/man/ms/
Beamer/DocBook 5 fallbacks, filesystem resource loading, source-location
diagnostics, colon-qualified metadata names, or extension-qualified output
format lookup. It only adds the missing bounded DocBook 4 default-template
resource and its local custom override path.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`pandoc-doctemplates-core` renderer, built-in resource fallback registry,
resource-map custom overrides, and WordPress doctemplate review-packet smoke.
Full upstream Pandoc runner parity, Cabal/Haskell doctemplates tests, external
template engines, and broader default-template drift audits remain separate
activation slices.

Root harness: not run - isolated micro-slice.
