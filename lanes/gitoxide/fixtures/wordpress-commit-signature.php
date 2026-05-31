<?php

declare(strict_types=1);

$body = "tree 0123456789abcdef0123456789abcdef01234567\n"
    . "parent 1111111111111111111111111111111111111111\n"
    . "author WordPress Importer <importer@example.test> 1710000000 -0230\n"
    . "committer WordPress Deploy Bot <deploy@example.test> 1710003600 +0000\n"
    . "encoding UTF-8\n"
    . "gpgsig -----BEGIN SSH SIGNATURE-----\n"
    . " c2lnbmVkLXdvcmRwcmVzcy1pbXBvcnQ=\n"
    . " -----END SSH SIGNATURE-----\n"
    . "mergetag object 3333333333333333333333333333333333333333\n"
    . " type commit\n"
    . " tag wp-release-2026.05\n"
    . " tagger WordPress Release Bot <release@example.test> 1710007200 +0000\n"
    . " \n"
    . " Release tag embedded for deployment provenance\n"
    . "\n"
    . "Import WordPress export\n\n"
    . "Source: wp-content/uploads/export.wxr\n\n"
    . "Signed-off-by: WordPress Importer <importer@example.test>\n"
    . "Co-authored-by: Content Reviewer <reviewer@example.test>\n"
    . "Acked-by: Plugin Maintainer <plugin-maintainer@example.test>\n"
    . "Reviewed-by: Deployment Reviewer <deploy-review@example.test>\n"
    . "Tested-by: QA Runner <qa@example.test>\n"
    . " staged import dry-run\n";

$lateStandardHeaderBody = "tree 0123456789abcdef0123456789abcdef01234567\n"
    . "author WordPress Importer <importer@example.test> 1710000000 -0230\n"
    . "committer WordPress Deploy Bot <deploy@example.test> 1710003600 +0000\n"
    . "parent 1111111111111111111111111111111111111111\n"
    . "encoding UTF-8\n"
    . "\n"
    . "Import with late standard headers\n";

$misorderedHeaderBody = "tree 0123456789abcdef0123456789abcdef01234567\n"
    . "committer WordPress Deploy Bot <deploy@example.test> 1710003600 +0000\n"
    . "author WordPress Importer <importer@example.test> 1710000000 -0230\n"
    . "\n"
    . "Import with reordered actors\n";

$oddTimestampBody = "tree 0123456789abcdef0123456789abcdef01234567\n"
    . "author WordPress Importer <importer@example.test> 1312735823 +051800\n"
    . "committer WordPress Deploy Bot <deploy@example.test> 1288373970 --700\n"
    . "\n"
    . "Import with legacy timestamp offsets\n";

$whitespaceSignatureBody = "tree 0123456789abcdef0123456789abcdef01234567\n"
    . "author WordPress Importer <importer@example.test> 1710000000 -0230\n"
    . "committer WordPress Deploy Bot <deploy@example.test> 1710003600 +0000\n"
    . "gpgsig -----BEGIN PGP SIGNATURE-----\n"
    . " \n"
    . " c2lnbmVkLXdoaXRlc3BhY2Utd29yZHByZXNz\n"
    . " -----END PGP SIGNATURE-----\n"
    . " \n"
    . "\n"
    . "Whitespace signed WordPress import\n";

$multiGpgsigBody = "tree 0123456789abcdef0123456789abcdef01234567\n"
    . "author Junio C Hamano <gitster@pobox.com> 1319256362 -0700\n"
    . "committer Junio C Hamano <gitster@pobox.com> 1319259176 -0700\n"
    . "gpgsig -----BEGIN PGP SIGNATURE-----\n"
    . "gpgsig Version: GnuPG v1.4.10 (GNU/Linux)\n"
    . "gpgsig \n"
    . "gpgsig c2lnbmVkLW9sZC1naXQ=\n"
    . "gpgsig -----END PGP SIGNATURE-----\n"
    . "\n"
    . "pretty: %G[?GS] placeholders\n";

$rawGpgsigBody = "tree 0123456789abcdef0123456789abcdef01234567\n"
    . "author WordPress Importer <importer@example.test> 1710000000 -0230\n"
    . "committer WordPress Deploy Bot <deploy@example.test> 1710003600 +0000\n"
    . "gpgsig -----BEGIN PGP SIGNATURE-----\n"
    . " c3RyZWFtaW5nLXdvcmRwcmVzcy1wcm92ZW5hbmNl\n"
    . " -----END PGP SIGNATURE-----\n"
    . "partial-import-tail-without-final-newline";

$standaloneTrailerBody = "Reviewed-by: Migration Reviewer <reviewer@example.test>\n"
    . " dry-run approved\n"
    . "(cherry picked from commit 0123456789abcdef0123456789abcdef01234567)\n";

return [
    'commitBody' => $body,
    'lateStandardHeaderCommitBody' => $lateStandardHeaderBody,
    'misorderedHeaderCommitBody' => $misorderedHeaderBody,
    'oddTimestampCommitBody' => $oddTimestampBody,
    'whitespaceSignatureCommitBody' => $whitespaceSignatureBody,
    'multiGpgsigCommitBody' => $multiGpgsigBody,
    'rawGpgsigCommitBody' => $rawGpgsigBody,
    'standaloneTrailerBody' => $standaloneTrailerBody,
    'expectedTree' => '0123456789abcdef0123456789abcdef01234567',
    'expectedAuthorName' => 'WordPress Importer',
    'expectedAuthorEmail' => 'importer@example.test',
    'expectedAuthorIdentity' => 'WordPress Importer <importer@example.test>',
    'expectedAuthorOffset' => -9000,
    'expectedCommitterIdentity' => 'WordPress Deploy Bot <deploy@example.test>',
    'expectedOddTimestampAuthorTime' => ['seconds' => 1312735823, 'offset' => 19080],
    'expectedOddTimestampCommitterTime' => ['seconds' => 1288373970, 'offset' => 0],
    'expectedOddTimestampCommitterRawTime' => '1288373970 --700',
    'expectedWhitespaceSignature' => "-----BEGIN PGP SIGNATURE-----\n\nc2lnbmVkLXdoaXRlc3BhY2Utd29yZHByZXNz\n-----END PGP SIGNATURE-----\n\n",
    'expectedWhitespaceSignedDataSha1' => 'de69a99d082679afe290ae6278e2df565f85fc40',
    'expectedWhitespaceTokenTypes' => ['tree', 'author', 'committer', 'extraHeader', 'message'],
    'expectedMultiGpgsigHeaderCount' => 5,
    'expectedMultiGpgsigFirstSignature' => '-----BEGIN PGP SIGNATURE-----',
    'expectedMultiGpgsigSignedDataSha1' => '95434dd7365f2260ed87ef549f84a8bdb9bf335a',
    'expectedRawGpgsigSignature' => "-----BEGIN PGP SIGNATURE-----\nc3RyZWFtaW5nLXdvcmRwcmVzcy1wcm92ZW5hbmNl\n-----END PGP SIGNATURE-----\n",
    'expectedRawGpgsigSignedDataSha1' => 'ba0fcfd4676620e752ff251e288020bb2cd53521',
    'expectedSummary' => 'Import WordPress export',
    'expectedWriterObjectIdGuard' => true,
    'expectedBodyWithoutTrailers' => 'Source: wp-content/uploads/export.wxr',
    'expectedSignedOffBy' => ['WordPress Importer <importer@example.test>'],
    'expectedCoAuthors' => ['Content Reviewer <reviewer@example.test>'],
    'expectedAckedBy' => ['Plugin Maintainer <plugin-maintainer@example.test>'],
    'expectedReviewedBy' => ['Deployment Reviewer <deploy-review@example.test>'],
    'expectedTestedBy' => ['QA Runner <qa@example.test> staged import dry-run'],
    'expectedStandaloneBodyWithoutTrailers' => '',
    'expectedStandaloneTrailerTokens' => [
        ['Reviewed-by', 'Migration Reviewer <reviewer@example.test> dry-run approved'],
    ],
    'expectedTokenTypes' => ['tree', 'parent', 'author', 'committer', 'encoding', 'extraHeader', 'extraHeader', 'message'],
    'expectedTokenExtraHeaders' => ['gpgsig', 'mergetag'],
    'expectedStorageSha1' => '0e4e540a8e8df5ad417aa01f23446ff1499375dc',
    'expectedObjectSha1' => 'b67d8527fb3f0ba50e607772acfd618c9a13cd89',
    'expectedSize' => 925,
    'expectedMixedHashGuard' => true,
    'expectedSignatureLineGuard' => true,
    'expectedSignatureHeaderPosition' => 0,
    'expectedMergeTagCount' => 1,
    'expectedMergeTagName' => 'wp-release-2026.05',
    'expectedMergeTagTarget' => '3333333333333333333333333333333333333333',
    'expectedMergeTagKind' => 'commit',
    'expectedMergeTagTagger' => 'WordPress Release Bot',
    'expectedMergeTagMessage' => "Release tag embedded for deployment provenance\n",
    'expectedLateParentExtraHeader' => '1111111111111111111111111111111111111111',
    'wordpressUse' => 'A WordPress import or deployment tool can inspect commit actors, raw timestamp offsets, encoding, signed payload bytes, merge-tag provenance, and attribution trailers without invoking git log.',
];
