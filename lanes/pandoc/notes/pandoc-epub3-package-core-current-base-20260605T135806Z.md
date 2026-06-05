# pandoc-epub3-package-core-current-base-20260605T135806Z

Base accepted HEAD: `7c27a6118223c3a795b10dae9f12e2e6310f566a`

## Source Truth

- Native PHP EPUB package behavior only under `lanes/pandoc`; no external Pandoc, Cabal, Haskell runner, `zip`/`unzip`, EPUBCheck, XMLDSig validator, DRM helper, remote fetch, or online service was used.
- EPUB OCF packages can carry optional reserved `META-INF/rights.xml` and `META-INF/signatures.xml` sidecars. This slice exposes bounded sidecar provenance, hashes, references, and missing/remote diagnostics for conversion handoff without validating cryptographic signatures or decrypting protected resources.
- Upstream Pandoc runner parity remains out of scope for this worker because no hydrated Pandoc/Cabal runner is available in this worktree.

## Implementation

- `EpubReader` now reports OCF sidecars at package level, in the import report, and on the document node attrs.
- `rights.xml` reporting preserves root metadata, language/base attributes, child entries, text values, media types, package-root local references, remote references, missing local diagnostics, byte length, CRC32, and SHA-256.
- `signatures.xml` reporting preserves XMLDSig signature IDs, canonicalization and signature methods, signature value presence, package-root reference targets, fragments, digest methods/values, existence diagnostics, byte length, CRC32, and SHA-256.
- The WordPress EPUB handoff example now includes rights/signature sidecars and reports their handoff counts while keeping fixture mutation name-based instead of position-based.

## Verification

- Baseline before this test cluster: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` -> `1 test files, 843 assertions, 0 failures`.
- Red-first check after adding the focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` -> `1 test files, 844 assertions, 1 failures` because the `ocf` sidecar report was absent.
- After implementation: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` -> `1 test files, 899 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test` -> `epub3 package handoff self-test ok`.
- PHP lint: `php -l lanes/pandoc/src/EpubReader.php`, `php -l lanes/pandoc/tests/EpubReaderTest.php`, and `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php` -> no syntax errors.
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane json ok\n";'` -> `lane json ok`.
- Whitespace check: `git diff --check -- lanes/pandoc` -> passed with no output.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `935 -> 936`.
- Native mapped check count: `1391 -> 1392`.
- EPUB focused cases: `32 -> 33`.
- Focused EPUB assertions: `843 -> 899` (`+56`).

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP EPUB reader/package helpers and AST/WordPress handoff path. Full XMLDSig validation, DRM/decryption, EPUBCheck-style validation, external remote-resource resolution, and upstream Pandoc runner parity remain separate out-of-scope dependency work.

## Non-Overlap

This does not repeat accepted OCF mimetype/container validation, OPF metadata/manifest/spine parsing, unique identifier handling, nav/NCX extraction, guide/collections, media overlays, remote manifest reference classification, encryption/obfuscated font reporting, or XHTML asset handoff. The new behavior is only bounded OCF `rights.xml` and `signatures.xml` sidecar preflight.

## Follow-Up

- A later slice can add cryptographic XMLDSig verification if a bounded native validator is introduced.
- A later slice can add DRM/decryption policy reporting beyond static sidecar provenance.
- XHTML-to-AST, CSS cascade, media export, and full EPUBCheck-style conformance remain separate package/conversion slices.
