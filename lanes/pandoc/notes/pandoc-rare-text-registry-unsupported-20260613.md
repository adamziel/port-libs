# Pandoc Rare Text Registry Unsupported Diagnostics

Mapped one bounded native PHP format-registry diagnostics case for rare Pandoc
text tokens outside the active wiki and roff lanes.

## Coverage

- Input tokens covered: `asciidoc`, `djot`, `fb2`, `haddock`, `muse`,
  `opml`, `org`, `pod`, `rst`, `t2t`, and `textile`.
- Output tokens covered: `asciidoc`, `asciidoc_legacy`, `asciidoctor`,
  `djot`, `fb2`, `haddock`, `markua`, `muse`, `opml`, `org`, `rst`,
  `texinfo`, `textile`, and `vimdoc`.
- `PandocFormatRegistry` now exposes rare text direction buckets, extension
  inference, AsciiDoc output aliases, unsupported summaries, parity counts,
  and extension-level unsupported diagnostics.

## Verdict

This is registry evidence only. Direct native PHP rare text reader and writer
parity remains explicitly unsupported, and no parser or writer implementation
class is registered for these formats.

## Metrics

- `phpPass`: `3321 -> 3322`
- `phpFail`: `0`
- `mapped`: `3280 -> 3281`
- `mappedPandocFormatRegistryRareTextUnsupportedCases`: `1`
- `pandocFormatRegistryRareTextUnsupportedAssertions`: `151`

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  passed `1` file, `1257` assertions, `0` failures.
- Rare-text filtered registry checks passed `4` tests, `151` assertions,
  `0` failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed `45` files, `74664`
  assertions, `0` failures.

No external Pandoc binary, format-specific CLI, browser renderer, Node tooling,
online service, live provider test, or external validator was invoked.
