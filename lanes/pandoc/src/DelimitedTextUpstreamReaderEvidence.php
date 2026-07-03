<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DelimitedTextUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-delimited-text-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-delimited-text-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-delimited-text-root';
    public const CHECKED_IN_CURRENT_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/upstream-current-csv-reader';
    public const CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/generated-current-csv-reader';
    public const CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/generated-current-tsv-reader';
    public const EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT = 2;
    public const EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT = 0;
    public const EXPECTED_STATIC_CSV_ADJACENT_RST_FIXTURE_COUNT = 2;
    public const EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT = 20;
    public const EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT = 15;

    private const CHECKED_IN_CURRENT_CSV_FIXTURES = [
        'csv.md' => [
            'role' => 'direct-csv-command-reader-native-output',
            'upstreamPath' => 'test/command/csv.md',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-current-csv-reader/csv.md',
            'sha256' => '42a8bc56612d061388889a10d73b1d34fb870595785ee550ef43c6a065a77ad6',
            'bytes' => 2719,
        ],
        '01.csv' => [
            'role' => 'direct-csv-command-reader-input-fixture',
            'upstreamPath' => 'test/command/01.csv',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-current-csv-reader/01.csv',
            'sha256' => '257c619e19786fddf7685a31a45f6495446a5213083540d09ecba6ce7f1e62cd',
            'bytes' => 47,
        ],
    ];

    private const CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURES = [
        'quoted-multiline.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-multiline',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.csv',
            'sha256' => 'a038fe6edd54cf98e2b3afaf14dd4e5cbdbbdb86ab2b62d9bd60cd783ce3324e',
            'bytes' => 80,
        ],
        'quoted-multiline.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-multiline',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.native',
            'sha256' => 'b0b4ae0c2f04421f042eef43c3a79ab699e771a3873e28b23e85d15091f03d57',
            'bytes' => 1894,
        ],
        'post-delimiter-space.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'post-delimiter-space',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.csv',
            'sha256' => '109867931d7a1d37a49d565c175d085415b378800e2acd2d4ec8f1c24935601f',
            'bytes' => 131,
        ],
        'post-delimiter-space.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'post-delimiter-space',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.native',
            'sha256' => '766278b6bf6c85a71a50a50df5c8ee776c7e774020897f8f39e34d9841a9c8d1',
            'bytes' => 1684,
        ],
        'backslash-escaped-quote.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'backslash-escaped-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-quote.csv',
            'sha256' => 'ae11512ae25941072ef5c297914c544a0815f2a2aba9527a9c80ca1ac5aa406e',
            'bytes' => 33,
        ],
        'backslash-escaped-quote.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'backslash-escaped-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-quote.native',
            'sha256' => '0a512d33990f2629025b2eaae15e34d070fe5e985926e6d2d06d2937ac8ef1b5',
            'bytes' => 932,
        ],
        'quoted-linebreak.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'quoted-linebreak',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-linebreak.csv',
            'sha256' => 'b017e1cc1434c3422538e1b16fb240ae2c35b0bda12041f568cf5da7921b0476',
            'bytes' => 48,
        ],
        'quoted-linebreak.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'quoted-linebreak',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-linebreak.native',
            'sha256' => '84472dfb9a0d40daf8c8c38cd50892cd2e13e8118e133ebfcac3720a16ae54f8',
            'bytes' => 2136,
        ],
        'no-header-ragged.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'no-header-ragged',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-ragged.csv',
            'sha256' => '178c37d0389b55262ee5a906f2d6a83f914da8bfd819fd37718206065baf876d',
            'bytes' => 57,
        ],
        'no-header-ragged.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'no-header-ragged',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-ragged.native',
            'sha256' => '2e6f817cfdf74fb6876cc386ea863d0b5469e2f5c72da6aac8c521fc9fabc8d0',
            'bytes' => 1480,
        ],
        'bom-leading-whitespace.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'bom-leading-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bom-leading-whitespace.csv',
            'sha256' => '6812293a42d8d68da5c184020b3a3a4a579b6f77125080bf40486b8e433f3aec',
            'bytes' => 50,
        ],
        'bom-leading-whitespace.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'bom-leading-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bom-leading-whitespace.native',
            'sha256' => '9657368b59d4181c81246a5a11bd5dba277a29088dfdc392c31e2a44fd615e36',
            'bytes' => 1229,
        ],
        'text-after-closing-quote.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'text-after-closing-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/text-after-closing-quote.csv',
            'sha256' => 'baa94e35273deb1680660c255569262f9258132d2f97c7550b082f9676e991a6',
            'bytes' => 65,
        ],
        'text-after-closing-quote.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'text-after-closing-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/text-after-closing-quote.native',
            'sha256' => '8e33c870e16bb77dc144c177673e3313dce9415c80bda3c9b13123466d42442e',
            'bytes' => 1246,
        ],
        'trailing-empty-fields.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'trailing-empty-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/trailing-empty-fields.csv',
            'sha256' => '2f8e15547906de3b9b95a5d354e039809382171b9d64366d751d8e493b5553d5',
            'bytes' => 62,
        ],
        'trailing-empty-fields.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'trailing-empty-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/trailing-empty-fields.native',
            'sha256' => '86ca6197ec2c3178474e08e68f8deac8996f0fc7f994a803ec1a399e56f9f849',
            'bytes' => 1477,
        ],
        'crlf-rows.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'crlf-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/crlf-rows.csv',
            'sha256' => '9936f7d7046f8e486617541749ff65707d43e463b88577ee8c187615f7c7bc9d',
            'bytes' => 45,
        ],
        'crlf-rows.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'crlf-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/crlf-rows.native',
            'sha256' => '95a70343048b4accc704b7ba0613fce1dfea60c0f719eadadb9c2c73761f2c76',
            'bytes' => 1210,
        ],
        'unquoted-space-empty-quoted.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'unquoted-space-empty-quoted',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.csv',
            'sha256' => 'f59f8d34be7b452806cfd54e49584047e6156c6791b7df067d7452ba697ddba7',
            'bytes' => 74,
        ],
        'unquoted-space-empty-quoted.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'unquoted-space-empty-quoted',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.native',
            'sha256' => '2460dd7891857c3927c5f229fbd819afe432604a92606a61f3cb5b87d6bcd3d7',
            'bytes' => 1523,
        ],
        'comment-looking-data.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'comment-looking-data',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/comment-looking-data.csv',
            'sha256' => 'cbfda6df02a13b5ba96fcd6ab171b5083c20ef97af65e858ae110032eb9f51c8',
            'bytes' => 96,
        ],
        'comment-looking-data.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'comment-looking-data',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/comment-looking-data.native',
            'sha256' => 'dcb0f03da9d7ec90de5ce244b3e3002b4f41cc18a9f10314189bcb457823bab6',
            'bytes' => 1617,
        ],
        'no-header-edge-delimiters.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'no-header-edge-delimiters',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-edge-delimiters.csv',
            'sha256' => 'fecf7f0f3ba6bd37411f4c8ebcd36ffedf3a9c8f1e52213fdd044ae4decc0fb1',
            'bytes' => 49,
        ],
        'no-header-edge-delimiters.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'no-header-edge-delimiters',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-edge-delimiters.native',
            'sha256' => '43066e049b19a9f9f6a210b3e25981d07a01915ba784dd86d8427fbf109408c9',
            'bytes' => 1395,
        ],
        'single-quote-dialect.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'single-quote-dialect',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-dialect.csv',
            'sha256' => 'd59a5e83a298313470b808ba0381a51e3eacb0d50f317719717999e3009c1c2d',
            'bytes' => 104,
        ],
        'single-quote-dialect.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'single-quote-dialect',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-dialect.native',
            'sha256' => '9c05ec1d28eeda63e95a2f99d84cd0ce4bd6413c6b786efb5c973f86dcdb79b6',
            'bytes' => 1646,
        ],
        'semicolon-delimiter-multiline-cell.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'semicolon-delimiter-multiline-cell',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/semicolon-delimiter-multiline-cell.csv',
            'sha256' => 'c383ab2b385dcae671a50b2b226051d74d738aaa627dd9c4393af0d39b863336',
            'bytes' => 112,
        ],
        'semicolon-delimiter-multiline-cell.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'semicolon-delimiter-multiline-cell',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/semicolon-delimiter-multiline-cell.native',
            'sha256' => '32ddacd1d7a77be7516423cc0d67ade520cf024bac92b03607dda08267dfad2f',
            'bytes' => 2016,
        ],
        'cr-only-rows.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'cr-only-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/cr-only-rows.csv',
            'sha256' => 'fca94752c9fdfbe612a0a998c33a2ba3d5fd816db58ab9648bd41d9318bf3624',
            'bytes' => 45,
        ],
        'cr-only-rows.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'cr-only-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/cr-only-rows.native',
            'sha256' => 'a505f9be0ae0712a85d2ce4f9d035e7299d2b730b327a9d81ddfe10bbc2a8b3f',
            'bytes' => 1227,
        ],
        'unterminated-quote-eof.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'unterminated-quote-eof',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unterminated-quote-eof.csv',
            'sha256' => '272c4e0c03e402d21e2b808459fc913dd3eacc2e7c9dafdfb6f506c8127eb747',
            'bytes' => 34,
        ],
        'unterminated-quote-eof.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'unterminated-quote-eof',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unterminated-quote-eof.native',
            'sha256' => '754ba8a6135cf7f7064b714cb6a33990958865e0a5ee04532710a74cc395e74b',
            'bytes' => 925,
        ],
        'duplicate-header-labels.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'duplicate-header-labels',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/duplicate-header-labels.csv',
            'sha256' => 'd0627dffb43d149d884fba447424eed9544c36f9885516afd3e2a04e807c101f',
            'bytes' => 42,
        ],
        'duplicate-header-labels.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'duplicate-header-labels',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/duplicate-header-labels.native',
            'sha256' => '7e2b213a1c5fa209f5c3f41187012455d9bd701b2da6ff379b15519707ff938e',
            'bytes' => 1211,
        ],
        'keep-space-after-comma.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'keep-space-after-comma',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-after-comma.csv',
            'sha256' => '68e6bdf13bdb5129562eca08ba28a7516377821d8d2cf951f2927ae923dfb656',
            'bytes' => 118,
        ],
        'keep-space-after-comma.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'keep-space-after-comma',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-after-comma.native',
            'sha256' => '5a110b2e35a46a8a3e98961b0a68baf210d015a374c99fdd04c60dfee641c721',
            'bytes' => 1731,
        ],
        'space-delimiter-single-quote.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'space-delimiter-single-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-delimiter-single-quote.csv',
            'sha256' => '577165de4a8e2beaee7ef748dc7686c9a283f71e730f8d2e21be94e16cde65f4',
            'bytes' => 79,
        ],
        'space-delimiter-single-quote.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'space-delimiter-single-quote',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-delimiter-single-quote.native',
            'sha256' => '594390fc80d43bada7903e66a771be44bbef23b24a7f11a2e9ac87e96bc542dd',
            'bytes' => 1579,
        ],
        'blank-row-skipped.csv' => [
            'role' => 'generated-csv-native-parity-input-fixture',
            'sample' => 'blank-row-skipped',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-row-skipped.csv',
            'sha256' => '4d721ac02e32060a616d3fef61083cc6f88adae5ace5ced3d77fe5f6fb966321',
            'bytes' => 71,
        ],
        'blank-row-skipped.native' => [
            'role' => 'generated-csv-native-parity-expected-native-output',
            'sample' => 'blank-row-skipped',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-row-skipped.native',
            'sha256' => 'cf931bb22f5eeb8934579b99d4109e60801dd40e9f48e4e78a4e24038bc07a5f',
            'bytes' => 1555,
        ],
    ];

    private const CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURES = [
        'simple.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'simple',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/simple.tsv',
            'sha256' => 'fcee0aed5a2fde11bbd19f2fc4445357a0d7bbd9c9962df6630fed4b6178ff8e',
            'bytes' => 71,
        ],
        'simple.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'simple',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/simple.native',
            'sha256' => 'f4c930c9d309c4dd6ec1c50eda9e45ff3614566e6c26e4b5254ce3e9c62abb2a',
            'bytes' => 1540,
        ],
        'quote-trailing.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'quote-trailing',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.tsv',
            'sha256' => 'c5694bc5e74a5920c4752369bd967be614f3d7f8fde6395bcd05c9b5f22d85dd',
            'bytes' => 102,
        ],
        'quote-trailing.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'quote-trailing',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.native',
            'sha256' => '51b8ce6dc3164f654f50f7fc1597e2788b04a2b634a32a3f52d51951b68260b6',
            'bytes' => 1975,
        ],
        'unicode-safe.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'unicode-safe',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.tsv',
            'sha256' => 'cd7a0f7e2c4737a1884c0ff3ec73bf6a5990fbdfb6ba1b588b6a6d9202ab3e02',
            'bytes' => 91,
        ],
        'unicode-safe.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'unicode-safe',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.native',
            'sha256' => 'e7d3ea0f37e8d3b0613155eaaf480edf042cd5e22aa4291866ae8a0e627fe990',
            'bytes' => 1370,
        ],
        'ragged-blank-fields.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'ragged-blank-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.tsv',
            'sha256' => '3eb62cad900b02542011bfcb6ffa891856dbf398aa7e7174785264494258c9d4',
            'bytes' => 76,
        ],
        'ragged-blank-fields.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'ragged-blank-fields',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.native',
            'sha256' => 'a6f8a232c40e26e421c2640f35ff1f1010f24eb7e42341b9b09dfadfb86a2bee',
            'bytes' => 2159,
        ],
        'no-header.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'no-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header.tsv',
            'sha256' => '0553e41c6e8a6257ad01d8dfad5c1ffecfb495a58273b38b1115ddb5635449bd',
            'bytes' => 37,
        ],
        'no-header.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'no-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header.native',
            'sha256' => '9d9356cfcfb719fb3093faf108a3f70cbf15dfb3921b37420d8d6a3eef3caf46',
            'bytes' => 1186,
        ],
        'bom-leading-whitespace.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'bom-leading-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/bom-leading-whitespace.tsv',
            'sha256' => 'd10a56e1e3d9cdf0abb8c3f800d45a8bace164a4ff015c72dad5b5206b55f451',
            'bytes' => 48,
        ],
        'bom-leading-whitespace.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'bom-leading-whitespace',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/bom-leading-whitespace.native',
            'sha256' => '9657368b59d4181c81246a5a11bd5dba277a29088dfdc392c31e2a44fd615e36',
            'bytes' => 1229,
        ],
        'blank-row-literal-punctuation.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'blank-row-literal-punctuation',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-row-literal-punctuation.tsv',
            'sha256' => '3971c352574fb88bf49073fab5e73d309c3e50d23c169250aec22e8ed3e0c4d8',
            'bytes' => 51,
        ],
        'blank-row-literal-punctuation.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'blank-row-literal-punctuation',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-row-literal-punctuation.native',
            'sha256' => '29623a127b4bc0bf3f17b351bfa9f712a1ecbd2d24741d3c2f6aa0475e250023',
            'bytes' => 1253,
        ],
        'comment-looking-data.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'comment-looking-data',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/comment-looking-data.tsv',
            'sha256' => 'a52c8e6587c36a1deb6d86bce90910eb138f9ed983ba66c6336eca055f0e9d04',
            'bytes' => 84,
        ],
        'comment-looking-data.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'comment-looking-data',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/comment-looking-data.native',
            'sha256' => '52a97c04e576bedd6bec2609850c3a65c3a90fc165326d9ab11beae1f447cc2e',
            'bytes' => 1399,
        ],
        'no-header-edge-delimiters.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'no-header-edge-delimiters',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-edge-delimiters.tsv',
            'sha256' => '0e90d36fbdce51c4ee0557fa0d1526d849493f30d408675cc445094b7ae79e45',
            'bytes' => 58,
        ],
        'no-header-edge-delimiters.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'no-header-edge-delimiters',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-edge-delimiters.native',
            'sha256' => '1e219ae43ee7ef40c4b05ba0565a1e1f7b127a3b6ddda615ce5d9e87622446a4',
            'bytes' => 1769,
        ],
        'csv-quoted-literal.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'csv-quoted-literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/csv-quoted-literal.tsv',
            'sha256' => '1c28f3c034a65a005034ae5806e4d035eecd9704c6cf1055b2f0c041e96719be',
            'bytes' => 129,
        ],
        'csv-quoted-literal.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'csv-quoted-literal',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/csv-quoted-literal.native',
            'sha256' => '419fb3357404e8b572bf42e5fe3cc32c410f4b69566b282295a7039490ab6fdc',
            'bytes' => 1734,
        ],
        'keep-space-after-tab.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'keep-space-after-tab',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/keep-space-after-tab.tsv',
            'sha256' => '4a015006efd98569714058528747683dd5e3a384a0a9615d7d7ebce3bcd8e603',
            'bytes' => 119,
        ],
        'keep-space-after-tab.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'keep-space-after-tab',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/keep-space-after-tab.native',
            'sha256' => '88ffc2cd12c0dd74592bceeb20821ec9a38c10f87e9b60a808ca03569c9c1026',
            'bytes' => 1725,
        ],
        'crlf-rows.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'crlf-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/crlf-rows.tsv',
            'sha256' => '1ee34fc2887a5be7359dd06425faa9e15c47cc7fd65ea5b475119cf159951eb4',
            'bytes' => 44,
        ],
        'crlf-rows.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'crlf-rows',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/crlf-rows.native',
            'sha256' => 'ae90f3b65232ccb820321bacbc03f1f45224cfcfdb7eb2614315e124d91905e0',
            'bytes' => 1210,
        ],
        'quoted-tabs-and-newlines.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'quoted-tabs-and-newlines',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-tabs-and-newlines.tsv',
            'sha256' => '063ef586c65fd208bfb670a711edbd004501bb484fe5facbed94c6f898bb6f79',
            'bytes' => 94,
        ],
        'quoted-tabs-and-newlines.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'quoted-tabs-and-newlines',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-tabs-and-newlines.native',
            'sha256' => 'dbfdd6519302270f48a6831a9e0594d7779e14922b9f8fd120eee2a7204d2b5b',
            'bytes' => 1615,
        ],
        'blank-leading-header.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'blank-leading-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-leading-header.tsv',
            'sha256' => 'c2fd8d6c08e7858885d36a4d57a4f79f473418772f1c9f5c6f128b6fbba9858c',
            'bytes' => 21,
        ],
        'blank-leading-header.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'blank-leading-header',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-leading-header.native',
            'sha256' => '36321b161eb2743b361b6e5f2d8062b2de6d006969f64290fcbb84bb3d180ed2',
            'bytes' => 872,
        ],
        'basic-status.tsv' => [
            'role' => 'generated-tsv-native-parity-input-fixture',
            'sample' => 'basic-status',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/basic-status.tsv',
            'sha256' => 'd05b3c50b6780930533f48d3e8192cb4a50ee2f15dec69d75984d10f43dba22d',
            'bytes' => 61,
        ],
        'basic-status.native' => [
            'role' => 'generated-tsv-native-parity-expected-native-output',
            'sample' => 'basic-status',
            'checkedInPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/basic-status.native',
            'sha256' => '71b49eeb3ed15b82ae55464884fd30a7bf4191dbd04fb2625bea3a862896c4a9',
            'bytes' => 1262,
        ],
    ];

    private const GENERATED_CSV_NATIVE_SAMPLES = [
        'quoted-multiline' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.native',
        ],
        'post-delimiter-space' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.native',
        ],
        'backslash-escaped-quote' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-quote.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-quote.native',
            'options' => [
                'escape' => '\\',
            ],
        ],
        'quoted-linebreak' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-linebreak.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-linebreak.native',
        ],
        'no-header-ragged' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-ragged.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-ragged.native',
            'options' => [
                'header' => false,
            ],
        ],
        'bom-leading-whitespace' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bom-leading-whitespace.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bom-leading-whitespace.native',
        ],
        'text-after-closing-quote' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/text-after-closing-quote.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/text-after-closing-quote.native',
        ],
        'trailing-empty-fields' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/trailing-empty-fields.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/trailing-empty-fields.native',
        ],
        'crlf-rows' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/crlf-rows.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/crlf-rows.native',
        ],
        'unquoted-space-empty-quoted' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.native',
        ],
        'comment-looking-data' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/comment-looking-data.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/comment-looking-data.native',
        ],
        'no-header-edge-delimiters' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-edge-delimiters.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-edge-delimiters.native',
            'options' => [
                'header' => false,
            ],
        ],
        'single-quote-dialect' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-dialect.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-dialect.native',
            'options' => [
                'quote' => '\'',
            ],
        ],
        'semicolon-delimiter-multiline-cell' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/semicolon-delimiter-multiline-cell.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/semicolon-delimiter-multiline-cell.native',
            'options' => [
                'delimiter' => 'semicolon',
            ],
        ],
        'cr-only-rows' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/cr-only-rows.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/cr-only-rows.native',
        ],
        'unterminated-quote-eof' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unterminated-quote-eof.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unterminated-quote-eof.native',
        ],
        'duplicate-header-labels' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/duplicate-header-labels.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/duplicate-header-labels.native',
        ],
        'keep-space-after-comma' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-after-comma.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-after-comma.native',
            'options' => [
                'keepSpace' => true,
            ],
        ],
        'space-delimiter-single-quote' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-delimiter-single-quote.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-delimiter-single-quote.native',
            'options' => [
                'delimiter' => 'space',
                'quote' => '\'',
            ],
        ],
        'blank-row-skipped' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-row-skipped.csv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-row-skipped.native',
        ],
    ];

    private const GENERATED_TSV_NATIVE_SAMPLES = [
        'simple' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/simple.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/simple.native',
        ],
        'quote-trailing' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.native',
        ],
        'unicode-safe' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.native',
        ],
        'ragged-blank-fields' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.native',
        ],
        'no-header' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header.native',
            'options' => [
                'header' => false,
            ],
        ],
        'bom-leading-whitespace' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/bom-leading-whitespace.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/bom-leading-whitespace.native',
        ],
        'blank-row-literal-punctuation' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-row-literal-punctuation.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-row-literal-punctuation.native',
        ],
        'comment-looking-data' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/comment-looking-data.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/comment-looking-data.native',
        ],
        'no-header-edge-delimiters' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-edge-delimiters.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-edge-delimiters.native',
            'options' => [
                'header' => false,
            ],
        ],
        'csv-quoted-literal' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/csv-quoted-literal.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/csv-quoted-literal.native',
        ],
        'keep-space-after-tab' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/keep-space-after-tab.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/keep-space-after-tab.native',
            'options' => [
                'keepSpace' => true,
            ],
        ],
        'crlf-rows' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/crlf-rows.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/crlf-rows.native',
        ],
        'quoted-tabs-and-newlines' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-tabs-and-newlines.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-tabs-and-newlines.native',
            'options' => [
                'quote' => '"',
            ],
        ],
        'blank-leading-header' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-leading-header.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-leading-header.native',
        ],
        'basic-status' => [
            'inputPath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/basic-status.tsv',
            'expectedNativePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/basic-status.native',
        ],
    ];

    private const SOURCE_FILES = [
        'src/Text/Pandoc/CSV.hs',
        'src/Text/Pandoc/Readers/CSV.hs',
    ];

    private const CSV_ADJACENT_RST_FIXTURES = [
        [
            'path' => 'test/command/3533-rst-csv-tables.csv',
            'role' => 'rst-csv-table-included-csv-data',
            'reader' => 'rst',
            'directDelimitedTextReaderFixture' => false,
            'reason' => 'CSV data referenced by an RST csv-table directive; it is exercised through the RST reader integration path, not as direct CSV reader input.',
        ],
        [
            'path' => 'test/command/3533-rst-csv-tables.md',
            'role' => 'rst-csv-table-command-output-fixture',
            'reader' => 'rst',
            'directDelimitedTextReaderFixture' => false,
            'reason' => 'Command fixture for RST csv-table output; the reader under test is RST, not the direct CSV or TSV reader.',
        ],
    ];

    private readonly string $repoRoot;
    private readonly string $upstreamRoot;

    public function __construct(string $repoRoot, string $upstreamRoot = self::DEFAULT_RELATIVE_UPSTREAM_ROOT)
    {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($upstreamRoot === '') {
            throw new \InvalidArgumentException('Upstream root must not be empty');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->upstreamRoot = $upstreamRoot;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $root = $this->absoluteUpstreamRoot();
        $generatedCsvNativeParityEvidence = self::generatedCsvNativeParityEvidence($this->repoRoot);
        $generatedTsvNativeParityEvidence = self::generatedTsvNativeParityEvidence($this->repoRoot);
        if (!is_dir($root)) {
            return [
                'schemaVersion' => 1,
                'tool' => self::TOOL_NAME,
                'status' => self::STATUS_SKIPPED_MISSING_SOURCE,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'root' => $this->displayPath($root),
                    'commit' => null,
                    'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                ],
                'denominator' => $this->emptyDenominator(),
                'sourceInventory' => $this->emptySourceInventory(),
                'staticCurrentEvidence' => self::checkedInCurrentEvidence($this->repoRoot),
                'generatedCsvNativeParityEvidence' => $generatedCsvNativeParityEvidence,
                'generatedTsvNativeParityEvidence' => $generatedTsvNativeParityEvidence,
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $upstreamFixtures = $this->upstreamFixtureEvidence($root);
        $sourceInventory = $this->sourceInventory($root);
        $validationIssues = $this->validationIssues($upstreamFixtures, $sourceInventory, $generatedCsvNativeParityEvidence, $generatedTsvNativeParityEvidence);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $this->gitHead($root),
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'fixtureDirectory' => 'test/command',
                'readerSources' => self::SOURCE_FILES,
            ],
            'denominator' => [
                'csvDirectFixtureCount' => count(self::CHECKED_IN_CURRENT_CSV_FIXTURES),
                'tsvDirectFixtureCount' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
                'fixtureScope' => 'direct CSV command reader fixtures in test/command',
                'csvDirectFixtures' => array_values(array_map(
                    static fn (array $fixture): string => (string) $fixture['upstreamPath'],
                    self::CHECKED_IN_CURRENT_CSV_FIXTURES
                )),
                'tsvDirectFixtures' => [],
                'csvAdjacentRstFixtureCount' => self::EXPECTED_STATIC_CSV_ADJACENT_RST_FIXTURE_COUNT,
                'csvAdjacentRstFixtures' => self::csvAdjacentRstFixturePaths(),
                'adjacentFixtureDenominatorImpact' => 0,
                'adjacentFixtureEvidence' => self::csvAdjacentRstFixtureEvidence(),
                'upstreamFixtures' => $upstreamFixtures,
                'parserOptionFixtureCount' => 5,
                'parserOptionFixtures' => [
                    'comma-delimiter-no-header',
                    'space-delimiter-single-quote',
                    'backslash-escaped-quote',
                    'keep-space-after-delimiter',
                    'semicolon-delimiter-multiline-cell',
                ],
            ],
            'sourceInventory' => $sourceInventory,
            'staticCurrentEvidence' => self::checkedInCurrentEvidence($this->repoRoot),
            'generatedCsvNativeParityEvidence' => $generatedCsvNativeParityEvidence,
            'generatedTsvNativeParityEvidence' => $generatedTsvNativeParityEvidence,
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-delimited-text-reader-evidence' : 'invalid-upstream-delimited-text-reader-evidence',
                'issues' => $validationIssues,
            ],
            'claim' => self::claim(),
            'claimBoundaries' => self::claimBoundaries(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInCurrentEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY);
        $generatedCsvNativeStaticEvidence = self::checkedInGeneratedCsvNativeEvidence($root);
        $generatedTsvNativeStaticEvidence = self::checkedInGeneratedTsvNativeEvidence($root);
        $adjacentFixtureEvidence = self::csvAdjacentRstFixtureEvidence();
        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-current-fixture-directory';
        }

        $fixtures = [];
        foreach (self::CHECKED_IN_CURRENT_CSV_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'upstreamPath' => (string) $snapshot['upstreamPath'],
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-csv-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-current-csv-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-current-csv-fixture-byte-count-mismatch';
            }
        }

        if (!self::hasRequiredGeneratedCsvNativeStaticEvidence($generatedCsvNativeStaticEvidence)) {
            $issues[] = 'invalid-checked-in-generated-csv-native-fixture-evidence';
        }

        if (!self::hasRequiredGeneratedTsvNativeStaticEvidence($generatedTsvNativeStaticEvidence)) {
            $issues[] = 'invalid-checked-in-generated-tsv-native-fixture-evidence';
        }

        if (!self::hasRequiredCsvAdjacentRstFixtureEvidence($adjacentFixtureEvidence)) {
            $issues[] = 'invalid-csv-adjacent-rst-fixture-evidence';
        }

        return [
            'kind' => 'static-checked-in-current-upstream-delimited-text-reader-fixture-evidence',
            'upstream' => [
                'name' => 'jgm/pandoc',
                'commit' => self::EXPECTED_UPSTREAM_COMMIT,
                'fixtureDirectory' => 'test/command',
                'readerSources' => self::SOURCE_FILES,
            ],
            'readerDenominator' => [
                'csvDirectFixtureCount' => count(self::CHECKED_IN_CURRENT_CSV_FIXTURES),
                'tsvDirectFixtureCount' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
                'fixtureScope' => 'direct CSV command reader fixtures in test/command',
                'csvDirectFixtures' => array_values(array_map(
                    static fn (array $fixture): string => (string) $fixture['upstreamPath'],
                    self::CHECKED_IN_CURRENT_CSV_FIXTURES
                )),
                'tsvDirectFixtures' => [],
                'csvAdjacentRstFixtureCount' => self::EXPECTED_STATIC_CSV_ADJACENT_RST_FIXTURE_COUNT,
                'csvAdjacentRstFixtures' => self::csvAdjacentRstFixturePaths(),
                'adjacentFixtureDenominatorImpact' => 0,
            ],
            'checkedInFixtureDirectory' => self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'adjacentFixtureEvidence' => $adjacentFixtureEvidence,
            'generatedCsvNativeStaticEvidence' => $generatedCsvNativeStaticEvidence,
            'generatedTsvNativeStaticEvidence' => $generatedTsvNativeStaticEvidence,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-delimited-text-reader-evidence' : 'invalid-checked-in-current-delimited-text-reader-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding Pandoc current CSV command-reader fixtures and generated CSV/TSV native sample fixtures to checked-in SHA-256 and byte-count snapshots.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the checked-in csv.md and 01.csv snapshots match the pinned upstream command fixture hashes',
                    'the upstream command corpus has two CSV direct-reader fixtures tracked by this PHP reader',
                    'the RST csv-table fixture pair is tracked as CSV-adjacent evidence with zero direct-reader denominator impact',
                    'the generated CSV-to-native parity fixture pairs are present as local evidence and are not counted as extra upstream CSV direct fixtures',
                    'there is no dedicated upstream TSV command fixture in this pinned corpus',
                    'the generated TSV-to-native parity fixture pairs are present as local evidence and are not counted as upstream TSV direct fixtures',
                ],
                'doesNotAssert' => [
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'that RST csv-table integration is implemented locally',
                    'full CSV/TSV feature parity beyond the direct reader fixtures and local parser-option cases',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInGeneratedCsvNativeEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY);
        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-generated-csv-native-fixture-directory';
        }

        $fixtures = [];
        foreach (self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'sample' => (string) $snapshot['sample'],
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-generated-csv-native-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-generated-csv-native-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-generated-csv-native-fixture-byte-count-mismatch';
            }
        }

        $samples = [];
        foreach (self::GENERATED_CSV_NATIVE_SAMPLES as $name => $sample) {
            $samples[] = [
                'name' => (string) $name,
                'inputPath' => (string) $sample['inputPath'],
                'expectedNativePath' => (string) $sample['expectedNativePath'],
                'readerOptions' => is_array($sample['options'] ?? null) ? $sample['options'] : [],
            ];
        }

        return [
            'kind' => 'static-checked-in-generated-csv-native-parity-fixture-evidence',
            'evidenceKind' => 'generated-csv-native-parity-fixtures',
            'checkedInFixtureDirectory' => self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY,
            'sampleCount' => count($samples),
            'samples' => $samples,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'csvDirectFixtureDenominator' => self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-generated-csv-native-parity-evidence' : 'invalid-checked-in-generated-csv-native-parity-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static local CSV-to-native fixture evidence; these fixture pairs are generated evidence and are not additional upstream CSV direct-reader fixtures.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the generated CSV input and expected native output fixture files match checked-in SHA-256 and byte-count snapshots',
                    'the generated CSV native sample count is separate from the upstream CSV direct fixture denominator',
                ],
                'doesNotAssert' => [
                    'that upstream adds dedicated CSV command fixtures for these generated samples',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full CSV/TSV feature parity beyond these generated CSV-to-native samples',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function csvAdjacentRstFixtureEvidence(): array
    {
        return [
            'kind' => 'csv-adjacent-rst-csv-table-fixture-evidence',
            'relationship' => 'adjacent-rst-reader-fixtures-not-direct-delimited-text',
            'fixtureScope' => 'RST reader csv-table integration fixture pair in test/command',
            'reader' => 'rst',
            'directive' => 'csv-table',
            'fixtureCount' => count(self::CSV_ADJACENT_RST_FIXTURES),
            'fixtures' => self::CSV_ADJACENT_RST_FIXTURES,
            'csvDirectFixtureDenominatorImpact' => 0,
            'tsvDirectFixtureDenominatorImpact' => 0,
            'claim' => 'Tracks the RST csv-table fixture pair as adjacent context only; these files do not increase the direct CSV or TSV reader denominator.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the RST csv-table fixture pair is not counted as direct CSV or TSV reader fixtures',
                    'the adjacent fixture pair has zero direct-reader denominator impact',
                ],
                'doesNotAssert' => [
                    'that native PHP implements RST csv-table integration',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'that these adjacent fixtures close direct CSV or TSV reader parity',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function csvAdjacentRstFixturePaths(): array
    {
        return array_values(array_map(
            static fn (array $fixture): string => (string) $fixture['path'],
            self::CSV_ADJACENT_RST_FIXTURES
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInGeneratedTsvNativeEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY);
        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-generated-tsv-native-fixture-directory';
        }

        $fixtures = [];
        foreach (self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'sample' => (string) $snapshot['sample'],
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-generated-tsv-native-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-generated-tsv-native-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-generated-tsv-native-fixture-byte-count-mismatch';
            }
        }

        $samples = [];
        foreach (self::GENERATED_TSV_NATIVE_SAMPLES as $name => $sample) {
            $samples[] = [
                'name' => (string) $name,
                'inputPath' => (string) $sample['inputPath'],
                'expectedNativePath' => (string) $sample['expectedNativePath'],
                'readerOptions' => is_array($sample['options'] ?? null) ? $sample['options'] : [],
            ];
        }

        return [
            'kind' => 'static-checked-in-generated-tsv-native-parity-fixture-evidence',
            'evidenceKind' => 'generated-tsv-native-parity-fixtures',
            'checkedInFixtureDirectory' => self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY,
            'sampleCount' => count($samples),
            'samples' => $samples,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'tsvDirectFixtureDenominator' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-generated-tsv-native-parity-evidence' : 'invalid-checked-in-generated-tsv-native-parity-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static local TSV-to-native fixture evidence; these fixture pairs are generated evidence and are not upstream TSV direct-reader fixtures.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the generated TSV input and expected native output fixture files match checked-in SHA-256 and byte-count snapshots',
                    'the generated TSV native sample count is separate from the upstream TSV direct fixture denominator',
                ],
                'doesNotAssert' => [
                    'that upstream provides dedicated TSV command fixtures',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full CSV/TSV feature parity beyond these generated TSV-to-native samples',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function generatedCsvNativeParityEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $staticEvidence = self::checkedInGeneratedCsvNativeEvidence($root);
        $sampleResults = [];
        $parseFailures = [];
        $mismatches = [];
        $matchCount = 0;
        $comparedCount = 0;

        foreach (self::GENERATED_CSV_NATIVE_SAMPLES as $name => $sample) {
            $inputPath = (string) $sample['inputPath'];
            $expectedNativePath = (string) $sample['expectedNativePath'];
            $readerOptions = is_array($sample['options'] ?? null) ? $sample['options'] : [];
            $readerOptions['sourcePath'] = $inputPath;
            $staticFixtureBinding = self::generatedNativeSampleStaticBinding($staticEvidence, 'csv', (string) $name);
            $absoluteInputPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $inputPath);
            $absoluteExpectedNativePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedNativePath);
            $input = is_file($absoluteInputPath) ? file_get_contents($absoluteInputPath) : false;
            $expectedNative = is_file($absoluteExpectedNativePath) ? file_get_contents($absoluteExpectedNativePath) : false;

            if (!is_string($input) || !is_string($expectedNative)) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'readerOptions' => $readerOptions,
                    'inputError' => is_string($input) ? null : 'missing-or-unreadable-csv-input-fixture',
                    'expectedNativeError' => is_string($expectedNative) ? null : 'missing-or-unreadable-native-fixture',
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    'staticFixtureBindingStatus' => $staticFixtureBinding['status'],
                    'staticFixtureBinding' => $staticFixtureBinding,
                    ...$failure,
                ];
                continue;
            }

            try {
                $document = (new DelimitedTextReader())->readCsv($input, $readerOptions);
                $generatedNative = PandocConverter::write($document, 'native');
            } catch (\Throwable $throwable) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'readerOptions' => $readerOptions,
                    'inputError' => $throwable::class . ': ' . $throwable->getMessage(),
                    'expectedNativeError' => null,
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    'staticFixtureBindingStatus' => $staticFixtureBinding['status'],
                    'staticFixtureBinding' => $staticFixtureBinding,
                    ...$failure,
                ];
                continue;
            }

            ++$comparedCount;
            $expectedTokens = self::nativeTokenStream($expectedNative);
            $generatedTokens = self::nativeTokenStream($generatedNative);
            $matched = $expectedTokens === $generatedTokens;
            if ($matched) {
                ++$matchCount;
            } else {
                $mismatches[] = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'firstDifference' => self::firstStringDifference($expectedTokens, $generatedTokens) ?? 'unknown-native-token-difference',
                ];
            }

            $table = $document->children[0] ?? null;
            $packet = $table instanceof AstNode ? $table->attr('delimitedText', []) : [];
            $sampleResults[] = [
                'name' => (string) $name,
                'status' => $matched ? 'matched' : 'mismatched',
                'inputPath' => $inputPath,
                'expectedNativePath' => $expectedNativePath,
                'readerOptions' => $readerOptions,
                'staticFixtureBindingStatus' => $staticFixtureBinding['status'],
                'staticFixtureBinding' => $staticFixtureBinding,
                'reader' => 'csv',
                'expectedNativeSha256' => hash('sha256', $expectedNative),
                'generatedNativeSha256' => hash('sha256', $generatedNative),
                'expectedNativeTokenSha256' => hash('sha256', $expectedTokens),
                'generatedNativeTokenSha256' => hash('sha256', $generatedTokens),
                'rowCount' => is_array($packet) ? ($packet['rowCount'] ?? null) : null,
                'columnCount' => is_array($packet) ? ($packet['columnCount'] ?? null) : null,
                'csvDirectFixtureDenominator' => self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT,
            ];
        }

        $sampleCount = count(self::GENERATED_CSV_NATIVE_SAMPLES);
        $mismatchCount = $comparedCount - $matchCount;
        $staticEvidenceValid = self::hasRequiredGeneratedCsvNativeStaticEvidence($staticEvidence);
        $validStaticFixtureBindingCount = self::validGeneratedNativeSampleStaticBindingCount($sampleResults, 'csv');
        $invalidStaticFixtureBindingCount = $sampleCount - $validStaticFixtureBindingCount;

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'kind' => 'executable-generated-csv-native-parity-evidence',
            'evidenceKind' => 'generated-csv-native-parity',
            'status' => 'completed-generated-csv-native-parity-evidence',
            'claim' => 'Executes the local PHP CSV reader and native writer for checked-in generated CSV samples; these are not extra upstream CSV direct fixtures or upstream runner results.',
            'fixtureDirectory' => self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURE_DIRECTORY,
            'reader' => 'csv',
            'csvDirectFixtureDenominator' => self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT,
            'sampleCount' => $sampleCount,
            'comparedSampleCount' => $comparedCount,
            'parseFailureCount' => count($parseFailures),
            'generatedNativeMatchCount' => $matchCount,
            'generatedNativeMismatchCount' => $mismatchCount,
            'generatedNativeMatchPercent' => self::percent($matchCount, $sampleCount),
            'staticFixtureBindingValidCount' => $validStaticFixtureBindingCount,
            'staticFixtureBindingInvalidCount' => $invalidStaticFixtureBindingCount,
            'parityStatus' => self::generatedCsvNativeParityStatus($staticEvidenceValid && $invalidStaticFixtureBindingCount === 0, count($parseFailures), $mismatchCount, $comparedCount, $sampleCount),
            'staticFixtureEvidence' => $staticEvidence,
            'samples' => $sampleResults,
            'parseFailures' => $parseFailures,
            'mismatches' => $mismatches,
            'claimBoundaries' => [
                'doesAssert' => [
                    'the local CSV reader can read the checked-in generated CSV samples',
                    'the generated native output matches the checked-in expected native fixtures by normalized native token stream',
                    'each executable generated CSV sample is bound to valid checked-in input and native snapshot evidence',
                    'the upstream CSV direct fixture denominator remains two',
                ],
                'doesNotAssert' => [
                    'that the generated CSV samples are upstream command fixtures',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full CSV/TSV feature parity beyond these generated CSV-to-native samples',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function generatedTsvNativeParityEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $staticEvidence = self::checkedInGeneratedTsvNativeEvidence($root);
        $sampleResults = [];
        $parseFailures = [];
        $mismatches = [];
        $matchCount = 0;
        $comparedCount = 0;

        foreach (self::GENERATED_TSV_NATIVE_SAMPLES as $name => $sample) {
            $inputPath = (string) $sample['inputPath'];
            $expectedNativePath = (string) $sample['expectedNativePath'];
            $readerOptions = is_array($sample['options'] ?? null) ? $sample['options'] : [];
            $readerOptions['sourcePath'] = $inputPath;
            $staticFixtureBinding = self::generatedNativeSampleStaticBinding($staticEvidence, 'tsv', (string) $name);
            $absoluteInputPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $inputPath);
            $absoluteExpectedNativePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $expectedNativePath);
            $input = is_file($absoluteInputPath) ? file_get_contents($absoluteInputPath) : false;
            $expectedNative = is_file($absoluteExpectedNativePath) ? file_get_contents($absoluteExpectedNativePath) : false;

            if (!is_string($input) || !is_string($expectedNative)) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'readerOptions' => $readerOptions,
                    'inputError' => is_string($input) ? null : 'missing-or-unreadable-tsv-input-fixture',
                    'expectedNativeError' => is_string($expectedNative) ? null : 'missing-or-unreadable-native-fixture',
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    'staticFixtureBindingStatus' => $staticFixtureBinding['status'],
                    'staticFixtureBinding' => $staticFixtureBinding,
                    ...$failure,
                ];
                continue;
            }

            try {
                $document = (new DelimitedTextReader())->readTsv($input, $readerOptions);
                $generatedNative = PandocConverter::write($document, 'native');
            } catch (\Throwable $throwable) {
                $failure = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'readerOptions' => $readerOptions,
                    'inputError' => $throwable::class . ': ' . $throwable->getMessage(),
                    'expectedNativeError' => null,
                ];
                $parseFailures[] = $failure;
                $sampleResults[] = [
                    'name' => (string) $name,
                    'status' => 'parse-failed',
                    'staticFixtureBindingStatus' => $staticFixtureBinding['status'],
                    'staticFixtureBinding' => $staticFixtureBinding,
                    ...$failure,
                ];
                continue;
            }

            ++$comparedCount;
            $expectedTokens = self::nativeTokenStream($expectedNative);
            $generatedTokens = self::nativeTokenStream($generatedNative);
            $matched = $expectedTokens === $generatedTokens;
            if ($matched) {
                ++$matchCount;
            } else {
                $mismatches[] = [
                    'sample' => (string) $name,
                    'inputPath' => $inputPath,
                    'expectedNativePath' => $expectedNativePath,
                    'firstDifference' => self::firstStringDifference($expectedTokens, $generatedTokens) ?? 'unknown-native-token-difference',
                ];
            }

            $table = $document->children[0] ?? null;
            $packet = $table instanceof AstNode ? $table->attr('delimitedText', []) : [];
            $sampleResults[] = [
                'name' => (string) $name,
                'status' => $matched ? 'matched' : 'mismatched',
                'inputPath' => $inputPath,
                'expectedNativePath' => $expectedNativePath,
                'readerOptions' => $readerOptions,
                'staticFixtureBindingStatus' => $staticFixtureBinding['status'],
                'staticFixtureBinding' => $staticFixtureBinding,
                'reader' => 'tsv',
                'expectedNativeSha256' => hash('sha256', $expectedNative),
                'generatedNativeSha256' => hash('sha256', $generatedNative),
                'expectedNativeTokenSha256' => hash('sha256', $expectedTokens),
                'generatedNativeTokenSha256' => hash('sha256', $generatedTokens),
                'rowCount' => is_array($packet) ? ($packet['rowCount'] ?? null) : null,
                'columnCount' => is_array($packet) ? ($packet['columnCount'] ?? null) : null,
                'tsvDirectFixtureDenominator' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
            ];
        }

        $sampleCount = count(self::GENERATED_TSV_NATIVE_SAMPLES);
        $mismatchCount = $comparedCount - $matchCount;
        $staticEvidenceValid = self::hasRequiredGeneratedTsvNativeStaticEvidence($staticEvidence);
        $validStaticFixtureBindingCount = self::validGeneratedNativeSampleStaticBindingCount($sampleResults, 'tsv');
        $invalidStaticFixtureBindingCount = $sampleCount - $validStaticFixtureBindingCount;

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'kind' => 'executable-generated-tsv-native-parity-evidence',
            'evidenceKind' => 'generated-tsv-native-parity',
            'status' => 'completed-generated-tsv-native-parity-evidence',
            'claim' => 'Executes the local PHP TSV reader and native writer for checked-in generated TSV samples; these are not upstream TSV direct fixtures or upstream runner results.',
            'fixtureDirectory' => self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURE_DIRECTORY,
            'reader' => 'tsv',
            'tsvDirectFixtureDenominator' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
            'sampleCount' => $sampleCount,
            'comparedSampleCount' => $comparedCount,
            'parseFailureCount' => count($parseFailures),
            'generatedNativeMatchCount' => $matchCount,
            'generatedNativeMismatchCount' => $mismatchCount,
            'generatedNativeMatchPercent' => self::percent($matchCount, $sampleCount),
            'staticFixtureBindingValidCount' => $validStaticFixtureBindingCount,
            'staticFixtureBindingInvalidCount' => $invalidStaticFixtureBindingCount,
            'parityStatus' => self::generatedTsvNativeParityStatus($staticEvidenceValid && $invalidStaticFixtureBindingCount === 0, count($parseFailures), $mismatchCount, $comparedCount, $sampleCount),
            'staticFixtureEvidence' => $staticEvidence,
            'samples' => $sampleResults,
            'parseFailures' => $parseFailures,
            'mismatches' => $mismatches,
            'claimBoundaries' => [
                'doesAssert' => [
                    'the local TSV reader can read the checked-in generated TSV samples',
                    'the generated native output matches the checked-in expected native fixtures by normalized native token stream',
                    'each executable generated TSV sample is bound to valid checked-in input and native snapshot evidence',
                    'the upstream TSV direct fixture denominator remains zero',
                ],
                'doesNotAssert' => [
                    'that the generated TSV samples are upstream command fixtures',
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'full CSV/TSV feature parity beyond these generated TSV-to-native samples',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $upstream = is_array($report['upstream'] ?? null) ? $report['upstream'] : [];
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $staticValidation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];
        $generatedCsvNative = is_array($report['generatedCsvNativeParityEvidence'] ?? null) ? $report['generatedCsvNativeParityEvidence'] : [];
        $generatedTsvNative = is_array($report['generatedTsvNativeParityEvidence'] ?? null) ? $report['generatedTsvNativeParityEvidence'] : [];

        return implode(PHP_EOL, [
            'Pandoc delimited text reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Upstream: ' . (string) ($upstream['commit'] ?? 'unknown')
                . ' expected=' . (string) ($upstream['expectedCommit'] ?? self::EXPECTED_UPSTREAM_COMMIT),
            'CSV direct fixtures: ' . (int) ($denominator['csvDirectFixtureCount'] ?? 0),
            'TSV direct fixtures: ' . (int) ($denominator['tsvDirectFixtureCount'] ?? 0),
            'Static current evidence: ' . (string) ($staticValidation['status'] ?? 'unknown')
                . ' checkedInFixtures=' . (int) ($staticEvidence['checkedInFixtureCount'] ?? 0),
            'Generated CSV native parity: ' . (int) ($generatedCsvNative['generatedNativeMatchCount'] ?? 0)
                . '/' . (int) ($generatedCsvNative['sampleCount'] ?? 0)
                . ' status=' . (string) ($generatedCsvNative['parityStatus'] ?? 'unknown'),
            'Generated TSV native parity: ' . (int) ($generatedTsvNative['generatedNativeMatchCount'] ?? 0)
                . '/' . (int) ($generatedTsvNative['sampleCount'] ?? 0)
                . ' status=' . (string) ($generatedTsvNative['parityStatus'] ?? 'unknown'),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            'No upstream Haskell/Cabal runner result, RST csv-table integration, or full CSV/TSV feature parity is asserted.',
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticCurrentEvidence(array $report): bool
    {
        $evidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];
        $denominator = is_array($evidence['readerDenominator'] ?? null) ? $evidence['readerDenominator'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-current-delimited-text-reader-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($denominator['csvDirectFixtureCount'] ?? -1) === self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT
            && (int) ($denominator['tsvDirectFixtureCount'] ?? -1) === self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT
            && self::hasRequiredGeneratedCsvNativeStaticEvidence(
                is_array($evidence['generatedCsvNativeStaticEvidence'] ?? null) ? $evidence['generatedCsvNativeStaticEvidence'] : []
            )
            && self::hasRequiredGeneratedTsvNativeStaticEvidence(
                is_array($evidence['generatedTsvNativeStaticEvidence'] ?? null) ? $evidence['generatedTsvNativeStaticEvidence'] : []
            )
            && self::hasRequiredCsvAdjacentRstFixtureEvidence(
                is_array($evidence['adjacentFixtureEvidence'] ?? null) ? $evidence['adjacentFixtureEvidence'] : []
            );
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-delimited-text-reader-evidence'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedCsvNativeStaticEvidence(array $evidence): bool
    {
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-generated-csv-native-parity-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($evidence['sampleCount'] ?? -1) === self::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT
            && (int) ($evidence['checkedInFixtureCount'] ?? -1) === count(self::CHECKED_IN_GENERATED_CSV_NATIVE_FIXTURES)
            && (int) ($evidence['csvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT
            && self::hasRequiredGeneratedNativeStaticFixtureBindings($evidence, 'csv', array_keys(self::GENERATED_CSV_NATIVE_SAMPLES));
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedTsvNativeStaticEvidence(array $evidence): bool
    {
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-generated-tsv-native-parity-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($evidence['sampleCount'] ?? -1) === self::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT
            && (int) ($evidence['checkedInFixtureCount'] ?? -1) === count(self::CHECKED_IN_GENERATED_TSV_NATIVE_FIXTURES)
            && (int) ($evidence['tsvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT
            && self::hasRequiredGeneratedNativeStaticFixtureBindings($evidence, 'tsv', array_keys(self::GENERATED_TSV_NATIVE_SAMPLES));
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredCsvAdjacentRstFixtureEvidence(array $evidence): bool
    {
        $fixtures = is_array($evidence['fixtures'] ?? null) ? $evidence['fixtures'] : [];

        return ($evidence['kind'] ?? null) === 'csv-adjacent-rst-csv-table-fixture-evidence'
            && ($evidence['relationship'] ?? null) === 'adjacent-rst-reader-fixtures-not-direct-delimited-text'
            && ($evidence['reader'] ?? null) === 'rst'
            && ($evidence['directive'] ?? null) === 'csv-table'
            && (int) ($evidence['fixtureCount'] ?? -1) === self::EXPECTED_STATIC_CSV_ADJACENT_RST_FIXTURE_COUNT
            && array_column($fixtures, 'path') === self::csvAdjacentRstFixturePaths()
            && array_column($fixtures, 'directDelimitedTextReaderFixture') === [false, false]
            && (int) ($evidence['csvDirectFixtureDenominatorImpact'] ?? -1) === 0
            && (int) ($evidence['tsvDirectFixtureDenominatorImpact'] ?? -1) === 0;
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedCsvNativeParity(array $evidence, int $requiredSampleCount = self::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT): bool
    {
        if ($requiredSampleCount < 0) {
            throw new \InvalidArgumentException('Required generated CSV native sample count must not be negative');
        }

        $staticEvidence = is_array($evidence['staticFixtureEvidence'] ?? null) ? $evidence['staticFixtureEvidence'] : [];

        return ($evidence['status'] ?? null) === 'completed-generated-csv-native-parity-evidence'
            && (int) ($evidence['csvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT
            && (int) ($evidence['sampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['comparedSampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['parseFailureCount'] ?? -1) === 0
            && (int) ($evidence['generatedNativeMatchCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['generatedNativeMismatchCount'] ?? -1) === 0
            && (int) ($evidence['staticFixtureBindingValidCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['staticFixtureBindingInvalidCount'] ?? -1) === 0
            && ($evidence['parityStatus'] ?? null) === 'generated-csv-native-parity-observed-not-upstream-fixture'
            && self::hasRequiredGeneratedCsvNativeStaticEvidence($staticEvidence)
            && self::hasRequiredGeneratedNativeSampleStaticBindings($evidence, 'csv', $requiredSampleCount);
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public static function hasRequiredGeneratedTsvNativeParity(array $evidence, int $requiredSampleCount = self::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT): bool
    {
        if ($requiredSampleCount < 0) {
            throw new \InvalidArgumentException('Required generated TSV native sample count must not be negative');
        }

        $staticEvidence = is_array($evidence['staticFixtureEvidence'] ?? null) ? $evidence['staticFixtureEvidence'] : [];

        return ($evidence['status'] ?? null) === 'completed-generated-tsv-native-parity-evidence'
            && (int) ($evidence['tsvDirectFixtureDenominator'] ?? -1) === self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT
            && (int) ($evidence['sampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['comparedSampleCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['parseFailureCount'] ?? -1) === 0
            && (int) ($evidence['generatedNativeMatchCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['generatedNativeMismatchCount'] ?? -1) === 0
            && (int) ($evidence['staticFixtureBindingValidCount'] ?? -1) === $requiredSampleCount
            && (int) ($evidence['staticFixtureBindingInvalidCount'] ?? -1) === 0
            && ($evidence['parityStatus'] ?? null) === 'generated-tsv-native-parity-observed-not-upstream-fixture'
            && self::hasRequiredGeneratedTsvNativeStaticEvidence($staticEvidence)
            && self::hasRequiredGeneratedNativeSampleStaticBindings($evidence, 'tsv', $requiredSampleCount);
    }

    /**
     * @param array<string, mixed> $staticEvidence
     * @param list<string> $sampleNames
     */
    private static function hasRequiredGeneratedNativeStaticFixtureBindings(array $staticEvidence, string $reader, array $sampleNames): bool
    {
        foreach ($sampleNames as $sampleName) {
            $binding = self::generatedNativeSampleStaticBinding($staticEvidence, $reader, $sampleName);
            if (($binding['status'] ?? null) !== self::validGeneratedNativeSampleStaticBindingStatus($reader)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $evidence
     */
    private static function hasRequiredGeneratedNativeSampleStaticBindings(array $evidence, string $reader, int $requiredSampleCount): bool
    {
        $samples = is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [];
        if (count($samples) !== $requiredSampleCount) {
            return false;
        }

        $validStatus = self::validGeneratedNativeSampleStaticBindingStatus($reader);
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                return false;
            }
            $binding = is_array($sample['staticFixtureBinding'] ?? null) ? $sample['staticFixtureBinding'] : [];
            if (($sample['staticFixtureBindingStatus'] ?? null) !== $validStatus) {
                return false;
            }
            if (($binding['status'] ?? null) !== $validStatus) {
                return false;
            }
            if (($binding['reader'] ?? null) !== $reader) {
                return false;
            }
            if (($binding['sample'] ?? null) !== ($sample['name'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, mixed> $samples
     */
    private static function validGeneratedNativeSampleStaticBindingCount(array $samples, string $reader): int
    {
        $validStatus = self::validGeneratedNativeSampleStaticBindingStatus($reader);
        $count = 0;
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                continue;
            }
            $binding = is_array($sample['staticFixtureBinding'] ?? null) ? $sample['staticFixtureBinding'] : [];
            if (($sample['staticFixtureBindingStatus'] ?? null) !== $validStatus) {
                continue;
            }
            if (($binding['status'] ?? null) !== $validStatus) {
                continue;
            }
            if (($binding['reader'] ?? null) !== $reader) {
                continue;
            }
            if (($binding['sample'] ?? null) !== ($sample['name'] ?? null)) {
                continue;
            }

            ++$count;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $staticEvidence
     * @return array<string, mixed>
     */
    private static function generatedNativeSampleStaticBinding(array $staticEvidence, string $reader, string $sampleName): array
    {
        if ($reader !== 'csv' && $reader !== 'tsv') {
            throw new \InvalidArgumentException("Unsupported generated native sample reader: {$reader}");
        }

        $fixtures = is_array($staticEvidence['checkedInFixtures'] ?? null) ? $staticEvidence['checkedInFixtures'] : [];
        $inputRole = "generated-{$reader}-native-parity-input-fixture";
        $expectedNativeRole = "generated-{$reader}-native-parity-expected-native-output";
        $inputFixture = null;
        $expectedNativeFixture = null;
        foreach ($fixtures as $fixture) {
            if (!is_array($fixture) || (string) ($fixture['sample'] ?? '') !== $sampleName) {
                continue;
            }
            if (($fixture['role'] ?? null) === $inputRole) {
                $inputFixture = $fixture;
                continue;
            }
            if (($fixture['role'] ?? null) === $expectedNativeRole) {
                $expectedNativeFixture = $fixture;
            }
        }

        $inputSnapshot = self::generatedNativeStaticFixtureSnapshotSummary($inputFixture, $inputRole);
        $expectedNativeSnapshot = self::generatedNativeStaticFixtureSnapshotSummary($expectedNativeFixture, $expectedNativeRole);
        $bindingIsValid = ($inputSnapshot['snapshotMatches'] ?? false) === true
            && ($expectedNativeSnapshot['snapshotMatches'] ?? false) === true;

        return [
            'kind' => "generated-{$reader}-native-sample-static-fixture-binding",
            'reader' => $reader,
            'sample' => $sampleName,
            'status' => $bindingIsValid
                ? self::validGeneratedNativeSampleStaticBindingStatus($reader)
                : self::invalidGeneratedNativeSampleStaticBindingStatus($reader),
            'inputFixture' => $inputSnapshot,
            'expectedNativeFixture' => $expectedNativeSnapshot,
        ];
    }

    /**
     * @param array<string, mixed>|null $fixture
     * @return array<string, mixed>
     */
    private static function generatedNativeStaticFixtureSnapshotSummary(?array $fixture, string $expectedRole): array
    {
        if ($fixture === null) {
            return [
                'name' => null,
                'role' => $expectedRole,
                'checkedInPath' => null,
                'present' => false,
                'sha256MatchesSnapshot' => false,
                'byteCountMatchesSnapshot' => false,
                'snapshotMatches' => false,
                'status' => 'missing-static-fixture-snapshot',
            ];
        }

        $file = is_array($fixture['checkedInFile'] ?? null) ? $fixture['checkedInFile'] : [];
        $present = ($file['present'] ?? false) === true;
        $sha256 = is_string($file['sha256'] ?? null) ? $file['sha256'] : null;
        $expectedSha256 = is_string($file['expectedSha256'] ?? null) ? $file['expectedSha256'] : null;
        $bytes = is_int($file['bytes'] ?? null) ? $file['bytes'] : null;
        $expectedBytes = is_int($file['expectedBytes'] ?? null) ? $file['expectedBytes'] : null;
        $sha256MatchesSnapshot = $sha256 !== null && $expectedSha256 !== null && $sha256 === $expectedSha256;
        $byteCountMatchesSnapshot = $bytes !== null && $expectedBytes !== null && $bytes === $expectedBytes;
        $snapshotMatches = $present && $sha256MatchesSnapshot && $byteCountMatchesSnapshot;

        return [
            'name' => (string) ($fixture['name'] ?? ''),
            'role' => (string) ($fixture['role'] ?? $expectedRole),
            'checkedInPath' => (string) ($file['path'] ?? ''),
            'present' => $present,
            'sha256' => $sha256,
            'expectedSha256' => $expectedSha256,
            'sha256MatchesSnapshot' => $sha256MatchesSnapshot,
            'bytes' => $bytes,
            'expectedBytes' => $expectedBytes,
            'byteCountMatchesSnapshot' => $byteCountMatchesSnapshot,
            'snapshotMatches' => $snapshotMatches,
            'status' => $snapshotMatches ? 'valid-static-fixture-snapshot' : 'invalid-static-fixture-snapshot',
        ];
    }

    private static function validGeneratedNativeSampleStaticBindingStatus(string $reader): string
    {
        return "valid-generated-{$reader}-native-sample-static-binding";
    }

    private static function invalidGeneratedNativeSampleStaticBindingStatus(string $reader): string
    {
        return "invalid-generated-{$reader}-native-sample-static-binding";
    }

    private static function claim(): string
    {
        return 'Tracks the current upstream direct CSV command-reader fixtures, the adjacent RST csv-table fixture pair with zero direct-reader denominator impact, twenty generated CSV-to-native evidence samples, the absence of dedicated TSV command fixtures, and fifteen generated TSV-to-native evidence samples for the delimited text reader.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the count and file identities of upstream direct CSV command-reader fixtures tracked locally',
                'that the current pinned upstream CSV reader source files are present when an upstream checkout is inspected',
                'that the RST csv-table fixture pair is CSV-adjacent evidence and not part of the direct CSV/TSV reader denominator',
                'that no dedicated TSV command fixture is available in the pinned direct-reader evidence set',
                'static checked-in current csv.md and 01.csv fixture identity when staticCurrentEvidence is valid',
                'twenty generated CSV-to-native local samples when generatedCsvNativeParityEvidence is valid',
                'fifteen generated TSV-to-native local samples when generatedTsvNativeParityEvidence is valid',
            ],
            'doesNotAssert' => [
                'that upstream Haskell/Cabal/Tasty tests were executed',
                'that local PHP output matches every upstream CSV-adjacent command fixture',
                'that the generated CSV samples are upstream command fixtures',
                'that the generated TSV samples are upstream command fixtures',
                'RST csv-table reader integration',
                'full CSV/TSV feature parity beyond the direct reader fixture evidence',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'csvDirectFixtureCount' => 0,
            'tsvDirectFixtureCount' => 0,
            'fixtureScope' => 'direct CSV command reader fixtures in test/command',
            'csvDirectFixtures' => [],
            'tsvDirectFixtures' => [],
            'upstreamFixtures' => [],
            'parserOptionFixtureCount' => 0,
            'parserOptionFixtures' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySourceInventory(): array
    {
        return [
            'files' => [],
            'presentFileCount' => 0,
            'missingFileCount' => 0,
            'presentLineCount' => 0,
        ];
    }

    /**
     * @return list<array{name: string, role: string, path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}>
     */
    private function upstreamFixtureEvidence(string $root): array
    {
        $fixtures = [];
        foreach (self::CHECKED_IN_CURRENT_CSV_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['upstreamPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                ...$file,
            ];
        }

        return $fixtures;
    }

    /**
     * @return array{files: list<array{path: string, present: bool, bytes: ?int, lineCount: ?int}>, presentFileCount: int, missingFileCount: int, presentLineCount: int}
     */
    private function sourceInventory(string $root): array
    {
        $files = [];
        foreach (self::SOURCE_FILES as $path) {
            $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $present = is_file($absolute);
            $contents = $present ? file_get_contents($absolute) : false;
            $bytes = $present ? filesize($absolute) : false;
            $files[] = [
                'path' => $path,
                'present' => $present,
                'bytes' => is_int($bytes) ? $bytes : null,
                'lineCount' => is_string($contents) ? substr_count($contents, "\n") + ($contents === '' || str_ends_with($contents, "\n") ? 0 : 1) : null,
            ];
        }

        $present = array_values(array_filter($files, static fn (array $file): bool => ($file['present'] ?? false) === true));

        return [
            'files' => $files,
            'presentFileCount' => count($present),
            'missingFileCount' => count($files) - count($present),
            'presentLineCount' => array_sum(array_map(static fn (array $file): int => (int) ($file['lineCount'] ?? 0), $present)),
        ];
    }

    /**
     * @param list<array{name: string, role: string, path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}> $upstreamFixtures
     * @param array<string, mixed> $sourceInventory
     * @param array<string, mixed> $generatedCsvNativeParityEvidence
     * @param array<string, mixed> $generatedTsvNativeParityEvidence
     * @return list<string>
     */
    private function validationIssues(array $upstreamFixtures, array $sourceInventory, array $generatedCsvNativeParityEvidence, array $generatedTsvNativeParityEvidence): array
    {
        $issues = [];
        foreach ($upstreamFixtures as $fixture) {
            if (($fixture['present'] ?? false) !== true) {
                $issues[] = 'missing-upstream-csv-command-fixture';
            } elseif (($fixture['sha256'] ?? null) !== ($fixture['expectedSha256'] ?? null)) {
                $issues[] = 'upstream-csv-command-fixture-sha256-mismatch';
            } elseif ((int) ($fixture['bytes'] ?? -1) !== (int) ($fixture['expectedBytes'] ?? -2)) {
                $issues[] = 'upstream-csv-command-fixture-byte-count-mismatch';
            }
        }

        if ((int) ($sourceInventory['missingFileCount'] ?? 0) > 0) {
            $issues[] = 'missing-upstream-delimited-text-reader-source';
        }

        if (!self::hasRequiredGeneratedCsvNativeParity($generatedCsvNativeParityEvidence)) {
            $issues[] = 'generated-csv-native-parity-not-observed';
        }

        if (!self::hasRequiredGeneratedTsvNativeParity($generatedTsvNativeParityEvidence)) {
            $issues[] = 'generated-tsv-native-parity-not-observed';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array{path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}
     */
    private static function snapshotFileEvidence(string $root, string $relativePath, string $expectedSha256, int $expectedBytes): array
    {
        $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : false;
        $bytes = $present ? filesize($path) : false;

        return [
            'path' => $relativePath,
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'expectedSha256' => $expectedSha256,
            'bytes' => is_int($bytes) ? $bytes : null,
            'expectedBytes' => $expectedBytes,
        ];
    }

    private static function nativeTokenStream(string $native): string
    {
        $native = (string) preg_replace('/\[\s*\]/', '[]', $native);
        $native = (string) preg_replace('/\(\s*""\s*,\s*\[\]\s*,\s*\[\]\s*\)/', '("",[],[])', $native);

        return (string) preg_replace('/\s+/', ' ', trim($native));
    }

    private static function firstStringDifference(string $expected, string $actual): ?string
    {
        if ($expected === $actual) {
            return null;
        }

        $limit = min(strlen($expected), strlen($actual));
        for ($offset = 0; $offset < $limit; $offset++) {
            if ($expected[$offset] === $actual[$offset]) {
                continue;
            }

            return 'native token byte ' . $offset
                . ' expected=' . bin2hex($expected[$offset])
                . ' actual=' . bin2hex($actual[$offset]);
        }

        return 'native token length expected=' . strlen($expected) . ' actual=' . strlen($actual);
    }

    private static function generatedCsvNativeParityStatus(bool $staticEvidenceValid, int $parseFailureCount, int $mismatchCount, int $comparedCount, int $sampleCount): string
    {
        if (!$staticEvidenceValid) {
            return 'blocked-by-generated-csv-native-fixture-validation';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-generated-csv-native-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'generated-csv-native-mismatches-observed';
        }
        if ($sampleCount > 0 && $comparedCount === $sampleCount) {
            return 'generated-csv-native-parity-observed-not-upstream-fixture';
        }

        return 'not-evaluated-no-generated-csv-native-samples';
    }

    private static function generatedTsvNativeParityStatus(bool $staticEvidenceValid, int $parseFailureCount, int $mismatchCount, int $comparedCount, int $sampleCount): string
    {
        if (!$staticEvidenceValid) {
            return 'blocked-by-generated-tsv-native-fixture-validation';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-generated-tsv-native-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'generated-tsv-native-mismatches-observed';
        }
        if ($sampleCount > 0 && $comparedCount === $sampleCount) {
            return 'generated-tsv-native-parity-observed-not-upstream-fixture';
        }

        return 'not-evaluated-no-generated-tsv-native-samples';
    }

    private static function percent(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private function absoluteUpstreamRoot(): string
    {
        if (str_starts_with($this->upstreamRoot, DIRECTORY_SEPARATOR)) {
            return rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->upstreamRoot);
    }

    private function displayPath(string $path): string
    {
        if (str_starts_with($path, $this->repoRoot . DIRECTORY_SEPARATOR)) {
            return substr($path, strlen($this->repoRoot) + 1);
        }

        return $path;
    }

    private function gitHead(string $root): ?string
    {
        $head = $root . '/.git/HEAD';
        if (!is_file($head)) {
            return null;
        }

        $contents = trim((string) file_get_contents($head));
        if (str_starts_with($contents, 'ref: ')) {
            $refPath = $root . '/.git/' . substr($contents, 5);
            return is_file($refPath) ? trim((string) file_get_contents($refPath)) : null;
        }

        return preg_match('/^[0-9a-f]{40}$/', $contents) === 1 ? $contents : null;
    }
}
