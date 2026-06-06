# Pandoc Charset/Unicode Width Core - Multi-Skin Emoji ZWJ Width

Slice: `pandoc-charset-unicode-width-core-current-base-20260606T050153Z`
Base: `d0a134019583244d26aaca02c539b68c5c2f018e`

## Change

- Extended `UnicodeText::graphemes()` so an emoji skin-tone modifier can attach
  to a later modifier-capable emoji base inside an existing ZWJ cluster.
- Kept multi-person emoji ZWJ sequences such as person-handshake-person and
  person-heart-kiss-person with skin tones on both people as one display
  cluster.
- Preserved the two-column display-width contract through split, pad, wrap, and
  WordPress charset review-table output instead of splitting the second skin
  tone into a standalone two-column cluster.
- Added a WordPress charset handoff audit row for multi-skin emoji ZWJ width.

## Source Truth

The bounded source-truth contract is Unicode extended grapheme behavior for
emoji modifier sequences and emoji ZWJ sequences, plus the existing Pandoc lane
display-width convention that emoji presentation clusters occupy two display
columns for Markdown table padding and review wrapping. This slice does not
ingest full Unicode grapheme-break data tables or terminal-profile-specific
emoji width variants.

The pinned Pandoc upstream checkout path referenced by the static manifest was
not present in this isolated worktree, and no upstream runner command was
executed. No Pandoc, Cabal solver/build/test command, Haskell runner, external
charset converter, browser renderer, online sanitizer, online service, or live
provider test was executed.

## Red-First Evidence

Baseline before adding the new focused case:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 506 assertions, 0 failures`

After adding the focused test and before restoring the implementation fix:

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 507 assertions, 1 failures`
  - Failure: the multi-person emoji ZWJ handshake sequence measured display
    width `4` instead of `2`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`
  - Result: `1 test files, 516 assertions, 0 failures`
  - Delta: `+1` focused PASS case / `+10` assertions.
- `php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`
  - Result: `charset unicode handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`UnicodeText` grapheme/display-width helper, Markdown display-padding behavior,
and WordPress charset handoff example. Full Unicode grapheme-break property
ingestion, broader emoji conformance fixtures, declared HTML/XML charset
sniffing, terminal-profile-specific emoji width variants, and full upstream
Pandoc Haskell runner parity remain separate bounded follow-up work.

## Non-Overlap

This does not overlap accepted UTF-8 repair, UTF-16/UTF-32 BOM handling,
single-byte legacy encodings, Shift_JIS/Windows-31J, EUC-JP, ISO-2022-JP,
Big5, GBK/GB18030, EUC-KR, HZ-GB-2312, Unicode normalization, emoji tag
sequences, basic skin-tone modifier width, text/emoji variation selector
clusters, emoji ZWJ variation sequences without a later skin-tone modifier,
supplementary East Asian wide ranges, ambiguous-width policy, Unicode separator
wrapping, default-ignorable controls, prepended format-control zero-width
accounting, Indic virama clusters, Myanmar/Khmer conjuncts, Markdown/HTML
reader behavior, table geometry, DOCX/ODF/EPUB/PDF, syntax-highlighting,
CSL/BibTeX, YAML, doctemplate, ZIP/OPC, or upstream-runner dependency audit
slices.
