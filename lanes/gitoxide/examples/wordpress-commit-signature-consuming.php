<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\CommitSignature;

$fixture = require __DIR__ . '/../fixtures/wordpress-commit-signature-consuming.php';

$author = CommitSignature::parseConsuming($fixture['authorSignatureBytes']);
$reviewer = CommitSignature::parseConsuming($fixture['reviewerSignatureBytes']);
$nextLine = CommitSignature::parseConsuming($fixture['nextLineSignatureBytes']);

$malformedRejected = false;
try {
    CommitSignature::parseConsuming($fixture['malformedSignatureBytes']);
} catch (InvalidArgumentException) {
    $malformedRejected = true;
}

return [
    'author' => [
        'identity' => $author['signature']->identity()->storageBytes(),
        'time' => trim($author['signature']->time),
        'lenientTime' => $author['signature']->time(),
        'remainder' => $author['rest'],
    ],
    'reviewer' => [
        'identity' => $reviewer['signature']->identity()->storageBytes(),
        'time' => trim($reviewer['signature']->time),
        'remainder' => $reviewer['rest'],
    ],
    'nextLineRemainder' => $nextLine['rest'],
    'malformedRejected' => $malformedRejected,
    'wordpressUse' => $fixture['wordpressUse'],
];
