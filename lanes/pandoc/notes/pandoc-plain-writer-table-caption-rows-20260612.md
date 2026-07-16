# Pandoc plain writer table caption rows

Slice: `pandoc-plain-writer-table-caption-rows`

## Ship gate

Text-format verdict remains not shippable. The focused denominator-backed gate is 459 / 1,132 (40.5%):

| Surface | Local / upstream | Verdict |
| --- | ---: | --- |
| Markdown/CommonMark/GFM | 439 / 1,096 | Not shippable; extension and variant parity remains open. |
| LaTeX/TeX/math | 20 / 14 | Not shippable; local evidence is granular, but native LaTeX reader and math parity remain incomplete. |
| Wiki/roff text readers | 0 / 20 | Not shippable; readers are unsupported or need explicit deferral. |
| CSV/TSV readers | 0 / 2 | Not shippable; native table readers are unsupported. |
| Plain output | 3 local slices | Output-side evidence; this slice closes table caption and row flattening. |

## Implemented gap

`PlainWriter` now renders native table captions and table head/body/foot rows as readable plain rows instead of falling through to unstructured child text. The renderer preserves inline-formatted cell text, multi-block cell text, and existing wrapping diagnostics.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 file, 216 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 44 files, 73864 assertions, 0 failures.

No Pandoc binary, Cabal/Haskell runner, office suite, TeX/Typst engine, browser renderer, Node tooling, external validator, online service, live provider test, or live-service provider test was invoked.
