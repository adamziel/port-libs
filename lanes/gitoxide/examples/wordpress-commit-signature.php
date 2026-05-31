<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\Commit;
use PortLibs\Gitoxide\CommitMessage;

$fixture = require __DIR__ . '/../fixtures/wordpress-commit-signature.php';
$commit = Commit::parse($fixture['commitBody']);
$author = $commit->authorSignature();
$authorIdentity = $author->identity();
$committer = $commit->committerSignature();
$committerIdentity = $committer->identity();
$trailers = $commit->messageTrailers();
$signedData = $commit->signedDataForSignature();
$mergeTags = $commit->mergeTags();
$tokenResults = Commit::iterateTokens($fixture['commitBody']);
$storageBytes = $commit->storageBytes();
$lateStandardHeaderCommit = Commit::parse($fixture['lateStandardHeaderCommitBody']);
$oddTimestampCommit = Commit::parse($fixture['oddTimestampCommitBody']);
$oddTimestampAuthor = $oddTimestampCommit->authorSignature();
$oddTimestampCommitter = $oddTimestampCommit->committerSignature();
$whitespaceSignatureCommit = Commit::parse($fixture['whitespaceSignatureCommitBody']);
$whitespaceSignature = $whitespaceSignatureCommit->signatureForVerification();
$multiGpgsigCommit = Commit::parse($fixture['multiGpgsigCommitBody']);
$multiGpgsigSignature = $multiGpgsigCommit->signatureForVerification();
$rawGpgsigSignature = Commit::signatureForVerificationFromBytes($fixture['rawGpgsigCommitBody']);
$standaloneTrailerMessage = CommitMessage::fromBytes("Review imported plugin metadata\n\n" . $fixture['standaloneTrailerBody']);
$standaloneTrailerBody = $standaloneTrailerMessage->body ?? '';
$misorderedHeaderRejected = false;
try {
    Commit::parse($fixture['misorderedHeaderCommitBody']);
} catch (InvalidArgumentException) {
    $misorderedHeaderRejected = true;
}
$writerObjectIdGuard = false;
try {
    (new Commit(
        '0123456789abcdef0123456789abcdef0123456g',
        [],
        'WordPress Importer <importer@example.test> 1710000000 -0230',
        'WordPress Deploy Bot <deploy@example.test> 1710003600 +0000',
        "Invalid deploy tree\n",
        [],
    ))->storageBytes();
} catch (InvalidArgumentException) {
    $writerObjectIdGuard = true;
}
$mixedHashGuard = false;
try {
    (new Commit(
        '0123456789abcdef0123456789abcdef01234567',
        ['1111111111111111111111111111111111111111111111111111111111111111'],
        'WordPress Importer <importer@example.test> 1710000000 -0230',
        'WordPress Deploy Bot <deploy@example.test> 1710003600 +0000',
        "Mixed object-format deploy parent\n",
        [],
    ))->storageBytes();
} catch (InvalidArgumentException) {
    $mixedHashGuard = true;
}
$signatureLineGuard = false;
try {
    (new Commit(
        '0123456789abcdef0123456789abcdef01234567',
        [],
        "WordPress Importer <importer@example.test> 1710000000 -0230\nencoding UTF-16",
        'WordPress Deploy Bot <deploy@example.test> 1710003600 +0000',
        "Injected deploy signature\n",
        [],
    ))->storageBytes();
} catch (InvalidArgumentException) {
    $signatureLineGuard = true;
}

return [
    'tree' => $commit->tree,
    'author' => [
        'name' => $author->name,
        'email' => $author->email,
        'seconds' => $author->seconds(),
        'offsetSeconds' => $author->offsetSeconds(),
        'identity' => $authorIdentity->storageBytes(),
    ],
    'committer' => [
        'name' => $committer->name,
        'email' => $committer->email,
        'seconds' => $committer->seconds(),
        'offsetSeconds' => $committer->offsetSeconds(),
        'identity' => $committerIdentity->storageBytes(),
    ],
    'encoding' => $commit->encoding,
    'signatureHeader' => $commit->pgpSignature(),
    'signatureHeaderPosition' => $commit->extraHeaderPosition('gpgsig'),
    'signatureHeaderCount' => count($commit->extraHeaderValues('gpgsig')),
    'mergeTagCount' => count($commit->mergeTagHeaders()),
    'mergeTagNames' => array_map(static fn ($tag): string => $tag->name, $mergeTags),
    'mergeTags' => array_map(
        static fn ($tag): array => [
            'target' => $tag->target,
            'kind' => $tag->targetKind,
            'name' => $tag->name,
            'tagger' => $tag->taggerSignature()?->name,
            'message' => $tag->message,
        ],
        $mergeTags,
    ),
    'summary' => $commit->messageSummary(),
    'bodyWithoutTrailers' => $commit->messageBodyWithoutTrailers(),
    'trailers' => array_map(
        static fn ($trailer): array => ['token' => $trailer->token, 'value' => $trailer->value],
        $trailers,
    ),
    'signedOffBy' => array_map(static fn ($trailer): string => $trailer->value, $commit->signedOffByTrailers()),
    'coAuthoredBy' => array_map(static fn ($trailer): string => $trailer->value, $commit->coAuthoredByTrailers()),
    'ackedBy' => array_map(static fn ($trailer): string => $trailer->value, $commit->ackedByTrailers()),
    'reviewedBy' => array_map(static fn ($trailer): string => $trailer->value, $commit->reviewedByTrailers()),
    'testedBy' => array_map(static fn ($trailer): string => $trailer->value, $commit->testedByTrailers()),
    'attributions' => array_map(static fn ($trailer): string => "{$trailer->token}: {$trailer->value}", $commit->attributionTrailers()),
    'standaloneBodyWithoutTrailers' => CommitMessage::bodyWithoutTrailer($standaloneTrailerBody),
    'standaloneTrailerTokens' => array_map(
        static fn ($trailer): array => [$trailer->token, $trailer->value],
        CommitMessage::trailersFromBody($standaloneTrailerBody),
    ),
    'signedDataSha1' => $signedData === null ? null : sha1($signedData),
    'signedDataHasSignatureHeader' => $signedData !== null && str_contains($signedData, 'gpgsig '),
    'storageSha1' => sha1($storageBytes),
    'objectSha1' => $commit->object()->oid(),
    'size' => $commit->size(),
    'roundTripMatches' => Commit::parse($storageBytes)->storageBytes() === $storageBytes,
    'lateStandardHeaderParentCount' => count($lateStandardHeaderCommit->parents),
    'lateStandardHeaderEncoding' => $lateStandardHeaderCommit->encoding,
    'lateStandardHeaderParentExtra' => $lateStandardHeaderCommit->extraHeader('parent'),
    'lateStandardHeaderEncodingExtra' => $lateStandardHeaderCommit->extraHeader('encoding'),
    'misorderedHeaderRejected' => $misorderedHeaderRejected,
    'writerObjectIdGuard' => $writerObjectIdGuard,
    'mixedHashGuard' => $mixedHashGuard,
    'signatureLineGuard' => $signatureLineGuard,
    'oddTimestampAuthorTime' => $oddTimestampAuthor->time(),
    'oddTimestampCommitterTime' => $oddTimestampCommitter->time(),
    'oddTimestampCommitterRawTime' => $oddTimestampCommitter->time,
    'oddTimestampRoundTripMatches' => Commit::parse($oddTimestampCommit->storageBytes())->storageBytes() === $oddTimestampCommit->storageBytes(),
    'whitespaceSignature' => $whitespaceSignature['signature'] ?? null,
    'whitespaceSignedDataSha1' => $whitespaceSignature === null ? null : sha1($whitespaceSignature['signedData']),
    'whitespaceSignedDataHasSignatureHeader' => $whitespaceSignature !== null && str_contains($whitespaceSignature['signedData'], 'gpgsig '),
    'whitespaceTokenTypes' => array_map(
        static fn (array $result): string => $result['token']['type'] ?? 'error',
        Commit::iterateTokens($fixture['whitespaceSignatureCommitBody']),
    ),
    'multiGpgsigHeaderCount' => count($multiGpgsigCommit->extraHeaderValues('gpgsig')),
    'multiGpgsigFirstSignature' => $multiGpgsigCommit->pgpSignature(),
    'multiGpgsigSignedDataSha1' => $multiGpgsigSignature === null ? null : sha1($multiGpgsigSignature['signedData']),
    'multiGpgsigSignedDataKeepsLaterGpgsigLines' => $multiGpgsigSignature !== null
        && str_contains($multiGpgsigSignature['signedData'], 'gpgsig Version: GnuPG v1.4.10 (GNU/Linux)')
        && str_contains($multiGpgsigSignature['signedData'], 'gpgsig -----END PGP SIGNATURE-----'),
    'multiGpgsigRoundTripMatches' => $multiGpgsigCommit->storageBytes() === $fixture['multiGpgsigCommitBody'],
    'rawGpgsigSignature' => $rawGpgsigSignature['signature'] ?? null,
    'rawGpgsigSignedDataSha1' => $rawGpgsigSignature === null ? null : sha1($rawGpgsigSignature['signedData']),
    'rawGpgsigSignedDataKeepsTail' => $rawGpgsigSignature !== null
        && str_ends_with($rawGpgsigSignature['signedData'], 'partial-import-tail-without-final-newline'),
    'tokenTypes' => array_map(static fn (array $result): string => $result['token']['type'] ?? 'error', $tokenResults),
    'tokenExtraHeaderNames' => array_values(array_map(
        static fn (array $result): string => $result['token']['name'],
        array_filter($tokenResults, static fn (array $result): bool => ($result['token']['type'] ?? null) === 'extraHeader'),
    )),
];
