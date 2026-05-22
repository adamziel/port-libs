<?php

declare(strict_types=1);

use PortLibs\Gitoxide\Commit;
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
        $t->same("Import WordPress content\n\nWith block fixtures.\n", $commit->message);
    },
    'commit parser rejects missing required headers' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => Commit::parse("tree 0123456789abcdef0123456789abcdef01234567\n\nmsg"));
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
];

