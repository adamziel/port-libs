# EPUB page-list CFI navigation fixture

This increment adds a checked-in EPUB 3 package/native pair that exercises page-list targets whose href fragments are EPUB CFI expressions. The package/native harness now reports page-list CFI target coverage separately from ordinary page-list entries.

## Count deltas

- Checked-in EPUB package inputs: `54 -> 55`.
- Checked-in same-basename `.native` goldens: `54 -> 55`.
- Checked-in fixture identity files: `108 -> 110`.
- Package feature coverage: `fixtureCount=55`, `pageListCfiFixtures=1`, `pageListCfiTargets=2`, `manifestItems=203`.
- Package feature signature: `e75142b4b7a60c66daaeee8e86d39db4a59c17ed11f4ae6fdf1d1f62b2219a32`.
- Normalized native AST signature: `823efd0175361dcd665817fa99f41d1cbdb41b0da954f726bf6daf9fefdd5fac`.

## Fixture hashes

```text
88feb1210f770ffa341c907fe0f1b9a68c88677abf28021849e73197695d0a8f  page-list-cfi-navigation.epub
2ae9e1947ee8146d7d007041d4bfff8d8ca8dbfc99ced96851cade2160046500  page-list-cfi-navigation.native
```

## Gate

```sh
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=55 --require-native-readiness=55 --require-mapped-parity=55 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
```

Expected summary:

```text
packages: total=55 compared=55 packageParsed=55 readerParsed=55 packageFailures=0 readerFailures=0
normalizedAst: matches=55 (100.00%) mismatches=0
fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=110 observed=110
packageFeatureCoverage: fixtures=55 pageListCfiFixtures=1 pageListCfiTargets=2
nativeAstPackageParity: totalEpubCount=55 normalizedAstMatchCount=55
```
