# Pandoc XML/HTML5 DOM Core Current Base

Slice: `pandoc-xml-html5-dom-core-current-base-20260604T012750Z`

Accepted base: `59ad35343f0b979589ac3a508925c996eae4a547`

## Behavior

- Added `XmlHtmlDomFragment` as a bounded native PHP support component for
  document-reader XML/HTML fragment handoff.
- HTML fragments are parsed through libxml with `LIBXML_NONET`, no external
  entity substitution, normalized lowercase element/attribute names, HTML5
  void element serialization, boolean attribute serialization, implicit
  paragraph close behavior, entity-safe text/attribute output, and retained
  comments.
- Unsafe HTML import surfaces are dropped with deterministic diagnostics:
  active elements such as `script`, event-handler attributes, unsafe
  `javascript:`/`vbscript:`/`data:` URL attributes, and unsafe CSS
  `javascript:`/`expression()` style payloads.
- XML fragments reject XML declarations plus `DOCTYPE`/`ENTITY` declarations,
  keep external resolution disabled, preserve prefixed namespace declarations
  needed by DOCX/ODT/OPC-style readers, and serialize empty XML elements as
  self-closing tags.
- Added a WordPress raw-HTML handoff smoke that turns a legacy HTML packet into
  a safe `wp:html` block while preserving safe links, images, line breaks, and
  reporting dropped unsafe surfaces.

## Evidence

- `php -l lanes/pandoc/src/XmlHtmlDomFragment.php`: no syntax errors.
- `php -l lanes/pandoc/tests/XmlHtmlDomFragmentTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-xml-html-fragment-handoff.php`: no
  syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomFragmentTest.php`:
  1 selected test file, 35 assertions, 0 failures, 8 PASS lines.
- `php lanes/pandoc/examples/wordpress-xml-html-fragment-handoff.php --self-test`:
  `xml/html fragment handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`: 8 selected test files, 2873
  assertions, 0 failures, 309 PASS lines.
- `git diff --check -- lanes/pandoc`: passed.

## Non-Overlap

This slice does not repeat accepted Markdown reader/writer behavior, accepted
HTML table/list/blockquote/inline reader branches, ZIP/OPC package primitives,
DOCX body parsing, CSL citation handoff, YAML metadata handling, math/TeX
handoff, doctemplate rendering, or the shared ZIP extra-field support. It adds
only the bounded XML/HTML DOM fragment support-library behavior needed by
future richer document readers.

## Dependency Closure

No new support component outside `lanes/pandoc/**` is needed. This is the
smallest native PHP XML/HTML fragment component for this slice and reuses the
existing PHP `DOMDocument`/libxml extension with non-network parsing. Full
HTML5 tree-construction parity, browser layout, JavaScript execution, CSS
evaluation, external sanitizers, Pandoc/Haskell runners, Word, LibreOffice,
TeX/PDF engines, zip/unzip, and online services remain out of scope.

Root harness: not run - isolated micro-slice.
