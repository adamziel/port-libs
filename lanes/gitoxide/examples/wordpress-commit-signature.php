<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\Commit;

$fixture = require __DIR__ . '/../fixtures/wordpress-commit-signature.php';
$commit = Commit::parse($fixture['commitBody']);
$author = $commit->authorSignature();
$committer = $commit->committerSignature();
$trailers = $commit->messageTrailers();
$signedData = $commit->signedDataForSignature();
$mergeTagNames = array_map(
    static function (string $header): ?string {
        return preg_match('/(?:^|\n)tag ([^\n]+)/', $header, $matches) === 1 ? $matches[1] : null;
    },
    $commit->mergeTagHeaders(),
);

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
    'signatureHeader' => $commit->pgpSignature(),
    'signatureHeaderPosition' => $commit->extraHeaderPosition('gpgsig'),
    'signatureHeaderCount' => count($commit->extraHeaderValues('gpgsig')),
    'mergeTagCount' => count($commit->mergeTagHeaders()),
    'mergeTagNames' => array_values(array_filter($mergeTagNames, static fn (?string $name): bool => $name !== null)),
    'summary' => $commit->messageSummary(),
    'bodyWithoutTrailers' => $commit->messageBodyWithoutTrailers(),
    'trailers' => array_map(
        static fn ($trailer): array => ['token' => $trailer->token, 'value' => $trailer->value],
        $trailers,
    ),
    'signedOffBy' => array_map(static fn ($trailer): string => $trailer->value, $commit->signedOffByTrailers()),
    'coAuthoredBy' => array_map(static fn ($trailer): string => $trailer->value, $commit->coAuthoredByTrailers()),
    'attributions' => array_map(static fn ($trailer): string => "{$trailer->token}: {$trailer->value}", $commit->attributionTrailers()),
    'signedDataSha1' => $signedData === null ? null : sha1($signedData),
    'signedDataHasSignatureHeader' => $signedData !== null && str_contains($signedData, 'gpgsig '),
];
