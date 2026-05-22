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
    . "\n"
    . "Import WordPress export\n\n"
    . "Source: wp-content/uploads/export.wxr\n";

return [
    'commitBody' => $body,
    'expectedTree' => '0123456789abcdef0123456789abcdef01234567',
    'expectedAuthorName' => 'WordPress Importer',
    'expectedAuthorEmail' => 'importer@example.test',
    'expectedAuthorOffset' => -9000,
    'wordpressUse' => 'A WordPress import or deployment tool can inspect commit actors, encoding, and signature headers without invoking git log.',
];
