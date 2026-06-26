# Citation CSL WordPress Bibliography Options Handoff

Issue: `plib-pt7dg.5`
Date: 2026-06-26

## Scope

This lane was limited to the WordPress definition-list handoff for CSL
bibliography options. The CSL processor already preserved `hangingIndent`,
`entrySpacing`, `lineSpacing`, and `secondFieldAlign` on the bibliography
`definition_list` AST node; the WordPress writer was dropping those values when
emitting the `<dl class="pandoc-csl-bibliography">` HTML block.

## Result

`WordPressBlockWriter` now emits the CSL bibliography option metadata as:

- `data-csl-hanging-indent`
- `data-csl-entry-spacing`
- `data-csl-line-spacing`
- `data-csl-second-field-align`

The focused bibliography-options handoff failure was reduced.

## Verification

Baseline observed before the patch on the clean rebased branch:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 5947 assertions, 19 failures`
- Relevant failing name: `applies bounded csl bibliography options to wordpress definition list handoff`

After the patch:

- `php -l lanes/pandoc/src/WordPressBlockWriter.php`: pass
- `php lanes/pandoc/examples/wordpress-citation-csl-bibliography-options-handoff.php --self-test`: pass
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`: `1 test files, 5950 assertions, 18 failures`

Remaining focused-test failure names after this patch:

- `applies bounded csl et al use last for truncated name lists`
- `applies bounded csl quotes and strip periods rendering attributes`
- `applies bounded csl punctuation in quote locale option`
- `infers pandoc json citation suffix locators for diagnostics`
- `assigns bounded csl citation-number variables from sorted bibliography order`
- `formats bounded csl citation-number text variables with number forms`
- `formats bounded csl numeric text variables with number forms`
- `collapses bounded csl citation-number ranges for numeric styles`
- `applies bounded csl near-note position conditionals for note citations`
- `applies bounded csl bibliography display parts for second field layouts`
- `surfaces bounded csl rendering formatting on bibliography display parts`
- `surfaces bounded csl rendering formatting on citation inline parts`
- `surfaces nested csl bibliography display parts from macros and choose branches`
- `surfaces csl bibliography display parts from names substitutes`
- `suppresses only rendered bounded csl choose substitute variables`
- `renders bounded csl first reference note number for note-style citations`
- `renders bounded csl first reference note numbers in note bibliography handoff`
- `sorts bounded csl note bibliography by first reference note number`
