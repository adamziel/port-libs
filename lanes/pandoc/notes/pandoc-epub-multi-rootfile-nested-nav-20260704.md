# EPUB multi-rootfile nested navigation fixture

This increment adds a checked-in EPUB 3 package/native pair for `multi-rootfile-nested-nav.epub`. The fixture exercises multiple `container.xml` rootfiles, a nested selected OPF path, nav document TOC nesting, landmarks, page-list entries, a non-linear spine item, and a guide text reference.

## Count deltas

- Checked-in EPUB package inputs: `55 -> 56`.
- Checked-in same-basename `.native` goldens: `55 -> 56`.
- Checked-in fixture identity files: `110 -> 112`.
- Package feature coverage: `fixtureCount=56`, `navFixtures=51`, `landmarkFixtures=20`, `pageListFixtures=11`, `nonLinearSpineFixtures=15`, `manifestItems=207`, `readingOrderItems=87`, `opfParts=/OPS/book/package.opf:4`.
- Package feature signature: `9da1b80e9f255835c37df208a77c05c97c74411e980552b194226b2065023226`.
- Normalized native AST signature: `8016922b3dae3d6daacb060e8535d41aa9793ca5c4019e0067139b526c9cac60`.

## Fixture hashes

```text
d4d65c5c0c6db9dc89ddbe0545f7870815a770d1441be00211b865155a273961  multi-rootfile-nested-nav.epub
d6aaf8b80629420e9b3ea1854a751cca180bc408a0eac6c8a1513b83eb2aa96b  multi-rootfile-nested-nav.native
```

## Gate

```sh
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=56 --require-native-readiness=56 --require-mapped-parity=56 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
```

Expected summary:

```text
packages: total=56 compared=56 packageParsed=56 readerParsed=56 packageFailures=0 readerFailures=0
normalizedAst: matches=56 (100.00%) mismatches=0
fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=112 observed=112
packageFeatureCoverage: fixtures=56 nav=51 ncx=4 covers=5 landmarks=20 pageLists=11 pageListCfiFixtures=1 pageListCfiTargets=2 metadataCreators=52 manifestItems=207
nativeAstPackageParity: totalEpubCount=56 normalizedAstMatchCount=56
```
