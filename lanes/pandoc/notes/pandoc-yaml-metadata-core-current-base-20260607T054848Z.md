# Pandoc YAML Metadata Current-Base Directive Provenance

Slice: `pandoc-yaml-metadata-core-current-base-20260607T054848Z`
Base accepted HEAD: `dc087e94f6b78bbae9da5c244c45cf03600924da`

## Behavior

Native MarkdownReader YAML front matter now records `%YAML` directive version provenance in `yamlMetadataDirectiveProvenance`.

- `%YAML 1.1` and `%YAML 1.2` are recorded as supported.
- Future/unsupported versions such as `%YAML 1.3` still allow metadata parsing, but add a `yamlMetadataDiagnostics` entry with `reason: unsupported-yaml-version`.
- Internal metadata transport keys are stripped from final `meta`.
- Directive provenance merges across multiple front-matter blocks like existing diagnostics and tag provenance.

This is a bounded support-library slice for Pandoc-style YAML/front-matter handoff. It does not invoke Pandoc, Cabal, Haskell runners, external YAML parsers, online services, live provider tests, or live-service provider tests.

## Focused Evidence

Baseline before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 3324 assertions, 0 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
1 test files, 3342 assertions, 0 failures
```

Delta: `+1` focused PHP PASS case and `+18` focused assertions.

WordPress smoke:

```text
php lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php --self-test
yaml metadata handoff self-test ok
```

Syntax checks passed for:

- `lanes/pandoc/src/MarkdownReader.php`
- `lanes/pandoc/tests/MarkdownReaderTest.php`
- `lanes/pandoc/examples/wordpress-yaml-metadata-handoff.php`

## Dependency Closure

No new support component is needed. The slice reuses native PHP YAML directive parsing, metadata diagnostics/provenance transport, and the existing WordPress YAML metadata handoff example.

Remaining out of scope: full upstream Pandoc/YAML parser parity, Cabal/Haskell runner parity, external YAML parser comparison, online services, live provider tests, and live-service provider tests.

## Non-Overlap

This avoids the accepted YAML explicit sequence-key, flow explicit null-key, top-level flow mapping, alias diagnostic path, quoted ambiguous field, and indented document-marker scalar slices. The new behavior is limited to `%YAML` version directive provenance and unsupported-version diagnostics.
