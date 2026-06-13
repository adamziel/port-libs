# Pandoc IPYNB Source Language Hint Diagnostics

Date: 2026-06-13
Base: `86131b4c`
Bead: `plib-vig7x`

## Scope

`IpynbReader` now records metadata-only per-cell language hint summaries from:

- `cell.metadata.language`
- `cell.metadata.language_info.name`
- `cell.metadata.vscode.languageId`
- `cell.metadata.jupyter.language`
- notebook-level `metadata.language_info.name` / `metadata.kernelspec.language` fallback

Each cell summary keeps the language hint beside its existing source digest and
fingerprint. The reader reports mismatched cell-vs-notebook language hints,
unknown-language cells, hint source buckets, and aggregate language-hint counts
without adding raw source text to diagnostic summaries.

## Verification

- `php -l lanes/pandoc/src/IpynbReader.php`
- `php -l lanes/pandoc/tests/IpynbReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/IpynbReaderTest.php`
  - 1 file, 177 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 45 files, 75532 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`

No Pandoc binary, Jupyter, Python notebook runner, Node tooling, browser
renderer, online service, live provider, or external validator was invoked.
