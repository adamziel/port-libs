# EPUB manifest fallback-style fixture

This increment adds a checked-in EPUB 3 package/native pair for `manifest-fallback-style.epub`. The fixture exercises an OPF manifest item with both `fallback` and `fallback-style`; the fallback resolves to XHTML while the fallback-style chain resolves to CSS, and the reading-order document stays simple so Pandoc 3.10 native AST parity remains exact.

## Count deltas

- Checked-in EPUB package inputs: `56 -> 57`.
- Checked-in same-basename `.native` goldens: `56 -> 57`.
- Checked-in fixture identity files: `112 -> 114`.
- Package feature coverage: `fixtureCount=57`, `navFixtures=52`, `guideReferences=21`, `manifestItems=212`, `manifestFallbackItems=16`, `manifestFallbacks=6`, `resolvedManifestFallbacks=6`, `usableManifestFallbacks=6`.
- Package feature signature: `397841b7dbf233ac0fd5197f48ff1b21344bbb5953af3ff3a57d969d55dddcbe`.
- Normalized native AST signature: `faec465e002abb376673d8e745246db73858586ec2f4da229c30ca05a4719725`.

## Fixture hashes

```text
e9c4c86b4fc4d167600f09b0daf4cafa4cd15763b833119209ed42d01ffd5f8f  manifest-fallback-style.epub
e5ab69b21a48e6f8b0f907ec5dfb1d07bd8942de45fb1aff6e8ce6159f40abb7  manifest-fallback-style.native
```

## Gates

```sh
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=57 --require-native-readiness=57 --require-mapped-parity=57 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
php tools/pandoc-epub-executable-native-ast.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc summary --require-executable-parity=57 --require-pandoc-version='pandoc 3.10'
```

Expected summaries:

```text
packages: total=57 compared=57 packageParsed=57 readerParsed=57 packageFailures=0 readerFailures=0
normalizedAst: matches=57 (100.00%) mismatches=0
fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=114 observed=114
packageFeatureCoverage: fixtures=57 nav=52 ncx=4 covers=5 landmarks=20 pageLists=11 pageListCfiFixtures=1 pageListCfiTargets=2 metadataCreators=53 manifestItems=212
nativeAstPackageParity: totalEpubCount=57 normalizedAstMatchCount=57
executableNativeAstParity: totalEpubCount=57 normalizedAstMatchCount=57 pandocNativeFixtureMatchCount=57
```
