<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitFilter;
use PortLibs\Gitoxide\GitHash;

$assertThrowsMessage = static function (TestRunner $t, string $expectedMessage, callable $callback): void {
    try {
        $callback();
    } catch (Throwable $throwable) {
        $t->same($expectedMessage, $throwable->getMessage());
        return;
    }

    throw new RuntimeException('Expected exception was not thrown');
};

$noCall = static function (?string &$buf): ?bool {
    throw new RuntimeException('index function will not be called');
};

$noObjectInIndex = static function (?string &$buf): ?bool {
    return null;
};

return [
    'gix-filter ident undo no_id_changes_nothing' => static function (TestRunner $t): void {
        $buf = '';
        $t->same(false, GitFilter::identUndo('hello', $buf), 'the buffer is not touched');
        $t->same('', $buf);
    },
    'gix-filter ident undo empty' => static function (TestRunner $t): void {
        $buf = '';
        $t->same(false, GitFilter::identUndo('', $buf), 'nothing to be done in empty buffer');
    },
    'gix-filter ident undo nothing_if_newline_between_dollars' => static function (TestRunner $t): void {
        $buf = '';
        $t->same(false, GitFilter::identUndo(" \$Id: \n\$", $buf));
        $t->same('', $buf);
    },
    'gix-filter ident undo nothing_if_it_is_not_id' => static function (TestRunner $t): void {
        $buf = '';
        $t->same(false, GitFilter::identUndo(' $id: something$', $buf), "it's matching case-sensitively");
        $t->same('', $buf);
    },
    'gix-filter ident undo anything_between_dollar_id_dollar' => static function (TestRunner $t): void {
        $buf = '';
        $t->same(true, GitFilter::identUndo(" \$Id: something\$\nhello", $buf));
        $t->same(" \$Id\$\nhello", $buf);
    },
    'gix-filter ident undo multiple' => static function (TestRunner $t): void {
        $buf = '';
        $t->same(true, GitFilter::identUndo("\$Id: a\n\$ \$Id: something\$\nhello\$Id: hex\$\nlast \$Id:other\$\n\$Id: \n\$", $buf));
        $t->same("\$Id: a\n\$ \$Id\$\nhello\$Id\$\nlast \$Id\$\n\$Id: \n\$", $buf);

        $t->same(true, GitFilter::identUndo("\$Id: a\n\$\$Id:\$\$Id: hex\$\n\$Id:other\$\$Id: \$end", $buf));
        $t->same("\$Id: a\n\$\$Id\$\$Id\$\n\$Id\$\$Id\$end", $buf);
    },
    'gix-filter ident apply no_change' => static function (TestRunner $t): void {
        $buf = '';
        foreach ([
            '',
            'nothing',
            '$ID$ case sensitive matching',
            '$Id: expanded is ignored$',
        ] as $inputNoMatch) {
            $t->same(false, GitFilter::identApply($inputNoMatch, GitHash::SHA1, $buf), 'no substitution happens, nothing to do');
            $t->same('', $buf);
        }
    },
    'gix-filter ident apply simple' => static function (TestRunner $t): void {
        $buf = '';
        $t->same(true, GitFilter::identApply('$Id$', GitHash::SHA1, $buf), 'a change happens');
        $t->same('$Id: b3f5ebfb5843bc43ceecff6d4f26bb37c615beb1$', $buf);

        $t->same(true, GitFilter::identApply('$Id$ $Id$ foo', GitHash::SHA1, $buf));
        $expectedHash = 'e230cff7a9624f59eaa28bfb97602c3a03651a49';
        $t->same('$Id: ' . $expectedHash . '$ $Id: ' . $expectedHash . '$ foo', $buf);
    },
    'gix-filter ident apply round_trips' => static function (TestRunner $t): void {
        $buf = '';
        foreach ([
            "hi\n\$Id\$\nho\n\t\$Id\$\$Id\$\$Id\$",
            '$Id$',
            '$Id$ and one more $Id$ and done',
        ] as $input) {
            $t->same(true, GitFilter::identApply($input, GitHash::SHA1, $buf), 'the input was rewritten');
            $t->same(true, GitFilter::identUndo($buf, $buf), 'undo does something as well');
            $t->same($input, $buf, 'the filter can be undone perfectly');
        }
    },
    'gix-filter eol convert_to_git with_binary_attribute_is_never_converted' => static function (TestRunner $t) use ($noCall): void {
        $buf = '';
        $t->same(false, GitFilter::convertToGit("hi\r\nho", GitFilter::ATTR_BINARY, $buf, $noCall), "the user marked it as binary so it's never being touched");
    },
    'gix-filter eol convert_to_git no_crlf_means_no_work' => static function (TestRunner $t) use ($noCall, $noObjectInIndex): void {
        $buf = '';
        $t->same(false, GitFilter::convertToGit('hi', GitFilter::ATTR_TEXT_CRLF, $buf, $noCall));

        $t->same(false, GitFilter::convertToGit('hi', GitFilter::ATTR_TEXT_AUTO_CRLF, $buf, $noObjectInIndex), 'in auto-mode, the object is queried in the index as well.');
    },
    'gix-filter eol convert_to_git detected_as_binary' => static function (TestRunner $t) use ($noCall): void {
        $buf = '';
        $t->same(false, GitFilter::convertToGit("hi\0zero makes it binary", GitFilter::ATTR_TEXT_AUTO, $buf, $noCall), 'in auto-mode, we have a heuristic to see if the buffer is binary');
    },
    'gix-filter eol convert_to_git fast_conversion_by_stripping_cr' => static function (TestRunner $t) use ($noCall): void {
        $buf = '';
        $t->same(true, GitFilter::convertToGit("a\r\nb\r\nc", GitFilter::ATTR_TEXT_CRLF, $buf, $noCall));
        $t->same("a\nb\nc", $buf, 'here carriage returns can just be stripped');
    },
    'gix-filter eol convert_to_git slower_conversion_due_to_lone_cr' => static function (TestRunner $t) use ($noCall): void {
        $buf = '';
        $t->same(true, GitFilter::convertToGit("\r\ra\r\nb\r\nc", GitFilter::ATTR_TEXT_CRLF, $buf, $noCall));
        $t->same("\r\ra\nb\nc", $buf, 'here carriage returns cannot be stripped but must be handled in pairs');
    },
    'gix-filter eol convert_to_git crlf_in_index_prevents_conversion_to_lf' => static function (TestRunner $t): void {
        $buf = '';
        $called = false;
        $indexObject = static function (?string &$buf) use (&$called): ?bool {
            $called = true;
            $buf = "with CRLF\r\n";
            return true;
        };

        $changed = GitFilter::convertToGit("eligible\n", GitFilter::ATTR_TEXT_AUTO_INPUT, $buf, $indexObject);
        $t->same(true, $called, 'in auto mode, the index is queried as well');
        $t->same(false, $changed, "we saw the CRLF is present in the index, so it's unsafe to make changes");
    },
    'gix-filter eol convert_to_git round_trip_check' => static function (TestRunner $t) use ($assertThrowsMessage, $noCall): void {
        $buf = '';
        foreach ([
            "lone-nl\nhi\r\nho" => "LF would be replaced by CRLF in 'hello.txt'",
            "lone-cr\nhi\r\nho" => "LF would be replaced by CRLF in 'hello.txt'",
        ] as $input => $expected) {
            $assertThrowsMessage(
                $t,
                $expected,
                static fn () => GitFilter::convertToGit($input, GitFilter::ATTR_TEXT_CRLF, $buf, $noCall, [
                    'roundTripCheck' => GitFilter::ROUND_TRIP_FAIL,
                    'path' => 'hello.txt',
                ])
            );

            $t->same(true, GitFilter::convertToGit($input, GitFilter::ATTR_TEXT_CRLF, $buf, $noCall, [
                'roundTripCheck' => GitFilter::ROUND_TRIP_WARN,
                'path' => 'hello.txt',
            ]), "in warn mode, we will get a result even though it won't round-trip");
        }
    },
    'gix-filter eol convert_to_worktree no_conversion_if_attribute_digest_does_not_allow_it' => static function (TestRunner $t): void {
        $buf = '';
        foreach ([GitFilter::ATTR_BINARY, GitFilter::ATTR_TEXT_INPUT, GitFilter::ATTR_TEXT_AUTO_INPUT] as $digest) {
            $t->same(false, GitFilter::convertToWorktree("hi\nho", $digest, $buf), "the digest doesn't allow for CRLF changes");
        }
    },
    'gix-filter eol convert_to_worktree no_conversion_if_configuration_does_not_allow_it' => static function (TestRunner $t): void {
        $buf = '';
        foreach ([GitFilter::ATTR_TEXT, GitFilter::ATTR_TEXT_AUTO] as $digest) {
            foreach ([
                ['autoCrlf' => GitFilter::AUTO_CRLF_INPUT, 'eol' => GitFilter::MODE_CRLF],
                ['autoCrlf' => GitFilter::AUTO_CRLF_DISABLED, 'eol' => GitFilter::MODE_LF],
            ] as $config) {
                $t->same(false, GitFilter::convertToWorktree("hi\nho", $digest, $buf, $config), "the configuration doesn't allow for changes");
            }
        }
    },
    'gix-filter eol convert_to_worktree no_conversion_if_nothing_to_do' => static function (TestRunner $t): void {
        $buf = '';
        foreach ([
            ["hi\r\nho", GitFilter::ATTR_TEXT_CRLF, 'no lone line feed to handle'],
            ["binary\0linefeed\nho", GitFilter::ATTR_TEXT_AUTO_CRLF, 'binary in auto-mode is never handled'],
            ["binary\nlinefeed\r\nho", GitFilter::ATTR_TEXT_AUTO_CRLF, 'mixed crlf and lf is avoided'],
            ["eligible-but-disabled\nhere", GitFilter::ATTR_BINARY, 'designated binary is never handled'],
        ] as [$input, $digest, $msg]) {
            $t->same(false, GitFilter::convertToWorktree($input, $digest, $buf), $msg);
        }
    },
    'gix-filter eol convert_to_worktree each_nl_is_replaced_with_crnl' => static function (TestRunner $t): void {
        $buf = '';
        $t->same(true, GitFilter::convertToWorktree("hi\n\nho\nend", GitFilter::ATTR_TEXT_CRLF, $buf), 'the buffer has to be changed as it is explicitly demanded and has newlines to convert');
        $t->same("hi\r\n\r\nho\r\nend", $buf);
    },
    'gix-filter eol convert_to_worktree existing_crnl_are_not_replaced_for_safety_nor_are_lone_cr' => static function (TestRunner $t): void {
        $buf = '';
        $t->same(true, GitFilter::convertToWorktree("hi\r\n\nho\r\nend\r", GitFilter::ATTR_TEXT_CRLF, $buf));
        $t->same("hi\r\n\r\nho\r\nend\r", $buf);
    },
    'gix-filter worktree encoding for_label unknown' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            "An encoding named 'FOO' is not known",
            static fn () => GitFilter::worktreeEncodingForLabel('FOO')
        );
    },
    'gix-filter worktree encoding for_label utf32_is_not_supported' => static function (TestRunner $t): void {
        foreach (['UTF-32BE', 'UTF-32LE', 'UTF-32', 'UTF-32LE-BOM', 'UTF-32BE-BOM'] as $enc) {
            $t->throws(InvalidArgumentException::class, static fn () => GitFilter::worktreeEncodingForLabel($enc));
        }
    },
    'gix-filter worktree encoding for_label various_spellings_of_utf_8_are_supported' => static function (TestRunner $t): void {
        foreach (['UTF8', 'UTF-8', 'utf-8', 'utf8'] as $enc) {
            $t->same('UTF-8', GitFilter::worktreeEncodingForLabel($enc));
        }
    },
    'gix-filter worktree encoding for_label various_utf_16_without_bom_suffix_are_supported' => static function (TestRunner $t): void {
        foreach (['UTF-16BE', 'UTF-16LE'] as $label) {
            $t->same($label, GitFilter::worktreeEncodingForLabel($label));
        }
    },
    'gix-filter worktree encoding for_label various_utf_16_with_bom_suffix_are_unsupported' => static function (TestRunner $t): void {
        foreach (['UTF-16BE-BOM', 'UTF-16LE-BOM'] as $label) {
            $t->throws(InvalidArgumentException::class, static fn () => GitFilter::worktreeEncodingForLabel($label));
        }
    },
    'gix-filter worktree encoding for_label latin_1_is_supported_with_fallback' => static function (TestRunner $t): void {
        $t->same('windows-1252', GitFilter::worktreeEncodingForLabel('latin-1'), 'the encoding crate has its own fallback for ISO-8859-1 which we try to use');
    },
    'gix-filter worktree encode_to_git simple' => static function (TestRunner $t): void {
        $input = 'hello';
        foreach ([GitFilter::ROUND_TRIP_SKIP, GitFilter::ROUND_TRIP_FAIL] as $roundTrip) {
            $buf = '';
            GitFilter::encodeToGit($input, GitFilter::ENCODING_UTF8, $buf, $roundTrip);
            $t->same($input, $buf);
        }
    },
    'gix-filter worktree encode_to_worktree shift_jis' => static function (TestRunner $t): void {
        $input = 'ハローワールド';
        $buf = '';
        GitFilter::encodeToWorktree($input, GitFilter::ENCODING_SHIFT_JIS, $buf);

        $reEncoded = '';
        GitFilter::encodeToGit($buf, GitFilter::ENCODING_SHIFT_JIS, $reEncoded, GitFilter::ROUND_TRIP_FAIL);

        $t->same($input, $reEncoded, 'this should be round-trippable too');
    },
];
