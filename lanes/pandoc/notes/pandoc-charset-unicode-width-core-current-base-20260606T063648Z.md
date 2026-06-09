# Pandoc Charset/Unicode Width Core - Javanese/Balinese/Sundanese Stacks

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T063648Z`
Base: `d4dda46ed040d030bc495044e837fd77aa4678e5`

## Change

- Extended the existing bounded virama/linker display-width handling in
  `UnicodeText` to cover Javanese pangkon, Balinese adeg-adeg, and Sundanese
  pamaeh consonant stacks.
- Kept those stacks as single display clusters for `displayWidth()`,
  `graphemes()`, `splitAtDisplayWidth()`, `splitByDisplayBreakpoints()`,
  `padDisplay()`, and `wrapByDisplayWidth()`.
- Added the same audit row to the WordPress charset handoff example so reviewer
  tables show the one-column stack behavior without invoking external tools.

## Source Truth

The bounded source-truth contract is Pandoc-style display-width accounting for
Unicode grapheme clusters used by Markdown table padding and reviewer wrapping:
combining/linker marks do not become visible columns and virama-style stacks
must not be cut between the linker and following consonant. This slice extends
the accepted Indic and Myanmar/Khmer stack behavior to the adjacent bounded
Javanese, Balinese, and Sundanese script ranges.

The pinned Pandoc upstream checkout path referenced by the static manifest was
not present in this isolated worktree, and no upstream runner command was
executed. No Pandoc, Cabal solver/build/test command, Haskell runner, external
charset converter, browser renderer, online sanitizer, online service, or live
provider test was executed.

## Red-First Evidence

Baseline before adding the focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 516 assertions, 0 failures`

After adding the focused test and before the implementation fix:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 517 assertions, 1 failures`
  - Failure: the new Javanese/Balinese/Sundanese stack case measured a stack
    width of `2` instead of `1`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 527 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+10` assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`UnicodeText` grapheme/display-width helper, Markdown display-padding behavior,
and WordPress charset handoff example. Full Unicode grapheme-break property
ingestion, additional script-specific linker ranges, terminal-profile-specific
emoji width variants, declared HTML/XML charset sniffing, and full upstream
Pandoc Haskell runner parity remain separate bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
single-byte legacy encodings, Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP,
Big5, GBK/GB18030, EUC-KR, HZ-GB-2312, Unicode normalization, emoji tag
sequences, skin-tone modifier width, text/emoji variation selector clusters,
emoji ZWJ variation sequences, multi-skin emoji ZWJ sequences, supplementary
East Asian wide ranges, ambiguous-width policy, Unicode separator wrapping,
default-ignorable controls, prepended format-control zero-width accounting,
Indic virama clusters, Myanmar/Khmer conjuncts, Markdown/HTML reader behavior,
table geometry, DOCX/ODF/EPUB/PDF, syntax-highlighting, CSL/BibTeX, YAML,
doctemplate, ZIP/OPC, or upstream-runner dependency audit slices.
