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

return [
    'commitBody' => $body,
    'lateStandardHeaderCommitBody' => $lateStandardHeaderBody,
    'misorderedHeaderCommitBody' => $misorderedHeaderBody,
    'oddTimestampCommitBody' => $oddTimestampBody,
    'expectedTree' => '0123456789abcdef0123456789abcdef01234567',
    'expectedAuthorName' => 'WordPress Importer',
    'expectedAuthorEmail' => 'importer@example.test',
    'expectedAuthorOffset' => -9000,
    'expectedOddTimestampAuthorTime' => ['seconds' => 1312735823, 'offset' => 19080],
    'expectedOddTimestampCommitterTime' => ['seconds' => 1288373970, 'offset' => 0],
    'expectedOddTimestampCommitterRawTime' => '1288373970 --700',
    'expectedSummary' => 'Import WordPress export',
    'expectedBodyWithoutTrailers' => 'Source: wp-content/uploads/export.wxr',
    'expectedSignedOffBy' => ['WordPress Importer <importer@example.test>'],
    'expectedCoAuthors' => ['Content Reviewer <reviewer@example.test>'],
    'expectedAckedBy' => ['Plugin Maintainer <plugin-maintainer@example.test>'],
    'expectedReviewedBy' => ['Deployment Reviewer <deploy-review@example.test>'],
    'expectedTestedBy' => ['QA Runner <qa@example.test> staged import dry-run'],
    'expectedTokenTypes' => ['tree', 'parent', 'author', 'committer', 'encoding', 'extraHeader', 'extraHeader', 'message'],
    'expectedTokenExtraHeaders' => ['gpgsig', 'mergetag'],
    'expectedStorageSha1' => '0e4e540a8e8df5ad417aa01f23446ff1499375dc',
    'expectedObjectSha1' => 'b67d8527fb3f0ba50e607772acfd618c9a13cd89',
    'expectedSize' => 925,
    'expectedSignatureHeaderPosition' => 0,
    'expectedMergeTagCount' => 1,
    'expectedMergeTagName' => 'wp-release-2026.05',
    'expectedMergeTagTarget' => '3333333333333333333333333333333333333333',
    'expectedMergeTagKind' => 'commit',
    'expectedMergeTagTagger' => 'WordPress Release Bot',
    'expectedMergeTagMessage' => 'Release tag embedded for deployment provenance',
    'expectedLateParentExtraHeader' => '1111111111111111111111111111111111111111',
    'wordpressUse' => 'A WordPress import or deployment tool can inspect commit actors, raw timestamp offsets, encoding, signed payload bytes, merge-tag provenance, and attribution trailers without invoking git log.',
];
