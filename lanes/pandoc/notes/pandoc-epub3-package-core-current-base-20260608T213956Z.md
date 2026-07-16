# EPUB3 Package Current-Base Form/Ping Side Effects

Slice: `pandoc-epub3-package-core-current-base-20260608T213956Z`
Base: `ba1acddf7dda63f41a17e1f25945a52ff91962c3`

## Scope

Added bounded native EPUB XHTML side-effect reporting for:

- `<form action>` submissions.
- Submit `<input>` / `<button>` controls with `formaction`.
- `<a ping>` target lists.

The scanner reports these as inert `side-effects` review metadata under `xhtmlResourceReport`, per-asset XHTML reports, and raw HTML AST block attributes. Side-effect URLs are deliberately not added to `contentReferences`, so remote form and ping targets do not inflate remote resource load reconciliation.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 2876 assertions, 0 failures`.
- Red-first focused run failed after adding the form/ping test until standalone submit controls were narrowed and side-effect diagnostic ordering was corrected.
- Final: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 2960 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test` passed.

Focused assertion delta: `+84`.
Lane status moves `phpPass` from `1876` to `1877`.
Manifest mapped denominator moves `2301` to `2302`; EPUB3 package-core cases move `6` to `7`; EPUB3 package-core assertions move `112` to `196`.

## Dependency Closure

No new support component is needed. This reuses native `ZipPackage`, `EpubReader` XHTML scanning, package-reference resolution, `WordPressBlockWriter`, focused EPUB tests, and the existing WordPress EPUB3 package handoff example.

No Pandoc, Word, LibreOffice, zip/unzip, external converters, online services, live provider tests, or live-service provider tests were run.

## Non-Overlap

This does not repeat accepted EPUB container, OPF metadata, spine, nav/NCX, media overlay, CSS, script, link, meta refresh, switch, trigger, semantic, CFI, or remote-resource declaration coverage. The new behavior is limited to active XHTML form/ping side-effect policy handoff and keeps those side effects separate from resource-loading remote references.

## Next

Good non-overlapping follow-ups: EPUB nav/NCX provenance gaps, media-overlay resource handoff edges, encrypted/obfuscated asset policy, or CSS cascade/export policy. Keep the work native PHP and bounded; do not shell out to Pandoc, office tools, archive tools, online services, or live providers.
