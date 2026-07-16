# EPUB definition list spine fixture

This increment adds a checked-in EPUB/native pair for `definition-list-spine.epub`.
The fixture stays small while exercising EPUB spine XHTML definition lists with
two terms, paragraph definitions, inline emphasis, and a nav TOC target. The
`.native` golden was generated with `/opt/homebrew/bin/pandoc -f epub -t native`
and matches the local EPUB reader under the normalized AST policy.

## Count deltas

- Checked-in EPUB package inputs: `59 -> 60`.
- Checked-in same-basename `.native` goldens: `59 -> 60`.
- Checked-in fixture identity files: `118 -> 120`.
- Package feature coverage: `fixtureCount=60`, `navFixtures=55`, `metadataCreators=56`, `manifestItems=218`, `readingOrderItems=91`, `resourceKinds` includes `navigation=61` and `xhtml=87`.
- Package feature signature: `99394abff01ecaeb8afc423c0333104103f95e21648807565f71cce8d928a851`.
- Normalized native AST signature: `64e0a78d0b545f7f74f93d4a3bb536bd988464d94a029f0d0e91dd8ca46395e2`.

## Fixture hashes

```text
0baab26570c728b891093f14904fcebd543708c902091e851dce748a76ab2fa0  definition-list-spine.epub
c63677d9de4ea45d6fe74f5d68f433d73a2b2ec9076a803ecf4c6d7a5e5d78dd  definition-list-spine.native
```

## Gates

```sh
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=60 --require-native-readiness=60 --require-mapped-parity=60 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
php tools/pandoc-epub-executable-native-ast.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc summary --require-executable-parity=60 --require-pandoc-version='pandoc 3.10'
php tools/pandoc-epub-reader-evidence.php --checked-in-fixtures --pandoc-bin=/opt/homebrew/bin/pandoc --require-native-ast-package-parity --require-executable-native-ast-parity --require-pandoc-version='pandoc 3.10' --require-runner-plan --require-no-validation-issues
```

Expected summaries:

```text
packages: total=60 compared=60 packageParsed=60 readerParsed=60 packageFailures=0 readerFailures=0
normalizedAst: matches=60 (100.00%) mismatches=0
fixtureIdentity: status=valid-checked-in-current-epub-fixture-identity expected=120 observed=120
nativeAstPackageParity: totalEpubCount=60 normalizedAstMatchCount=60
executableNativeAstParity: totalEpubCount=60 normalizedAstMatchCount=60 pandocNativeFixtureMatchCount=60
```

The upstream Haskell/Tasty EPUB runner remains planned-not-run; this is
checked-in package/native/executable evidence, not a full upstream runner claim.
