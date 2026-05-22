<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Commit;
use PortLibs\Gitoxide\CommitSignature;
use PortLibs\Gitoxide\GitObject;

return [
    'parses canonical git commit headers and message' => static function (TestRunner $t): void {
        $body = "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "parent 1111111111111111111111111111111111111111\n"
            . "parent 2222222222222222222222222222222222222222\n"
            . "author Ada <ada@example.test> 1700000000 +0000\n"
            . "committer CI <ci@example.test> 1700000001 +0000\n"
            . "\n"
            . "Import WordPress content\n\nWith block fixtures.\n";

        $commit = Commit::parse($body);
        $t->same('0123456789abcdef0123456789abcdef01234567', $commit->tree);
        $t->same(['1111111111111111111111111111111111111111', '2222222222222222222222222222222222222222'], $commit->parents);
        $t->same('Ada', $commit->authorSignature()->name);
        $t->same('ada@example.test', $commit->authorSignature()->email);
        $t->same(1700000000, $commit->authorSignature()->seconds());
        $t->same(0, $commit->authorSignature()->offsetSeconds());
        $t->same('CI <ci@example.test> 1700000001 +0000', $commit->committerSignature()->storageBytes());
        $t->same("Import WordPress content\n\nWith block fixtures.\n", $commit->message);
    },
    'commit parser rejects missing required headers' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => Commit::parse("tree 0123456789abcdef0123456789abcdef01234567\n\nmsg"));
    },
    'parses gitoxide actor signatures with lenient delimiter handling' => static function (TestRunner $t): void {
        $signature = CommitSignature::parse('Gregor Hartmann<gh <Gregor Hartmann<gh@openoffice.org>> 1282910542 +0200');

        $t->same('Gregor Hartmann', $signature->name);
        $t->same('gh <Gregor Hartmann<gh@openoffice.org', $signature->email);
        $t->same('1282910542 +0200', $signature->time);
        $t->same(1282910542, $signature->seconds());
        $t->same(7200, $signature->offsetSeconds());

        $t->throws(InvalidArgumentException::class, static fn () => CommitSignature::parse('Name <name@example.test> abc -1215'));
        $t->throws(InvalidArgumentException::class, static fn () => CommitSignature::parse('Name name@example.test> 1700000000 +0000'));
    },
    'parses sha256 commits encoding and multiline extra headers' => static function (TestRunner $t): void {
        $body = "tree 0123456789ABCDEF0123456789abcdef0123456789abcdef0123456789abcdef\n"
            . "parent 1111111111111111111111111111111111111111111111111111111111111111\n"
            . "parent 2222222222222222222222222222222222222222222222222222222222222222\n"
            . "author Ada Lovelace <ada@example.com> 1710000000 +0000\n"
            . "committer Grace Hopper <grace@example.com> 1710003600 -0230\n"
            . "encoding ISO-8859-1\n"
            . "gpgsig -----BEGIN SSH SIGNATURE-----\n"
            . " U1NIU0lHAAAAAQAAADMAAAALc3NoLWVkMjU1MTkAAAAgZXhhbXBsZS1zaGEyNTY=\n"
            . " -----END SSH SIGNATURE-----\n"
            . "\n"
            . "sha256 subject\n\nsha256 body\n";

        $commit = Commit::parse($body, 'sha256');

        $t->same('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', $commit->tree);
        $t->same([
            '1111111111111111111111111111111111111111111111111111111111111111',
            '2222222222222222222222222222222222222222222222222222222222222222',
        ], $commit->parents);
        $t->same('ISO-8859-1', $commit->encoding);
        $t->same('Grace Hopper', $commit->committerSignature()->name);
        $t->same(-9000, $commit->committerSignature()->offsetSeconds());
        $t->same(
            "-----BEGIN SSH SIGNATURE-----\nU1NIU0lHAAAAAQAAADMAAAALc3NoLWVkMjU1MTkAAAAgZXhhbXBsZS1zaGEyNTY=\n-----END SSH SIGNATURE-----",
            $commit->extraHeaders['gpgsig'][0],
        );
        $t->same("sha256 subject\n\nsha256 body\n", $commit->message);
    },
    'parses gitoxide commit message summaries body trailers and attribution filters' => static function (TestRunner $t): void {
        $body = "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author Ada <ada@example.test> 1700000000 +0000\n"
            . "committer CI <ci@example.test> 1700000001 +0000\n"
            . "\n"
            . "Import WordPress export \t\r\n from WXR\n\n"
            . "Normalize block markup before import.\n\n"
            . "Signed-off-by: Alice <alice@example.test>\n"
            . "Co-authored-by : Bob <bob@example.test>\n"
            . " continued metadata\n"
            . "Reviewed-by: Carol <carol@example.test>\n";

        $commit = Commit::parse($body);
        $trailers = $commit->messageTrailers();

        $t->same('Import WordPress export  from WXR', $commit->messageSummary());
        $t->same("Import WordPress export \t\r\n from WXR", $commit->messageTitle());
        $t->same("Normalize block markup before import.", $commit->messageBodyWithoutTrailers());
        $t->same(3, count($trailers));
        $t->same('Signed-off-by', $trailers[0]->token);
        $t->same('Alice <alice@example.test>', $trailers[0]->value);
        $t->same('Co-authored-by', $trailers[1]->token);
        $t->same('Bob <bob@example.test> continued metadata', $trailers[1]->value);
        $t->same(1, count($commit->signedOffByTrailers()));
        $t->same(1, count($commit->coAuthoredByTrailers()));
        $t->same(2, count($commit->authorTrailers()));
        $t->same(3, count($commit->attributionTrailers()));
    },
    'commit trailer parser follows gitoxide footer block heuristics' => static function (TestRunner $t): void {
        $generic = Commit::parse("tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author A <a@example.test> 1700000000 +0000\n"
            . "committer C <c@example.test> 1700000000 +0000\n"
            . "\n"
            . "Subject\n\nNote: this is body text\nnot a trailer\n");
        $t->same([], $generic->messageTrailers());
        $t->same("Note: this is body text\nnot a trailer\n", $generic->messageBodyWithoutTrailers());

        $recognized = Commit::parse("tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author A <a@example.test> 1700000000 +0000\n"
            . "committer C <c@example.test> 1700000000 +0000\n"
            . "\n"
            . "Subject\n\nSigned-off-by: Alice <alice@example.test>\n"
            . "not a trailer 1\nnot a trailer 2\nnot a trailer 3");
        $t->same('', $recognized->messageBodyWithoutTrailers());
        $t->same('Alice <alice@example.test>', $recognized->messageTrailers()[0]->value);

        $belowThreshold = Commit::parse("tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author A <a@example.test> 1700000000 +0000\n"
            . "committer C <c@example.test> 1700000000 +0000\n"
            . "\n"
            . "Subject\n\nSigned-off-by: Alice <alice@example.test>\n"
            . "not a trailer 1\nnot a trailer 2\nnot a trailer 3\nnot a trailer 4");
        $t->same([], $belowThreshold->messageTrailers());
    },
    'extracts commit pgp signature and signed data without signature header bytes' => static function (TestRunner $t): void {
        $body = "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author A <a@example.test> 1700000000 +0000\n"
            . "committer C <c@example.test> 1700000000 +0000\n"
            . "gpgsig -----BEGIN PGP SIGNATURE-----\n"
            . " \n"
            . " cGF5bG9hZA==\n"
            . " -----END PGP SIGNATURE-----\n"
            . "\n"
            . "Signed commit\n";

        $commit = Commit::parse($body);

        $t->same("-----BEGIN PGP SIGNATURE-----\n\ncGF5bG9hZA==\n-----END PGP SIGNATURE-----", $commit->pgpSignature());
        $t->same("tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author A <a@example.test> 1700000000 +0000\n"
            . "committer C <c@example.test> 1700000000 +0000\n"
            . "\n"
            . "Signed commit\n", $commit->signedDataForSignature());

        $unsigned = Commit::parse("tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author A <a@example.test> 1700000000 +0000\n"
            . "committer C <c@example.test> 1700000000 +0000\n\nUnsigned\n");
        $t->same(null, $unsigned->pgpSignature());
        $t->same(null, $unsigned->signedDataForSignature());
    },
    'commit body can be read from a native git object' => static function (TestRunner $t): void {
        $body = "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author A <a@example.test> 1700000000 +0000\n"
            . "committer C <c@example.test> 1700000000 +0000\n"
            . "\n"
            . "Post import\n";
        $object = GitObject::fromStorageBytes("commit " . strlen($body) . "\0" . $body);
        $commit = Commit::parse($object->body);
        $t->same('Post import' . "\n", $commit->message);
    },
    'wordpress fixture exposes import commit actors encoding and signature header' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-commit-signature.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-commit-signature.php';

        $t->same($fixture['expectedTree'], $summary['tree']);
        $t->same($fixture['expectedAuthorName'], $summary['author']['name']);
        $t->same($fixture['expectedAuthorEmail'], $summary['author']['email']);
        $t->same($fixture['expectedAuthorOffset'], $summary['author']['offsetSeconds']);
        $t->same('UTF-8', $summary['encoding']);
        $t->contains('BEGIN SSH SIGNATURE', $summary['signatureHeader']);
        $t->same($fixture['expectedSummary'], $summary['summary']);
        $t->same($fixture['expectedBodyWithoutTrailers'], $summary['bodyWithoutTrailers']);
        $t->same($fixture['expectedSignedOffBy'], $summary['signedOffBy']);
        $t->same($fixture['expectedCoAuthors'], $summary['coAuthoredBy']);
        $t->same(false, $summary['signedDataHasSignatureHeader']);
    },
];
