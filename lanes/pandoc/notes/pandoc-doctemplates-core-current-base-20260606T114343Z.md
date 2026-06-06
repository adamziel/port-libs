# Pandoc Doctemplates Core Current Base - Default LaTeX Fallback

Slice: `pandoc-doctemplates-core-current-base-20260606T114343Z`
Base accepted HEAD: `fd4302f81958dd876e69577b59b33a8b2822f137`

## Source Truth

- Pinned Pandoc upstream commit remains `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
- Source behavior used for this slice: Pandoc ships writer default templates as data resources, and the default LaTeX template shape includes documentclass/class options, geometry, title/author/date, abstract, includes, TOC/list blocks, front/back matter, line stretch with the setspace package, bibliography handoff, and body placement.
- Existing native `DocTemplate` resource resolution already mapped default Markdown/CommonMark aliases. This slice extends that bounded support to `templates/default.latex` and `renderResource('templates/default', ..., 'latex')`.

## Implementation

- Added a bounded native default LaTeX template resource to `DocTemplate`.
- Covered LaTeX writer alias fallback through `templates/default`.
- Preserved caller-supplied `templates/default.latex` overrides.
- Extended the WordPress doctemplate review-packet self-test with a LaTeX default-template handoff.

## Evidence

- Baseline focused command before adding the failing assertion:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  Result: `1 test files, 199 assertions, 0 failures`.
- Red-first focused command after adding the LaTeX default-template case:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  Result: failed with `Missing doctemplate resource templates/default`.
- Passing focused command after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  Result: `1 test files, 229 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  Result: `OK wordpress doctemplate review packet`.

## Non-Overlap

This does not repeat the accepted doctemplate Markdown/CommonMark default fallback, map-pairs, applied-partial rebinding, braced separator, or breakable-space slices. It also does not run Pandoc, Cabal, Haskell runners, external template engines, TeX/PDF engines, browser renderers, online services, live provider tests, or live-service provider tests.

## Dependency Closure

No new support component is required. The slice reuses native PHP `DocTemplate` parsing/rendering, built-in resource lookup, writer alias fallback, in-memory resources, and the focused PHP test harness. Full upstream runner parity remains gated on a hydrated Pandoc checkout plus Cabal package/project files and Tasty executable closure for `test-pandoc` and `test-pandoc-lua-engine`.

## Follow-Up

Keep fuller upstream default-template parity, LaTeX partials, Beamer/ConTeXt/Typst/HTML defaults, filesystem/HTTP template discovery, source-location diagnostics, and full doclayout value modeling as separate bounded slices.
