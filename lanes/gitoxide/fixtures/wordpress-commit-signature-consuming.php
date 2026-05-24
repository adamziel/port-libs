<?php

declare(strict_types=1);

return [
    'authorSignatureBytes' => 'WordPress Importer <importer@example.test> 1710000000 -0230 audit=plugin-import',
    'reviewerSignatureBytes' => 'Migration Reviewer <reviewer@example.test> approved plugin import dry-run',
    'nextLineSignatureBytes' => "Release Bot <release@example.test>\ncommitter CI <ci@example.test> 1710003600 +0000",
    'malformedSignatureBytes' => 'WordPress Importer importer@example.test> 1710000000 -0230',
    'expectedAuthorIdentity' => 'WordPress Importer <importer@example.test>',
    'expectedAuthorTime' => '1710000000 -0230',
    'expectedAuthorRemainder' => 'audit=plugin-import',
    'expectedReviewerIdentity' => 'Migration Reviewer <reviewer@example.test>',
    'expectedReviewerTime' => '',
    'expectedReviewerRemainder' => 'approved plugin import dry-run',
    'expectedNextLineRemainder' => "\ncommitter CI <ci@example.test> 1710003600 +0000",
    'wordpressUse' => 'A WordPress import or deployment audit can split commit actor signatures from local provenance suffixes while preserving malformed timestamp bytes for caller diagnostics without invoking git log.',
];
