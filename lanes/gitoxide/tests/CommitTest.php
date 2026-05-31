<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Commit;
use PortLibs\Gitoxide\CommitMessage;
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
    'commit parser requires the gitoxide header message separator' => static function (TestRunner $t): void {
        $body = "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author Ada <ada@example.test> 1700000000 +0000\n"
            . "committer CI <ci@example.test> 1700000001 +0000\n";

        $t->throws(InvalidArgumentException::class, static fn () => Commit::parse($body));
        $t->same([], Commit::iterateTokens(''));
    },
    'commit parser follows gitoxide strict header order' => static function (TestRunner $t): void {
        $lateStandardHeader = "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author Ada <ada@example.test> 1700000000 +0000\n"
            . "committer CI <ci@example.test> 1700000001 +0000\n"
            . "parent 1111111111111111111111111111111111111111\n"
            . "encoding UTF-8\n"
            . "\n"
            . "Late standard headers\n";

        $commit = Commit::parse($lateStandardHeader);

        $t->same([], $commit->parents);
        $t->same(null, $commit->encoding);
        $t->same('1111111111111111111111111111111111111111', $commit->extraHeader('parent'));
        $t->same('UTF-8', $commit->extraHeader('encoding'));
        $t->same(['parent', 'encoding'], array_map(static fn (array $header): string => $header['name'], $commit->allExtraHeaders()));

        $committerBeforeAuthor = "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "committer CI <ci@example.test> 1700000001 +0000\n"
            . "author Ada <ada@example.test> 1700000000 +0000\n"
            . "\n"
            . "Out of order\n";
        $t->throws(InvalidArgumentException::class, static fn () => Commit::parse($committerBeforeAuthor));

        $encodingBeforeCommitter = "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author Ada <ada@example.test> 1700000000 +0000\n"
            . "encoding UTF-8\n"
            . "committer CI <ci@example.test> 1700000001 +0000\n"
            . "\n"
            . "Out of order\n";
        $t->throws(InvalidArgumentException::class, static fn () => Commit::parse($encodingBeforeCommitter));
    },
    'parses gitoxide actor signatures with lenient delimiter handling' => static function (TestRunner $t): void {
        $signature = CommitSignature::parse('Gregor Hartmann<gh <Gregor Hartmann<gh@openoffice.org>> 1282910542 +0200');

        $t->same('Gregor Hartmann', $signature->name);
        $t->same('gh <Gregor Hartmann<gh@openoffice.org', $signature->email);
        $t->same('1282910542 +0200', $signature->time);
        $t->same(1282910542, $signature->seconds());
        $t->same(['seconds' => 1282910542, 'offset' => 7200], $signature->time());
        $t->same(7200, $signature->offsetSeconds());

        $withOffsetSeconds = CommitSignature::parse('first last <name@example.com> 1312735823 +051800');
        $t->same('1312735823 +051800', $withOffsetSeconds->time);
        $t->same(['seconds' => 1312735823, 'offset' => 19080], $withOffsetSeconds->time());
        $t->same(19080, $withOffsetSeconds->offsetSeconds());
        $t->same('first last <name@example.com> 1312735823 +051800', $withOffsetSeconds->storageBytes());

        $doubleDashOffset = CommitSignature::parse('name <name@example.com> 1288373970 --700');
        $t->same(1288373970, $doubleDashOffset->seconds());
        $t->same(['seconds' => 1288373970, 'offset' => 0], $doubleDashOffset->time());
        $t->same(0, $doubleDashOffset->offsetSeconds());
        $t->same('name <name@example.com> 1288373970 --700', $doubleDashOffset->storageBytes());

        $missingTimestamp = CommitSignature::parse('first last <name@example.com>');
        $t->same('', $missingTimestamp->time);
        $t->same(0, $missingTimestamp->seconds());
        $t->same(null, $missingTimestamp->time());

        $t->throws(InvalidArgumentException::class, static fn () => (new CommitSignature('invalid < middlename', 'ok', '0 +0000'))->storageBytes());
        $t->throws(InvalidArgumentException::class, static fn () => (new CommitSignature('ok', 'server>.example.com', '0 +0000'))->storageBytes());
        $t->throws(InvalidArgumentException::class, static fn () => (new CommitSignature("hello\nnewline", 'name@example.com', '0 +0000'))->storageBytes());
        $t->throws(InvalidArgumentException::class, static fn () => CommitSignature::parse('Name name@example.test> 1700000000 +0000'));

        $invalidTime = CommitSignature::parseConsuming('Name <name@example.test> abc -1215');
        $t->same('Name', $invalidTime['signature']->name);
        $t->same('name@example.test', $invalidTime['signature']->email);
        $t->same('', $invalidTime['signature']->time);
        $t->same('abc -1215', $invalidTime['rest']);
    },
    'wordpress signature consuming example splits actor time from local suffixes' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-commit-signature-consuming.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-commit-signature-consuming.php';

        $t->same($fixture['expectedAuthorIdentity'], $summary['author']['identity']);
        $t->same($fixture['expectedAuthorTime'], $summary['author']['time']);
        $t->same(['seconds' => 1710000000, 'offset' => -9000], $summary['author']['lenientTime']);
        $t->same($fixture['expectedAuthorRemainder'], $summary['author']['remainder']);
        $t->same($fixture['expectedReviewerIdentity'], $summary['reviewer']['identity']);
        $t->same($fixture['expectedReviewerTime'], $summary['reviewer']['time']);
        $t->same($fixture['expectedReviewerRemainder'], $summary['reviewer']['remainder']);
        $t->same($fixture['expectedNextLineRemainder'], $summary['nextLineRemainder']);
        $t->same(true, $summary['malformedRejected']);
        $t->contains('WordPress import', $summary['wordpressUse']);
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
    'commit token iterator follows gitoxide CommitRefIter order' => static function (TestRunner $t): void {
        $body = "tree 0123456789ABCDEF0123456789abcdef0123456789abcdef0123456789abcdef\n"
            . "parent 1111111111111111111111111111111111111111111111111111111111111111\n"
            . "parent 2222222222222222222222222222222222222222222222222222222222222222\n"
            . "author Ada Lovelace <ada@example.com> 1710000000 +0000\n"
            . "committer Grace Hopper <grace@example.com> 1710003600 -0230\n"
            . "encoding ISO-8859-1\n"
            . "gpgsig -----BEGIN SSH SIGNATURE-----\n"
            . " U1NIU0lHAAAAAQAAADMAAAALc3NoLWVkMjU1MTkAAAAgZXhhbXBsZS1zaGEyNTY=\n"
            . " -----END SSH SIGNATURE-----\n"
            . "mergetag object 3333333333333333333333333333333333333333333333333333333333333333\n"
            . " type commit\n"
            . " tag nested-sha256\n"
            . " tagger Release Bot <release@example.com> 1710007200 +0530\n"
            . " \n"
            . " nested release notes\n"
            . "\n"
            . "sha256 subject\n\nsha256 body\n";

        $tokens = Commit::iterateTokens($body, 'sha256');

        $t->same([
            'tree',
            'parent',
            'parent',
            'author',
            'committer',
            'encoding',
            'extraHeader',
            'extraHeader',
            'message',
        ], array_map(static fn (array $result): ?string => $result['token']['type'] ?? null, $tokens));
        $t->same('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', $tokens[0]['token']['id']);
        $t->same('0123456789ABCDEF0123456789abcdef0123456789abcdef0123456789abcdef', $tokens[0]['token']['rawId']);
        $t->same('Ada Lovelace <ada@example.com> 1710000000 +0000', $tokens[3]['token']['signature']);
        $t->same('ISO-8859-1', $tokens[5]['token']['encoding']);
        $t->same('gpgsig', $tokens[6]['token']['name']);
        $t->same(
            "-----BEGIN SSH SIGNATURE-----\nU1NIU0lHAAAAAQAAADMAAAALc3NoLWVkMjU1MTkAAAAgZXhhbXBsZS1zaGEyNTY=\n-----END SSH SIGNATURE-----",
            $tokens[6]['token']['value'],
        );
        $t->same('mergetag', $tokens[7]['token']['name']);
        $t->contains('tag nested-sha256', $tokens[7]['token']['value']);
        $t->same("sha256 subject\n\nsha256 body\n", $tokens[8]['token']['message']);
    },
    'commit token iterator returns prior tokens before decode errors' => static function (TestRunner $t): void {
        $body = "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author Ada <ada@example.test> 1700000000 +0000\n"
            . "committer Broken <broken@example.test> 1700000001 +0000";

        $tokens = Commit::iterateTokens($body);

        $t->same(['tree', 'author', null], array_map(static fn (array $result): ?string => $result['token']['type'] ?? null, $tokens));
        $t->same(false, $tokens[2]['ok']);
        $t->contains('committer header is not newline terminated', $tokens[2]['error'] ?? '');
    },
    'commit writer follows gitoxide WriteTo storage size and object semantics' => static function (TestRunner $t): void {
        $body = "tree 0123456789ABCDEF0123456789abcdef0123456789abcdef0123456789abcdef\n"
            . "parent 1111111111111111111111111111111111111111111111111111111111111111\n"
            . "author Ada Lovelace <ada@example.com> 1710000000 +0000\n"
            . "committer Grace Hopper <grace@example.com> 1710003600 -0230\n"
            . "encoding ISO-8859-1\n"
            . "gpgsig -----BEGIN SSH SIGNATURE-----\n"
            . " U1NIU0lHAAAAAQAAADMAAAALc3NoLWVkMjU1MTkAAAAgZXhhbXBsZS1zaGEyNTY=\n"
            . " -----END SSH SIGNATURE-----\n"
            . "reviewed-by Release Reviewer <reviewer@example.test>\n"
            . "\n"
            . "sha256 subject\n\nsha256 body\n";
        $commit = Commit::parse($body, 'sha256');
        $expected = "tree 0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef\n"
            . "parent 1111111111111111111111111111111111111111111111111111111111111111\n"
            . "author Ada Lovelace <ada@example.com> 1710000000 +0000\n"
            . "committer Grace Hopper <grace@example.com> 1710003600 -0230\n"
            . "encoding ISO-8859-1\n"
            . "gpgsig -----BEGIN SSH SIGNATURE-----\n"
            . " U1NIU0lHAAAAAQAAADMAAAALc3NoLWVkMjU1MTkAAAAgZXhhbXBsZS1zaGEyNTY=\n"
            . " -----END SSH SIGNATURE-----\n"
            . "reviewed-by Release Reviewer <reviewer@example.test>\n"
            . "\n"
            . "sha256 subject\n\nsha256 body\n";

        $t->same($expected, $commit->storageBytes());
        $t->same(strlen($expected), $commit->size());
        $object = $commit->object();
        $t->same('commit', $object->type);
        $t->same($expected, $object->body);
        $t->same(hash('sha1', 'commit ' . strlen($expected) . "\0" . $expected), $object->oid());

        $emptyEncoding = new Commit(
            '0123456789abcdef0123456789abcdef01234567',
            [],
            'Ada <ada@example.test> 1700000000 +0000',
            'CI <ci@example.test> 1700000001 +0000',
            'message',
            [],
            '',
        );
        $t->throws(InvalidArgumentException::class, static fn () => $emptyEncoding->storageBytes());

        $badTree = new Commit(
            '0123456789abcdef0123456789abcdef0123456g',
            [],
            'Ada <ada@example.test> 1700000000 +0000',
            'CI <ci@example.test> 1700000001 +0000',
            'message',
            [],
        );
        $t->throws(InvalidArgumentException::class, static fn () => $badTree->storageBytes());

        $badParent = new Commit(
            '0123456789abcdef0123456789abcdef01234567',
            ['111111111111111111111111111111111111111'],
            'Ada <ada@example.test> 1700000000 +0000',
            'CI <ci@example.test> 1700000001 +0000',
            'message',
            [],
        );
        $t->throws(InvalidArgumentException::class, static fn () => $badParent->object());

        $mixedHashParents = new Commit(
            '0123456789abcdef0123456789abcdef01234567',
            ['1111111111111111111111111111111111111111111111111111111111111111'],
            'Ada <ada@example.test> 1700000000 +0000',
            'CI <ci@example.test> 1700000001 +0000',
            'message',
            [],
        );
        $t->throws(InvalidArgumentException::class, static fn () => $mixedHashParents->storageBytes());

        $authorHeaderInjection = new Commit(
            '0123456789abcdef0123456789abcdef01234567',
            [],
            "Ada <ada@example.test> 1700000000 +0000\nencoding UTF-16",
            'CI <ci@example.test> 1700000001 +0000',
            'message',
            [],
        );
        $t->throws(InvalidArgumentException::class, static fn () => $authorHeaderInjection->storageBytes());

        $committerSuffixInjection = new Commit(
            '0123456789abcdef0123456789abcdef01234567',
            [],
            'Ada <ada@example.test> 1700000000 +0000',
            'CI <ci@example.test> 1700000001 +0000 reviewed-by Bot <bot@example.test>',
            'message',
            [],
        );
        $t->throws(InvalidArgumentException::class, static fn () => $committerSuffixInjection->storageBytes());
    },
    'extra header lookup follows gitoxide first all and position semantics' => static function (TestRunner $t): void {
        $body = "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "author Release Bot <release@example.test> 1710000000 +0000\n"
            . "committer CI <ci@example.test> 1710003600 +0000\n"
            . "gpgsig -----BEGIN PGP SIGNATURE-----\n"
            . "mergetag object 3333333333333333333333333333333333333333\n"
            . " type commit\n"
            . " tag wp-release-2026.05\n"
            . " tagger Release Bot <release@example.test> 1710007200 +0000\n"
            . " \n"
            . " WordPress release tag provenance\n"
            . "gpgsig iHUEABYIAB0WIQSuZwcGWSQItmusNgR5URpSUCnw\n"
            . "gpgsig -----END PGP SIGNATURE-----\n"
            . "\n"
            . "Release WordPress content\n";

        $commit = Commit::parse($body);

        $t->same('-----BEGIN PGP SIGNATURE-----', $commit->extraHeader('gpgsig'));
        $t->same('-----BEGIN PGP SIGNATURE-----', $commit->pgpSignature());
        $t->same(0, $commit->extraHeaderPosition('gpgsig'));
        $t->same(1, $commit->extraHeaderPosition('mergetag'));
        $t->same(null, $commit->extraHeaderPosition('unknown'));
        $t->same([
            '-----BEGIN PGP SIGNATURE-----',
            'iHUEABYIAB0WIQSuZwcGWSQItmusNgR5URpSUCnw',
            '-----END PGP SIGNATURE-----',
        ], $commit->extraHeaderValues('gpgsig'));
        $t->same(1, count($commit->mergeTagHeaders()));
        $t->contains("tag wp-release-2026.05", $commit->mergeTagHeaders()[0]);
        $t->same([
            ['name' => 'gpgsig', 'value' => '-----BEGIN PGP SIGNATURE-----'],
            [
                'name' => 'mergetag',
                'value' => "object 3333333333333333333333333333333333333333\n"
                    . "type commit\n"
                    . "tag wp-release-2026.05\n"
                    . "tagger Release Bot <release@example.test> 1710007200 +0000\n"
                    . "\n"
                    . "WordPress release tag provenance",
            ],
            ['name' => 'gpgsig', 'value' => 'iHUEABYIAB0WIQSuZwcGWSQItmusNgR5URpSUCnw'],
            ['name' => 'gpgsig', 'value' => '-----END PGP SIGNATURE-----'],
        ], $commit->allExtraHeaders());

        $mergeTags = $commit->mergeTags();
        $t->same('3333333333333333333333333333333333333333', $mergeTags[0]->target);
        $t->same('commit', $mergeTags[0]->targetKind);
        $t->same('wp-release-2026.05', $mergeTags[0]->name);
        $t->same('Release Bot', $mergeTags[0]->taggerSignature()?->name);
        $t->same('WordPress release tag provenance', $mergeTags[0]->message);
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
            . "Acked-by: Dana <dana@example.test>\n"
            . "Reviewed-by: Carol <carol@example.test>\n"
            . "Tested-by: Eli <eli@example.test>\n"
            . " continued lab\n";

        $commit = Commit::parse($body);
        $trailers = $commit->messageTrailers();

        $t->same('Import WordPress export  from WXR', $commit->messageSummary());
        $t->same("Import WordPress export \t\r\n from WXR", $commit->messageTitle());
        $t->same("Normalize block markup before import.", $commit->messageBodyWithoutTrailers());
        $t->same(5, count($trailers));
        $t->same('Signed-off-by', $trailers[0]->token);
        $t->same('Alice <alice@example.test>', $trailers[0]->value);
        $t->same('Co-authored-by', $trailers[1]->token);
        $t->same('Bob <bob@example.test> continued metadata', $trailers[1]->value);
        $t->same('Acked-by', $trailers[2]->token);
        $t->same('Dana <dana@example.test>', $trailers[2]->value);
        $t->same('Reviewed-by', $trailers[3]->token);
        $t->same('Carol <carol@example.test>', $trailers[3]->value);
        $t->same('Tested-by', $trailers[4]->token);
        $t->same('Eli <eli@example.test> continued lab', $trailers[4]->value);
        $t->same(1, count($commit->signedOffByTrailers()));
        $t->same(1, count($commit->coAuthoredByTrailers()));
        $t->same(['Dana <dana@example.test>'], array_map(static fn ($trailer): string => $trailer->value, $commit->ackedByTrailers()));
        $t->same(['Carol <carol@example.test>'], array_map(static fn ($trailer): string => $trailer->value, $commit->reviewedByTrailers()));
        $t->same(['Eli <eli@example.test> continued lab'], array_map(static fn ($trailer): string => $trailer->value, $commit->testedByTrailers()));
        $t->same(2, count($commit->authorTrailers()));
        $t->same(5, count($commit->attributionTrailers()));
    },
    'commit message parsing uses gitoxide ascii byte classes' => static function (TestRunner $t): void {
        $t->same("\0Import WordPress export\0", CommitMessage::summaryOf("\0Import WordPress export\0"));
        $t->same('Import WordPress export', CommitMessage::summaryOf("\vImport WordPress export\v"));
        $t->same('Import WordPress export', CommitMessage::summaryOf(" \t\r\n\fImport WordPress export\f\r\n\t "));
        $t->same("Import\v  WordPress export", CommitMessage::summaryOf("Import\v\n WordPress export"));

        $message = new CommitMessage('Subject', "Signed-off-by: Alice <alice@example.test>\n\vnot a continuation");
        $trailers = $message->trailers();

        $t->same('', $message->bodyWithoutTrailers());
        $t->same(1, count($trailers));
        $t->same('Alice <alice@example.test> not a continuation', $trailers[0]->value);

        $valueBytes = new CommitMessage('Subject', "Tested-by: \0QA Runner\0\v");
        $t->same("\0QA Runner\0", $valueBytes->trailers()[0]->value);

        $verticalBlankSeparator = new CommitMessage('Subject', "Body paragraph\n\v\nSigned-off-by: Alice <alice@example.test>");
        $t->same('Body paragraph', $verticalBlankSeparator->bodyWithoutTrailers());
        $t->same('Alice <alice@example.test>', $verticalBlankSeparator->signedOffByTrailers()[0]->value);

        $invalidToken = new CommitMessage('Subject', "🤗: 🎉");
        $t->same("🤗: 🎉", $invalidToken->bodyWithoutTrailers());
        $t->same([], $invalidToken->trailers());
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
    'commit message body helpers match focused gix-object message cases' => static function (TestRunner $t): void {
        $titleOnly = CommitMessage::fromBytes("hello there\r\n");
        $t->same("hello there\r\n", $titleOnly->title);
        $t->same(null, $titleOnly->body);
        $t->same('hello there', $titleOnly->summary());

        $inconsistent = CommitMessage::fromBytes("hello\n\r\nthere");
        $t->same('hello', $inconsistent->title);
        $t->same('there', $inconsistent->body);

        $emptyBody = CommitMessage::fromBytes("hello\r\n\r\n");
        $t->same('hello', $emptyBody->title);
        $t->same(null, $emptyBody->body);

        $cases = [
            [
                "Signed-off-by: Alice <alice@example.com>\nCo-authored-by: Bob <bob@example.com>",
                '',
                [
                    ['Signed-off-by', 'Alice <alice@example.com>'],
                    ['Co-authored-by', 'Bob <bob@example.com>'],
                ],
            ],
            [
                "body paragraph\n\nAcked-by: Alice\n continuation line",
                'body paragraph',
                [['Acked-by', 'Alice continuation line']],
            ],
            [
                "some body text\ntoken: value",
                "some body text\ntoken: value",
                [],
            ],
            [
                "some body text\nSigned-off-by: Alice <alice@example.com>",
                '',
                [['Signed-off-by', 'Alice <alice@example.com>']],
            ],
            [
                "Signed-off-by: Alice <alice@example.com>\nnot a trailer 1\nnot a trailer 2\nnot a trailer 3",
                '',
                [['Signed-off-by', 'Alice <alice@example.com>']],
            ],
            [
                "Signed-off-by: Alice <alice@example.com>\nnot a trailer 1\nnot a trailer 2\nnot a trailer 3\nnot a trailer 4",
                "Signed-off-by: Alice <alice@example.com>\nnot a trailer 1\nnot a trailer 2\nnot a trailer 3\nnot a trailer 4",
                [],
            ],
            [
                "Acked-by: Alice\r\n continuation line\r\n",
                '',
                [['Acked-by', 'Alice continuation line']],
            ],
            [
                "a: b\nnot a trailer\nc: d",
                "a: b\nnot a trailer\nc: d",
                [],
            ],
            [
                "not a trailer\nSigned-off-by: Alice <alice@example.com>\nanother note\nSigned-off-by: Bob <bob@example.com>",
                '',
                [
                    ['Signed-off-by', 'Alice <alice@example.com>'],
                    ['Signed-off-by', 'Bob <bob@example.com>'],
                ],
            ],
            [
                "(cherry picked from commit 0123456789abcdef0123456789abcdef01234567)",
                '',
                [],
            ],
        ];

        foreach ($cases as [$body, $expectedBody, $expectedTrailers]) {
            $trailers = CommitMessage::trailersFromBody($body);
            $t->same($expectedBody, CommitMessage::bodyWithoutTrailer($body), $body);
            $t->same(
                $expectedTrailers,
                array_map(static fn ($trailer): array => [$trailer->token, $trailer->value], $trailers),
                $body,
            );
        }
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
    'commit signature verification helper follows gitoxide gpgsig range stripping' => static function (TestRunner $t): void {
        $body = "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "parent 1111111111111111111111111111111111111111\n"
            . "author A <a@example.test> 1700000000 +0000\n"
            . "committer C <c@example.test> 1700000000 +0000\n"
            . "gpgsig -----BEGIN PGP SIGNATURE-----\n"
            . " \n"
            . " c2lnbmVkLXdoaXRlc3BhY2U=\n"
            . " -----END PGP SIGNATURE-----\n"
            . " \n"
            . "\n"
            . "Whitespace signature commit\n";

        $commit = Commit::parse($body);
        $signature = $commit->signatureForVerification();
        $expectedSignedData = "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "parent 1111111111111111111111111111111111111111\n"
            . "author A <a@example.test> 1700000000 +0000\n"
            . "committer C <c@example.test> 1700000000 +0000\n"
            . "\n"
            . "Whitespace signature commit\n";

        $t->same("-----BEGIN PGP SIGNATURE-----\n\nc2lnbmVkLXdoaXRlc3BhY2U=\n-----END PGP SIGNATURE-----\n", $signature['signature'] ?? null);
        $t->same($expectedSignedData, $signature['signedData'] ?? null);
        $t->same($expectedSignedData, $commit->signedDataForSignature());
        $t->same(false, str_contains($signature['signedData'] ?? '', 'gpgsig '));
        $t->same($signature['signature'], $commit->pgpSignature());

        $tokens = Commit::iterateTokens($body);
        $t->same(['tree', 'parent', 'author', 'committer', 'extraHeader', 'message'], array_map(static fn (array $result): ?string => $result['token']['type'] ?? null, $tokens));
        $t->same($signature['signature'], $tokens[4]['token']['value']);
        $t->same("Whitespace signature commit\n", $tokens[5]['token']['message']);
    },
    'old git multi gpgsig header lines remain separate and signed data strips only the first one' => static function (TestRunner $t): void {
        $body = "tree 0123456789abcdef0123456789abcdef01234567\n"
            . "parent 1111111111111111111111111111111111111111\n"
            . "author Junio C Hamano <gitster@pobox.com> 1319256362 -0700\n"
            . "committer Junio C Hamano <gitster@pobox.com> 1319259176 -0700\n"
            . "gpgsig -----BEGIN PGP SIGNATURE-----\n"
            . "gpgsig Version: GnuPG v1.4.10 (GNU/Linux)\n"
            . "gpgsig \n"
            . "gpgsig cGF5bG9hZA==\n"
            . "gpgsig -----END PGP SIGNATURE-----\n"
            . "\n"
            . "pretty: %G[?GS] placeholders\n";

        $commit = Commit::parse($body);
        $signature = $commit->signatureForVerification();

        $t->same(5, count($commit->extraHeaderValues('gpgsig')));
        $t->same('-----BEGIN PGP SIGNATURE-----', $commit->pgpSignature());
        $t->same('-----BEGIN PGP SIGNATURE-----', $signature['signature'] ?? null);
        $t->contains('gpgsig Version: GnuPG v1.4.10 (GNU/Linux)', $signature['signedData'] ?? '');
        $t->contains('gpgsig -----END PGP SIGNATURE-----', $signature['signedData'] ?? '');
        $t->same($body, $commit->storageBytes());
        $t->same($body, Commit::parse($commit->storageBytes())->storageBytes());
        $t->same('pretty: %G[?GS] placeholders' . "\n", $commit->message);
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
        $t->same($fixture['expectedAuthorIdentity'], $summary['author']['identity']);
        $t->same($fixture['expectedAuthorOffset'], $summary['author']['offsetSeconds']);
        $t->same($fixture['expectedCommitterIdentity'], $summary['committer']['identity']);
        $t->same('UTF-8', $summary['encoding']);
        $t->contains('BEGIN SSH SIGNATURE', $summary['signatureHeader']);
        $t->same($fixture['expectedSignatureHeaderPosition'], $summary['signatureHeaderPosition']);
        $t->same(1, $summary['signatureHeaderCount']);
        $t->same($fixture['expectedMergeTagCount'], $summary['mergeTagCount']);
        $t->same([$fixture['expectedMergeTagName']], $summary['mergeTagNames']);
        $t->same($fixture['expectedMergeTagTarget'], $summary['mergeTags'][0]['target']);
        $t->same($fixture['expectedMergeTagKind'], $summary['mergeTags'][0]['kind']);
        $t->same($fixture['expectedMergeTagTagger'], $summary['mergeTags'][0]['tagger']);
        $t->same($fixture['expectedMergeTagMessage'], $summary['mergeTags'][0]['message']);
        $t->same($fixture['expectedSummary'], $summary['summary']);
        $t->same($fixture['expectedBodyWithoutTrailers'], $summary['bodyWithoutTrailers']);
        $t->same($fixture['expectedSignedOffBy'], $summary['signedOffBy']);
        $t->same($fixture['expectedCoAuthors'], $summary['coAuthoredBy']);
        $t->same($fixture['expectedAckedBy'], $summary['ackedBy']);
        $t->same($fixture['expectedReviewedBy'], $summary['reviewedBy']);
        $t->same($fixture['expectedTestedBy'], $summary['testedBy']);
        $t->same($fixture['expectedStandaloneBodyWithoutTrailers'], $summary['standaloneBodyWithoutTrailers']);
        $t->same($fixture['expectedStandaloneTrailerTokens'], $summary['standaloneTrailerTokens']);
        $t->same(false, $summary['signedDataHasSignatureHeader']);
        $t->same($fixture['expectedTokenTypes'], $summary['tokenTypes']);
        $t->same($fixture['expectedTokenExtraHeaders'], $summary['tokenExtraHeaderNames']);
        $t->same($fixture['expectedStorageSha1'], $summary['storageSha1']);
        $t->same($fixture['expectedObjectSha1'], $summary['objectSha1']);
        $t->same($fixture['expectedSize'], $summary['size']);
        $t->same(true, $summary['roundTripMatches']);
        $t->same(0, $summary['lateStandardHeaderParentCount']);
        $t->same(null, $summary['lateStandardHeaderEncoding']);
        $t->same($fixture['expectedLateParentExtraHeader'], $summary['lateStandardHeaderParentExtra']);
        $t->same('UTF-8', $summary['lateStandardHeaderEncodingExtra']);
        $t->same(true, $summary['misorderedHeaderRejected']);
        $t->same($fixture['expectedWriterObjectIdGuard'], $summary['writerObjectIdGuard']);
        $t->same($fixture['expectedMixedHashGuard'], $summary['mixedHashGuard']);
        $t->same($fixture['expectedSignatureLineGuard'], $summary['signatureLineGuard']);
        $t->same($fixture['expectedOddTimestampAuthorTime'], $summary['oddTimestampAuthorTime']);
        $t->same($fixture['expectedOddTimestampCommitterTime'], $summary['oddTimestampCommitterTime']);
        $t->same($fixture['expectedOddTimestampCommitterRawTime'], $summary['oddTimestampCommitterRawTime']);
        $t->same(true, $summary['oddTimestampRoundTripMatches']);
        $t->same($fixture['expectedWhitespaceSignature'], $summary['whitespaceSignature']);
        $t->same($fixture['expectedWhitespaceSignedDataSha1'], $summary['whitespaceSignedDataSha1']);
        $t->same(false, $summary['whitespaceSignedDataHasSignatureHeader']);
        $t->same($fixture['expectedWhitespaceTokenTypes'], $summary['whitespaceTokenTypes']);
        $t->same($fixture['expectedMultiGpgsigHeaderCount'], $summary['multiGpgsigHeaderCount']);
        $t->same($fixture['expectedMultiGpgsigFirstSignature'], $summary['multiGpgsigFirstSignature']);
        $t->same($fixture['expectedMultiGpgsigSignedDataSha1'], $summary['multiGpgsigSignedDataSha1']);
        $t->same(true, $summary['multiGpgsigSignedDataKeepsLaterGpgsigLines']);
        $t->same(true, $summary['multiGpgsigRoundTripMatches']);
    },
];
