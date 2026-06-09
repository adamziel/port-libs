# pandoc-doctemplates-core-current-base-20260609T071750Z

Base accepted HEAD: `606e24ec818a38feb2a796c2f2b7d182ce531afd`.

No `port-pandoc-*.needs-lane-rework.md` note existed for this lane at start.

## Source Truth

- Upstream fixture: `jgm/doctemplates` `0.11.0.1` `test/nest.test`.
- Static reference URL: `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/test/nest.test`.
- Bounded behavior mapped: indented standalone `$if$` / `$endif$` control lines inside an explicit `$^$` nesting loop should not contribute their source-line indentation to rendered loop output. The upstream fixture expects:
  - `1. Hello`
  - `     a`
  - `1. Hello`
  - `     b`

## Implementation

`DocTemplate` tokenization now recognizes standalone control directives when:

- the directive is a block control (`if`, `for`, `elseif`, `else`, `endif`, `sep`, or `endfor`);
- the directive is followed only by horizontal whitespace and then a line ending or EOF;
- the source prefix before the directive on that line is only spaces or tabs.

For those lines, the tokenizer drops the indentation-only source prefix before appending the text token, so explicit nesting from `$^$` is applied to rendered content without also inheriting indentation from control-only template lines. Inline loop controls and separator text remain unchanged because the prefix must be horizontal whitespace only.

## Focused Evidence

- Baseline before patch: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 1143 assertions, 0 failures`.
- Final focused test after implementation: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 1144 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  - `lanes/pandoc/src/DocTemplate.php`
  - `lanes/pandoc/tests/DocTemplateTest.php`
  - `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
- `git diff --check -- lanes/pandoc` passed.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.

Status delta:

- `lanes/pandoc/lane-status.json` `phpPass`: `2482` -> `2483`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2861` -> `2862`.
- Added one mapped doctemplate indentation/nesting case and one focused assertion.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `DocTemplate` tokenizer, parser, renderer, and WordPress doctemplate review example. Broader Pandoc/doctemplates runner parity remains out of scope for this isolated support-library handoff.

## Exclusions

Did not run Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF engines, browser renderers, online services, live provider tests, or live-service provider tests. Root harness was not run for this isolated micro-slice.

## Non-Overlap

This slice does not touch the previous BibTeX/CSL `@misc` document-type handoff, ODF/OpenDocument subtotal/drop-down metadata handoffs, ZIP/OPC package primitives, DOCX/ODT readers, citation processing, table geometry, YAML metadata, PDF handoff planning, or archive/compression helpers.
