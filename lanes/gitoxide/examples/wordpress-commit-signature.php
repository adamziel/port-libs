<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\Commit;

$fixture = require __DIR__ . '/../fixtures/wordpress-commit-signature.php';
$commit = Commit::parse($fixture['commitBody']);
$author = $commit->authorSignature();
$committer = $commit->committerSignature();

return [
    'tree' => $commit->tree,
    'author' => [
        'name' => $author->name,
        'email' => $author->email,
        'seconds' => $author->seconds(),
        'offsetSeconds' => $author->offsetSeconds(),
    ],
    'committer' => [
        'name' => $committer->name,
        'email' => $committer->email,
        'seconds' => $committer->seconds(),
        'offsetSeconds' => $committer->offsetSeconds(),
    ],
    'encoding' => $commit->encoding,
    'signatureHeader' => $commit->extraHeaders['gpgsig'][0] ?? null,
];
