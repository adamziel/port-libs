<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PptxUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-pptx-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-pptx-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-pptx-root';
    public const CHECKED_IN_CURRENT_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/upstream-current-pptx-reader';
    public const EXPECTED_STATIC_READER_TEST_COMPARE_COUNT = 1;
    public const EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT = 25;

    private const STATIC_CURRENT_READER_CASES = [
        [
            'name' => 'text extraction',
            'pptx' => 'pptx-reader/basic.pptx',
            'native' => 'pptx-reader/basic.native',
            'pairKey' => 'pptx-reader/basic.pptx|pptx-reader/basic.native',
        ],
    ];
    private const CHECKED_IN_CURRENT_FIXTURE_SNAPSHOT = [
        'basic' => [
            'name' => 'text extraction',
            'pptx' => 'pptx-reader/basic.pptx',
            'native' => 'pptx-reader/basic.native',
            'pairKey' => 'pptx-reader/basic.pptx|pptx-reader/basic.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/basic.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/basic.native',
            'pptxSha256' => 'e48fd9c2f8369d1792197e301d5fea676bf6e51097a24af7d85831a6f96dc2dc',
            'nativeSha256' => '42804b9b1954094a4b0ff0be20084e2e6d9bc0a84272f34f7f219f82505da6b4',
            'pptxBytes' => 111674,
            'nativeBytes' => 3966,
        ],
        'body-before-title' => [
            'name' => 'generated body-before-title placeholder ordering parity',
            'pptx' => 'pptx-reader/body-before-title.pptx',
            'native' => 'pptx-reader/body-before-title.native',
            'pairKey' => 'pptx-reader/body-before-title.pptx|pptx-reader/body-before-title.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/body-before-title.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/body-before-title.native',
            'pptxSha256' => '0211c524f44cac1d910cb51f9540bf2fa6dd6b497d3c018ff4f06517be6564c1',
            'nativeSha256' => 'e0b1dacb8bd85677d2556009e0a79e4443680cf98e45e26c4a3a0747800d7453',
            'pptxBytes' => 1519,
            'nativeBytes' => 132,
        ],
        'minimal' => [
            'name' => 'generated minimal text extraction parity',
            'pptx' => 'pptx-reader/minimal.pptx',
            'native' => 'pptx-reader/minimal.native',
            'pairKey' => 'pptx-reader/minimal.pptx|pptx-reader/minimal.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/minimal.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/minimal.native',
            'pptxSha256' => 'f4852d7b0455ae99a8ef2b3d419cb2aa9ab2f8b5c4167e3770a38483ab36f202',
            'nativeSha256' => '6ec8b821c9a28c12ca65c771d7dcb6df0ec7f9f91b139e318d4cdbbd4fde4c76',
            'pptxBytes' => 1502,
            'nativeBytes' => 119,
        ],
        'missing-relationship-skip' => [
            'name' => 'generated missing relationship skip parity',
            'pptx' => 'pptx-reader/missing-relationship-skip.pptx',
            'native' => 'pptx-reader/missing-relationship-skip.native',
            'pairKey' => 'pptx-reader/missing-relationship-skip.pptx|pptx-reader/missing-relationship-skip.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/missing-relationship-skip.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/missing-relationship-skip.native',
            'pptxSha256' => '0a9ed423c8987719d2b5ac4ed3367db507ece227737e141b371812b57c18e77a',
            'nativeSha256' => 'e751a414543010757345bac58bc4fb6157c1c99cbbd0e958f39753c18db3e5cd',
            'pptxBytes' => 1795,
            'nativeBytes' => 144,
        ],
        'multi-paragraph-textbox' => [
            'name' => 'generated multi-paragraph text box parity',
            'pptx' => 'pptx-reader/multi-paragraph-textbox.pptx',
            'native' => 'pptx-reader/multi-paragraph-textbox.native',
            'pairKey' => 'pptx-reader/multi-paragraph-textbox.pptx|pptx-reader/multi-paragraph-textbox.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/multi-paragraph-textbox.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/multi-paragraph-textbox.native',
            'pptxSha256' => 'f586b777919bb9266acec04640e2992be888ab987009e9d6866dc440d5f3060e',
            'nativeSha256' => '1201499244544e7be60096ac6d0a434ed10036429d0bf18b6dcf2807eb8ad8fd',
            'pptxBytes' => 1519,
            'nativeBytes' => 177,
        ],
        'nested-list' => [
            'name' => 'generated adjacent list-level split parity',
            'pptx' => 'pptx-reader/nested-list.pptx',
            'native' => 'pptx-reader/nested-list.native',
            'pairKey' => 'pptx-reader/nested-list.pptx|pptx-reader/nested-list.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/nested-list.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/nested-list.native',
            'pptxSha256' => 'c85b56c09a3568286e4c0d7b1979d88b700d5f609e121955c691a58f2bb97ff0',
            'nativeSha256' => '395c237357a332023f6bb3c991f2f84d54be6fb277ce964cdaad6d9ffe2336a6',
            'pptxBytes' => 1703,
            'nativeBytes' => 253,
        ],
        'empty-paragraph-textbox' => [
            'name' => 'generated explicit empty paragraph text box parity',
            'pptx' => 'pptx-reader/empty-paragraph-textbox.pptx',
            'native' => 'pptx-reader/empty-paragraph-textbox.native',
            'pairKey' => 'pptx-reader/empty-paragraph-textbox.pptx|pptx-reader/empty-paragraph-textbox.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/empty-paragraph-textbox.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/empty-paragraph-textbox.native',
            'pptxSha256' => '3c2746d48004a382c77a6b0780c31dae0246c9f9063251db2f93bcc16e688655',
            'nativeSha256' => '9a1dd6f8ddf28f555cd1f128f5e24864284f1a721d2ae3c1e4598ebdcbe9b21b',
            'pptxBytes' => 1519,
            'nativeBytes' => 169,
        ],
        'break-tab-field' => [
            'name' => 'generated break, tab, and field text boundary parity',
            'pptx' => 'pptx-reader/break-tab-field.pptx',
            'native' => 'pptx-reader/break-tab-field.native',
            'pairKey' => 'pptx-reader/break-tab-field.pptx|pptx-reader/break-tab-field.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/break-tab-field.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/break-tab-field.native',
            'pptxSha256' => 'eab556ea99844fb5f815f977d46d5a1923d59f71682c7cceae5e23b5937f113c',
            'nativeSha256' => 'e619a9e7b375700d5fd8c2c74cd9bb5c424098d39b972212a86f58764affadf4',
            'pptxBytes' => 1435,
            'nativeBytes' => 113,
        ],
        'bullets' => [
            'name' => 'generated minimal bullet list parity',
            'pptx' => 'pptx-reader/bullets.pptx',
            'native' => 'pptx-reader/bullets.native',
            'pairKey' => 'pptx-reader/bullets.pptx|pptx-reader/bullets.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/bullets.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/bullets.native',
            'pptxSha256' => '912915e6c9a56eda1e2cb657b23cd007cd0c49da8d8d96a199e9cb8c1e310760',
            'nativeSha256' => 'f53f49de194917ae945eaaff66720120bf8a0df95c6075b31a08ea41f633507c',
            'pptxBytes' => 1543,
            'nativeBytes' => 157,
        ],
        'comments-ignored' => [
            'name' => 'generated comments ignored parity',
            'pptx' => 'pptx-reader/comments-ignored.pptx',
            'native' => 'pptx-reader/comments-ignored.native',
            'pairKey' => 'pptx-reader/comments-ignored.pptx|pptx-reader/comments-ignored.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/comments-ignored.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/comments-ignored.native',
            'pptxSha256' => 'c4677dabb5ef3ac8765c1b818ca007f85cfa16b36a47e3b409bba90fe5c5485a',
            'nativeSha256' => '0adde5d0b2b9a90a0ce7864730f945f448d9d4f204c54db62de3de2294879d2a',
            'pptxBytes' => 2368,
            'nativeBytes' => 122,
        ],
        'content-part-skip' => [
            'name' => 'generated contentPart skip parity',
            'pptx' => 'pptx-reader/content-part-skip.pptx',
            'native' => 'pptx-reader/content-part-skip.native',
            'pairKey' => 'pptx-reader/content-part-skip.pptx|pptx-reader/content-part-skip.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/content-part-skip.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/content-part-skip.native',
            'pptxSha256' => '61244c0cca6dff5a64caa8318b8e81755b2853c221bf57d8cfafb9475deb2b0b',
            'nativeSha256' => '9e223d1d5dad199772749979c4331208ea6ee428b373d213f02c62ad108989f7',
            'pptxBytes' => 1817,
            'nativeBytes' => 125,
        ],
        'connector-skip' => [
            'name' => 'generated connector shape skip parity',
            'pptx' => 'pptx-reader/connector-skip.pptx',
            'native' => 'pptx-reader/connector-skip.native',
            'pairKey' => 'pptx-reader/connector-skip.pptx|pptx-reader/connector-skip.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/connector-skip.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/connector-skip.native',
            'pptxSha256' => 'ea84954b53c9ff9b53419df4828b32e191261c8e00375f20bd03ea160326a25b',
            'nativeSha256' => 'df89712378d3c5d4994094744ecd4e20f482e0231acd053619ebf92eff5b1254',
            'pptxBytes' => 1493,
            'nativeBytes' => 139,
        ],
        'embedded-image' => [
            'name' => 'generated embedded image native parity',
            'pptx' => 'pptx-reader/embedded-image.pptx',
            'native' => 'pptx-reader/embedded-image.native',
            'pairKey' => 'pptx-reader/embedded-image.pptx|pptx-reader/embedded-image.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/embedded-image.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/embedded-image.native',
            'pptxSha256' => 'de45bd6af2dcf74e29dd7d961e5459c3a5d2b420992b1bbf280b10ee6df7256a',
            'nativeSha256' => '1aea7cedcb9155ee19a55db0d2825b1427dab1f51bbb460d140cd637e2bec266',
            'pptxBytes' => 2363,
            'nativeBytes' => 195,
        ],
        'generated-table' => [
            'name' => 'generated table extraction parity',
            'pptx' => 'pptx-reader/generated-table.pptx',
            'native' => 'pptx-reader/generated-table.native',
            'pairKey' => 'pptx-reader/generated-table.pptx|pptx-reader/generated-table.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/generated-table.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/generated-table.native',
            'pptxSha256' => '85fec7638ef6f82c43cd805e9064146c4602cf5e7384ccdfa60a55048ec67b78',
            'nativeSha256' => '17b1efbb9d7b21ddf994fffd6c9d34110c48668ab144fd5b027d40034ec2e832',
            'pptxBytes' => 1702,
            'nativeBytes' => 1192,
        ],
        'table-span-review' => [
            'name' => 'generated table span review-only parity',
            'pptx' => 'pptx-reader/table-span-review.pptx',
            'native' => 'pptx-reader/table-span-review.native',
            'pairKey' => 'pptx-reader/table-span-review.pptx|pptx-reader/table-span-review.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/table-span-review.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/table-span-review.native',
            'pptxSha256' => '6d39a50f3215706922877dd2148afb0e55208a7600d2ebb48e60830d7d160b0c',
            'nativeSha256' => '8df034dad767bbd20cc5f1f9fb875eecf84b8636dc74100677433cda03b304ce',
            'pptxBytes' => 1739,
            'nativeBytes' => 1598,
        ],
        'grouped-shapes' => [
            'name' => 'generated grouped shape skip parity',
            'pptx' => 'pptx-reader/grouped-shapes.pptx',
            'native' => 'pptx-reader/grouped-shapes.native',
            'pairKey' => 'pptx-reader/grouped-shapes.pptx|pptx-reader/grouped-shapes.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/grouped-shapes.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/grouped-shapes.native',
            'pptxSha256' => '906420300b4dd404e516ea84b72afa1ae74ea5ed729097e1cbaa6e1226fb2d09',
            'nativeSha256' => '4e1caa42c42964a8ca9dab0dfb092ad4303009f46c3b406d491307e951447176',
            'pptxBytes' => 1975,
            'nativeBytes' => 61,
        ],
        'hidden-slide' => [
            'name' => 'generated hidden slide inclusion parity',
            'pptx' => 'pptx-reader/hidden-slide.pptx',
            'native' => 'pptx-reader/hidden-slide.native',
            'pairKey' => 'pptx-reader/hidden-slide.pptx|pptx-reader/hidden-slide.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hidden-slide.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hidden-slide.native',
            'pptxSha256' => '01627fa5f56ca583f3604306984cc1df4b69a15339396b061e44604265cb802f',
            'nativeSha256' => 'a543e3ed60ca4d5f187fba970ed855d5f064a911e3ee3224b07929481c62b515',
            'pptxBytes' => 1893,
            'nativeBytes' => 178,
        ],
        'hyperlink-text' => [
            'name' => 'generated text hyperlink invisibility parity',
            'pptx' => 'pptx-reader/hyperlink-text.pptx',
            'native' => 'pptx-reader/hyperlink-text.native',
            'pairKey' => 'pptx-reader/hyperlink-text.pptx|pptx-reader/hyperlink-text.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hyperlink-text.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/hyperlink-text.native',
            'pptxSha256' => '22180e777f4a145bd3aff34f6fd5c2a846ce5567d758a78565b5dfc6addca6e3',
            'nativeSha256' => 'f4334af63e88a238caf0dcb2a4bf37fa1745d54bb2d703ec287fb3cc0474bcd7',
            'pptxBytes' => 2004,
            'nativeBytes' => 100,
        ],
        'list-continuation' => [
            'name' => 'generated buNone list-continuation boundary parity',
            'pptx' => 'pptx-reader/list-continuation.pptx',
            'native' => 'pptx-reader/list-continuation.native',
            'pairKey' => 'pptx-reader/list-continuation.pptx|pptx-reader/list-continuation.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/list-continuation.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/list-continuation.native',
            'pptxSha256' => '2b7ae7359fde4edb717371d518ef80c8bbda374fa72def88c3dcd744c91fdf5f',
            'nativeSha256' => 'd5dd188d56624d8aa5a8a848a40d2e4568e3f522f034573dc8b539842ae702de',
            'pptxBytes' => 1713,
            'nativeBytes' => 294,
        ],
        'two-slides' => [
            'name' => 'generated two-slide ordering parity',
            'pptx' => 'pptx-reader/two-slides.pptx',
            'native' => 'pptx-reader/two-slides.native',
            'pairKey' => 'pptx-reader/two-slides.pptx|pptx-reader/two-slides.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/two-slides.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/two-slides.native',
            'pptxSha256' => '58e37ebe22ba5f7e5b9f7c3fe886ae5ff085876371178e63cc115a8f6d4e052c',
            'nativeSha256' => '269e2c8b638af9834b52a0ff23c795578f9b21404e27c60d846cf81b3520596a',
            'pptxBytes' => 1897,
            'nativeBytes' => 177,
        ],
        'speaker-notes' => [
            'name' => 'generated speaker notes visibility parity',
            'pptx' => 'pptx-reader/speaker-notes.pptx',
            'native' => 'pptx-reader/speaker-notes.native',
            'pairKey' => 'pptx-reader/speaker-notes.pptx|pptx-reader/speaker-notes.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/speaker-notes.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/speaker-notes.native',
            'pptxSha256' => '52d0a82f3a84c594a9be816307c90b918cb914802bd3622a4cf9e2c06f40ddc5',
            'nativeSha256' => '24f10e8e2632d64f9afb7a3aac8b0e48570d8ef61d76f6f0a51f841d104142f1',
            'pptxBytes' => 2511,
            'nativeBytes' => 95,
        ],
        'numbered-list' => [
            'name' => 'generated auto-numbered paragraph boundary parity',
            'pptx' => 'pptx-reader/numbered-list.pptx',
            'native' => 'pptx-reader/numbered-list.native',
            'pairKey' => 'pptx-reader/numbered-list.pptx|pptx-reader/numbered-list.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/numbered-list.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/numbered-list.native',
            'pptxSha256' => 'ba1162b8a31aba2b9cc01b1d346a070d66a0f8666afa44e0ace72bfdd76f1d4b',
            'nativeSha256' => 'be9e2f1c3a9f5815ea6cc86debe2ff081a4666931dd2e48c32245cd3de40cd9f',
            'pptxBytes' => 1520,
            'nativeBytes' => 118,
        ],
        'shape-order' => [
            'name' => 'generated plain text shape ordering parity',
            'pptx' => 'pptx-reader/shape-order.pptx',
            'native' => 'pptx-reader/shape-order.native',
            'pairKey' => 'pptx-reader/shape-order.pptx|pptx-reader/shape-order.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/shape-order.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/shape-order.native',
            'pptxSha256' => '3f92fd142900b957b23cfe2b1afb01d2785d23b77ae62c23429d6bd11fd3c02f',
            'nativeSha256' => '911f29fe22d020d181e007478bff7c157f6df49d06f7c42798bb3a933d33f427',
            'pptxBytes' => 1521,
            'nativeBytes' => 135,
        ],
        'slide-placeholders' => [
            'name' => 'generated slide footer/date/number placeholder visibility parity',
            'pptx' => 'pptx-reader/slide-placeholders.pptx',
            'native' => 'pptx-reader/slide-placeholders.native',
            'pairKey' => 'pptx-reader/slide-placeholders.pptx|pptx-reader/slide-placeholders.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/slide-placeholders.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/slide-placeholders.native',
            'pptxSha256' => 'c8e3aebc55d7e464bb43409263586042420acaac2ce308601dadd081ab17354b',
            'nativeSha256' => 'f76963e6f7aa7b051bddb6ad4fa62016af8f580963f75fee42ecb840c7a64cc6',
            'pptxBytes' => 1598,
            'nativeBytes' => 203,
        ],
        'smartart-hierarchy' => [
            'name' => 'generated SmartArt hierarchy native parity',
            'pptx' => 'pptx-reader/smartart-hierarchy.pptx',
            'native' => 'pptx-reader/smartart-hierarchy.native',
            'pairKey' => 'pptx-reader/smartart-hierarchy.pptx|pptx-reader/smartart-hierarchy.native',
            'pptxPath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/smartart-hierarchy.pptx',
            'nativePath' => 'lanes/pandoc/fixtures/upstream-current-pptx-reader/smartart-hierarchy.native',
            'pptxSha256' => '186195196185f1c5b95a0e7e2c327dc551371edbd09de4d2f94e418ff10420eb',
            'nativeSha256' => 'bc41c663b7f2711c8d12039d385926db19bae387c07290cd7629e5ab278e2ce9',
            'pptxBytes' => 2664,
            'nativeBytes' => 332,
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
                'staticCurrentEvidence' => $this->staticCurrentEvidence(),
                'runnerEvidence' => self::runnerNotRunEvidence(),
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $readerTestPath = $root . '/test/Tests/Readers/Pptx.hs';
        $fixtureDirectory = $root . '/test/pptx-reader';
        $readerCases = is_file($readerTestPath)
            ? $this->parseReaderCases((string) file_get_contents($readerTestPath))
            : [];
        $fixturePairs = $this->fixturePairs($fixtureDirectory);
        $unpairedFixtures = $this->unpairedFixtureFiles($fixtureDirectory);
        $validationIssues = $this->validationIssues($root, $readerCases, $fixturePairs, $unpairedFixtures);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $this->gitHead($root),
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerTestModule' => 'test/Tests/Readers/Pptx.hs',
                'fixtureDirectory' => 'test/pptx-reader',
            ],
            'denominator' => [
                'readerTestCompareCount' => count($readerCases),
                'fixturePairCount' => count($fixturePairs),
                'referencedPairCount' => count($readerCases),
                'readerCases' => $readerCases,
                'fixturePairs' => array_values($fixturePairs),
                'unpairedPptxFixtureCount' => count($unpairedFixtures['pptx']),
                'unpairedNativeFixtureCount' => count($unpairedFixtures['native']),
                'unpairedPptxFixtures' => $unpairedFixtures['pptx'],
                'unpairedNativeFixtures' => $unpairedFixtures['native'],
                'missingReferencedFiles' => $this->missingReferencedFiles($root, $readerCases),
                'unreferencedFixturePairs' => $this->unreferencedFixturePairs($readerCases, $fixturePairs),
            ],
            'sourceInventory' => $this->sourceInventory($root),
            'staticCurrentEvidence' => $this->staticCurrentEvidence(),
            'runnerEvidence' => self::runnerNotRunEvidence(),
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-pptx-reader-denominator' : 'invalid-upstream-pptx-reader-denominator',
                'issues' => $validationIssues,
            ],
            'claim' => self::claim(),
            'claimBoundaries' => self::claimBoundaries(),
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
        $staticDenominator = is_array($staticEvidence['readerDenominator'] ?? null) ? $staticEvidence['readerDenominator'] : [];
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];

        return implode(PHP_EOL, [
            'Pandoc PPTX reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Upstream: ' . (string) ($upstream['commit'] ?? 'unknown')
                . ' expected=' . (string) ($upstream['expectedCommit'] ?? self::EXPECTED_UPSTREAM_COMMIT),
            'Reader test comparisons: ' . (int) ($denominator['readerTestCompareCount'] ?? 0),
            'Fixture pairs: ' . (int) ($denominator['fixturePairCount'] ?? 0)
                . ' unpairedPptx=' . (int) ($denominator['unpairedPptxFixtureCount'] ?? 0)
                . ' unpairedNative=' . (int) ($denominator['unpairedNativeFixtureCount'] ?? 0),
            'Static current evidence: ' . (string) ($staticValidation['status'] ?? 'unknown')
                . ' comparisons=' . (int) ($staticDenominator['expectedCompareCount'] ?? 0)
                . ' checkedInPairs=' . (int) ($staticEvidence['checkedInFixturePairCount'] ?? 0)
                . ' checkedInUnpairedPptx=' . (int) ($staticEvidence['checkedInUnpairedPptxFixtureCount'] ?? 0)
                . ' checkedInUnpairedNative=' . (int) ($staticEvidence['checkedInUnpairedNativeFixtureCount'] ?? 0),
            'Runner status: ' . (string) ($runner['status'] ?? 'unknown'),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            'No upstream Haskell/Cabal runner result or full PowerPoint feature parity is asserted.',
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredReaderTestCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['readerTestCompareCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredFixturePairCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['fixturePairCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-pptx-reader-denominator'
            && ($validation['issues'] ?? null) === [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticCurrentEvidence(array $report): bool
    {
        $evidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];
        $denominator = is_array($evidence['readerDenominator'] ?? null) ? $evidence['readerDenominator'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-current-pptx-reader-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($denominator['expectedCompareCount'] ?? -1) === self::EXPECTED_STATIC_READER_TEST_COMPARE_COUNT
            && (int) ($evidence['checkedInFixturePairCount'] ?? -1) === self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRunnerNotRunEvidence(array $report): bool
    {
        $runner = is_array($report['runnerEvidence'] ?? null) ? $report['runnerEvidence'] : [];

        return ($runner['status'] ?? null) === 'not-run'
            && ($runner['executed'] ?? null) === false
            && array_key_exists('command', $runner)
            && $runner['command'] === null
            && array_key_exists('resultArtifact', $runner)
            && $runner['resultArtifact'] === null;
    }

    private static function claim(): string
    {
        return 'Parses the pinned upstream Tests.Readers.Pptx test module and test/pptx-reader fixture directory to establish the current PPTX reader golden-test denominator.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the count and file paths of upstream PPTX reader golden comparisons in Tests.Readers.Pptx',
                'that every referenced PPTX/native fixture file exists in the pinned sparse upstream checkout',
                'that root-level test/pptx-reader PPTX/native fixture pairs and unpaired files are accounted for',
                'static checked-in current upstream basic.pptx/basic.native plus generated body-before-title.pptx/body-before-title.native, minimal.pptx/minimal.native, missing-relationship-skip.pptx/missing-relationship-skip.native, multi-paragraph-textbox.pptx/multi-paragraph-textbox.native, nested-list.pptx/nested-list.native, empty-paragraph-textbox.pptx/empty-paragraph-textbox.native, break-tab-field.pptx/break-tab-field.native, bullets.pptx/bullets.native, comments-ignored.pptx/comments-ignored.native, content-part-skip.pptx/content-part-skip.native, connector-skip.pptx/connector-skip.native, embedded-image.pptx/embedded-image.native, generated-table.pptx/generated-table.native, table-span-review.pptx/table-span-review.native, grouped-shapes.pptx/grouped-shapes.native, hidden-slide.pptx/hidden-slide.native, hyperlink-text.pptx/hyperlink-text.native, list-continuation.pptx/list-continuation.native, two-slides.pptx/two-slides.native, speaker-notes.pptx/speaker-notes.native, numbered-list.pptx/numbered-list.native, shape-order.pptx/shape-order.native, slide-placeholders.pptx/slide-placeholders.native, and smartart-hierarchy.pptx/smartart-hierarchy.native fixture identities when staticCurrentEvidence is valid',
                'that upstream Haskell runner evidence is explicitly not-run',
            ],
            'doesNotAssert' => [
                'that upstream Haskell/Cabal/Tasty tests were executed',
                'full upstream Tests.Readers.Pptx runner parity',
                'that local PHP output matches upstream native output',
                'PPTX writer parity',
                'full PowerPoint feature parity beyond Pandoc reader behavior',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function runnerNotRunEvidence(): array
    {
        return [
            'runner' => 'Cabal/Tasty Pandoc PPTX reader suite',
            'scope' => 'upstream-haskell-runner',
            'status' => 'not-run',
            'executed' => false,
            'command' => null,
            'resultArtifact' => null,
            'blockers' => [
                'no committed upstream test:test-pandoc PPTX runner transcript or result artifact is present',
                'this PHP evidence gate intentionally does not invoke Cabal/Tasty or hydrate Haskell build dependencies',
                'a future runner claim must be bound to the pinned upstream commit and exact targeted PPTX Tasty pattern',
            ],
            'futureCommands' => [
                [
                    'purpose' => 'prepare runner dependencies in an isolated build directory',
                    'program' => 'cabal',
                    'arguments' => [
                        'v2-build',
                        '--offline',
                        '--dry-run',
                        '--only-dependencies',
                        '--project-dir=.',
                        '--builddir=.port-libs/pandoc-runner/cabal-build/pptx-targeted-run',
                        'test:test-pandoc',
                    ],
                ],
                [
                    'purpose' => 'list targeted PPTX reader tests',
                    'program' => 'cabal',
                    'arguments' => [
                        'v2-run',
                        '--offline',
                        '--project-dir=.',
                        '--builddir=.port-libs/pandoc-runner/cabal-build/pptx-targeted-run',
                        'test:test-pandoc',
                        '--',
                        '--list-tests',
                        '--pattern',
                        '$2 == "Readers" && $3 == "Pptx"',
                    ],
                ],
                [
                    'purpose' => 'run targeted PPTX reader tests',
                    'program' => 'cabal',
                    'arguments' => [
                        'v2-run',
                        '--offline',
                        '--project-dir=.',
                        '--builddir=.port-libs/pandoc-runner/cabal-build/pptx-targeted-run',
                        'test:test-pandoc',
                        '--',
                        '--pattern',
                        '$2 == "Readers" && $3 == "Pptx"',
                    ],
                ],
            ],
            'requiredArtifacts' => [
                '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
                '.port-libs/pandoc-runner/logs/pptx-targeted-list-tests.txt',
                '.port-libs/pandoc-runner/logs/pptx-targeted-run.txt',
                '.port-libs/pandoc-runner/artifacts/pptx-targeted-run/result.json',
            ],
            'claim' => 'No upstream Haskell runner parity is claimed.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staticCurrentEvidence(): array
    {
        $fixtureDirectory = $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY);
        $checkedInFixturePairs = array_values($this->fixturePairs($fixtureDirectory));
        $checkedInUnpairedFixtures = $this->unpairedFixtureFiles($fixtureDirectory);
        $checkedInPairKeys = [];
        foreach ($checkedInFixturePairs as $pair) {
            $checkedInPairKeys[(string) $pair['pairKey']] = true;
        }

        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-current-fixture-directory';
        }

        if (count(self::STATIC_CURRENT_READER_CASES) !== self::EXPECTED_STATIC_READER_TEST_COMPARE_COUNT) {
            $issues[] = 'static-reader-test-count-does-not-match-expected-snapshot';
        }

        if (count($checkedInFixturePairs) !== self::EXPECTED_STATIC_CHECKED_IN_FIXTURE_PAIR_COUNT) {
            $issues[] = 'checked-in-fixture-pair-count-does-not-match-static-snapshot';
        }
        if ($checkedInUnpairedFixtures['pptx'] !== []) {
            $issues[] = 'checked-in-current-unpaired-pptx-fixtures';
        }
        if ($checkedInUnpairedFixtures['native'] !== []) {
            $issues[] = 'checked-in-current-unpaired-native-fixtures';
        }

        $snapshotPairs = [];
        foreach (self::CHECKED_IN_CURRENT_FIXTURE_SNAPSHOT as $stem => $snapshot) {
            $pptx = $this->snapshotFileEvidence(
                (string) $snapshot['pptxPath'],
                (string) $snapshot['pptxSha256'],
                (int) $snapshot['pptxBytes']
            );
            $native = $this->snapshotFileEvidence(
                (string) $snapshot['nativePath'],
                (string) $snapshot['nativeSha256'],
                (int) $snapshot['nativeBytes']
            );
            $pairKey = (string) $snapshot['pairKey'];
            $snapshotPairs[] = [
                'stem' => (string) $stem,
                'name' => (string) ($snapshot['name'] ?? self::STATIC_CURRENT_READER_CASES[0]['name']),
                'pptx' => (string) $snapshot['pptx'],
                'native' => (string) $snapshot['native'],
                'pairKey' => $pairKey,
                'checkedInPptx' => $pptx,
                'checkedInNative' => $native,
            ];

            if (($pptx['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-pptx-fixture';
            } elseif (($pptx['sha256'] ?? null) !== $snapshot['pptxSha256']) {
                $issues[] = 'checked-in-current-pptx-sha256-mismatch';
            } elseif ((int) ($pptx['bytes'] ?? -1) !== (int) $snapshot['pptxBytes']) {
                $issues[] = 'checked-in-current-pptx-byte-count-mismatch';
            }

            if (($native['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-native-fixture';
            } elseif (($native['sha256'] ?? null) !== $snapshot['nativeSha256']) {
                $issues[] = 'checked-in-current-native-sha256-mismatch';
            } elseif ((int) ($native['bytes'] ?? -1) !== (int) $snapshot['nativeBytes']) {
                $issues[] = 'checked-in-current-native-byte-count-mismatch';
            }

            if (!isset($checkedInPairKeys[$pairKey])) {
                $issues[] = 'checked-in-current-fixture-pair-key-mismatch';
            }
        }

        return [
            'kind' => 'static-checked-in-current-pptx-reader-fixture-evidence',
            'upstream' => [
                'name' => 'jgm/pandoc',
                'commit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerTestModule' => 'test/Tests/Readers/Pptx.hs',
                'fixtureDirectory' => 'test/pptx-reader',
            ],
            'readerDenominator' => [
                'expectedCompareCount' => count(self::STATIC_CURRENT_READER_CASES),
                'expectedReaderCases' => self::STATIC_CURRENT_READER_CASES,
            ],
            'checkedInFixtureDirectory' => self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY,
            'checkedInFixturePairCount' => count($checkedInFixturePairs),
            'checkedInFixturePairs' => $snapshotPairs,
            'checkedInUnpairedPptxFixtureCount' => count($checkedInUnpairedFixtures['pptx']),
            'checkedInUnpairedNativeFixtureCount' => count($checkedInUnpairedFixtures['native']),
            'checkedInUnpairedPptxFixtures' => $checkedInUnpairedFixtures['pptx'],
            'checkedInUnpairedNativeFixtures' => $checkedInUnpairedFixtures['native'],
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-pptx-reader-evidence' : 'invalid-checked-in-current-pptx-reader-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding the pinned Tests.Readers.Pptx one-case denominator to the checked-in current upstream basic.pptx/basic.native fixture pair, plus twenty-four generated PPTX/native pairs used only for local normalized-AST parity.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'Tests.Readers.Pptx at the pinned upstream commit has one golden comparison for pptx-reader/basic.pptx and pptx-reader/basic.native',
                    'the checked-in current PPTX fixture directory contains twenty-five same-stem PPTX/native pairs and no unpaired PPTX/native files',
                    'the checked-in basic.pptx/basic.native, body-before-title.pptx/body-before-title.native, minimal.pptx/minimal.native, missing-relationship-skip.pptx/missing-relationship-skip.native, multi-paragraph-textbox.pptx/multi-paragraph-textbox.native, nested-list.pptx/nested-list.native, empty-paragraph-textbox.pptx/empty-paragraph-textbox.native, break-tab-field.pptx/break-tab-field.native, bullets.pptx/bullets.native, comments-ignored.pptx/comments-ignored.native, content-part-skip.pptx/content-part-skip.native, connector-skip.pptx/connector-skip.native, embedded-image.pptx/embedded-image.native, generated-table.pptx/generated-table.native, table-span-review.pptx/table-span-review.native, grouped-shapes.pptx/grouped-shapes.native, hidden-slide.pptx/hidden-slide.native, hyperlink-text.pptx/hyperlink-text.native, list-continuation.pptx/list-continuation.native, two-slides.pptx/two-slides.native, speaker-notes.pptx/speaker-notes.native, numbered-list.pptx/numbered-list.native, shape-order.pptx/shape-order.native, slide-placeholders.pptx/slide-placeholders.native, and smartart-hierarchy.pptx/smartart-hierarchy.native files match the expected SHA-256 hashes and byte counts for this snapshot',
                ],
                'doesNotAssert' => [
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'that a fresh upstream checkout was inspected during this PHP gate',
                    'that body-before-title.pptx/body-before-title.native is an upstream Tests.Readers.Pptx fixture',
                    'that minimal.pptx/minimal.native is an upstream Tests.Readers.Pptx fixture',
                    'that missing-relationship-skip.pptx/missing-relationship-skip.native is an upstream Tests.Readers.Pptx fixture',
                    'that multi-paragraph-textbox.pptx/multi-paragraph-textbox.native is an upstream Tests.Readers.Pptx fixture',
                    'that nested-list.pptx/nested-list.native is an upstream Tests.Readers.Pptx fixture',
                    'that empty-paragraph-textbox.pptx/empty-paragraph-textbox.native is an upstream Tests.Readers.Pptx fixture',
                    'that break-tab-field.pptx/break-tab-field.native is an upstream Tests.Readers.Pptx fixture',
                    'that bullets.pptx/bullets.native is an upstream Tests.Readers.Pptx fixture',
                    'that comments-ignored.pptx/comments-ignored.native is an upstream Tests.Readers.Pptx fixture',
                    'that content-part-skip.pptx/content-part-skip.native is an upstream Tests.Readers.Pptx fixture',
                    'that connector-skip.pptx/connector-skip.native is an upstream Tests.Readers.Pptx fixture',
                    'that embedded-image.pptx/embedded-image.native is an upstream Tests.Readers.Pptx fixture',
                    'that generated-table.pptx/generated-table.native is an upstream Tests.Readers.Pptx fixture',
                    'that table-span-review.pptx/table-span-review.native is an upstream Tests.Readers.Pptx fixture',
                    'that grouped-shapes.pptx/grouped-shapes.native is an upstream Tests.Readers.Pptx fixture',
                    'that hidden-slide.pptx/hidden-slide.native is an upstream Tests.Readers.Pptx fixture',
                    'that hyperlink-text.pptx/hyperlink-text.native is an upstream Tests.Readers.Pptx fixture',
                    'that list-continuation.pptx/list-continuation.native is an upstream Tests.Readers.Pptx fixture',
                    'that two-slides.pptx/two-slides.native is an upstream Tests.Readers.Pptx fixture',
                    'that speaker-notes.pptx/speaker-notes.native is an upstream Tests.Readers.Pptx fixture',
                    'that numbered-list.pptx/numbered-list.native is an upstream Tests.Readers.Pptx fixture',
                    'that shape-order.pptx/shape-order.native is an upstream Tests.Readers.Pptx fixture',
                    'that slide-placeholders.pptx/slide-placeholders.native is an upstream Tests.Readers.Pptx fixture',
                    'that smartart-hierarchy.pptx/smartart-hierarchy.native is an upstream Tests.Readers.Pptx fixture',
                    'broader PPTX fixture corpus coverage beyond basic.pptx/basic.native, body-before-title.pptx/body-before-title.native, minimal.pptx/minimal.native, missing-relationship-skip.pptx/missing-relationship-skip.native, multi-paragraph-textbox.pptx/multi-paragraph-textbox.native, nested-list.pptx/nested-list.native, empty-paragraph-textbox.pptx/empty-paragraph-textbox.native, break-tab-field.pptx/break-tab-field.native, bullets.pptx/bullets.native, comments-ignored.pptx/comments-ignored.native, content-part-skip.pptx/content-part-skip.native, connector-skip.pptx/connector-skip.native, embedded-image.pptx/embedded-image.native, generated-table.pptx/generated-table.native, table-span-review.pptx/table-span-review.native, grouped-shapes.pptx/grouped-shapes.native, hidden-slide.pptx/hidden-slide.native, hyperlink-text.pptx/hyperlink-text.native, list-continuation.pptx/list-continuation.native, two-slides.pptx/two-slides.native, speaker-notes.pptx/speaker-notes.native, numbered-list.pptx/numbered-list.native, shape-order.pptx/shape-order.native, slide-placeholders.pptx/slide-placeholders.native, and smartart-hierarchy.pptx/smartart-hierarchy.native',
                    'full PowerPoint feature parity',
                ],
            ],
        ];
    }

    /**
     * @return array{path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}
     */
    private function snapshotFileEvidence(string $relativePath, string $expectedSha256, int $expectedBytes): array
    {
        $path = $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
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

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'readerTestCompareCount' => 0,
            'fixturePairCount' => 0,
            'referencedPairCount' => 0,
            'readerCases' => [],
            'fixturePairs' => [],
            'unpairedPptxFixtureCount' => 0,
            'unpairedNativeFixtureCount' => 0,
            'unpairedPptxFixtures' => [],
            'unpairedNativeFixtures' => [],
            'missingReferencedFiles' => [],
            'unreferencedFixturePairs' => [],
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
     * @return list<array{name: string, pptx: string, native: string, pairKey: string}>
     */
    private function parseReaderCases(string $source): array
    {
        $cases = [];
        $pattern = '/\btestCompare(?:WithOpts\s+\S+)?\s+"([^"]+)"\s+"([^"]+\.pptx)"\s+"([^"]+\.native)"/s';
        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $pptx = (string) $match[2];
                $native = (string) $match[3];
                $cases[] = [
                    'name' => (string) $match[1],
                    'pptx' => $pptx,
                    'native' => $native,
                    'pairKey' => self::pairKey($pptx, $native),
                ];
            }
        }

        return $cases;
    }

    /**
     * @return array<string, array{pptx: string, native: string, pairKey: string}>
     */
    private function fixturePairs(string $fixtureDirectory): array
    {
        if (!is_dir($fixtureDirectory)) {
            return [];
        }

        $pptxByStem = $this->fixtureFilesByStem($fixtureDirectory, 'pptx');
        $nativeByStem = $this->fixtureFilesByStem($fixtureDirectory, 'native');

        $pairs = [];
        foreach (array_intersect(array_keys($pptxByStem), array_keys($nativeByStem)) as $stem) {
            $pptx = $pptxByStem[$stem];
            $native = $nativeByStem[$stem];
            $pairs[$stem] = [
                'pptx' => $pptx,
                'native' => $native,
                'pairKey' => self::pairKey($pptx, $native),
            ];
        }
        ksort($pairs, SORT_STRING);

        return $pairs;
    }

    /**
     * @return array<string, string>
     */
    private function fixtureFilesByStem(string $fixtureDirectory, string $extension): array
    {
        if (!is_dir($fixtureDirectory)) {
            return [];
        }

        $files = [];
        foreach (glob($fixtureDirectory . '/*.' . $extension) ?: [] as $path) {
            $stem = basename($path, '.' . $extension);
            $files[$stem] = 'pptx-reader/' . basename($path);
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return array{pptx:list<string>, native:list<string>}
     */
    private function unpairedFixtureFiles(string $fixtureDirectory): array
    {
        $pptxByStem = $this->fixtureFilesByStem($fixtureDirectory, 'pptx');
        $nativeByStem = $this->fixtureFilesByStem($fixtureDirectory, 'native');

        $unpairedPptx = array_values(array_diff_key($pptxByStem, $nativeByStem));
        $unpairedNative = array_values(array_diff_key($nativeByStem, $pptxByStem));
        sort($unpairedPptx, SORT_STRING);
        sort($unpairedNative, SORT_STRING);

        return [
            'pptx' => $unpairedPptx,
            'native' => $unpairedNative,
        ];
    }

    /**
     * @param list<array{name: string, pptx: string, native: string, pairKey: string}> $readerCases
     * @return list<array{case: string, path: string}>
     */
    private function missingReferencedFiles(string $root, array $readerCases): array
    {
        $missing = [];
        foreach ($readerCases as $case) {
            foreach (['pptx', 'native'] as $kind) {
                $relative = 'test/' . $case[$kind];
                if (!is_file($root . '/' . $relative)) {
                    $missing[] = [
                        'case' => $case['name'],
                        'path' => $relative,
                    ];
                }
            }
        }

        return $missing;
    }

    /**
     * @param list<array{name: string, pptx: string, native: string, pairKey: string}> $readerCases
     * @param array<string, array{pptx: string, native: string, pairKey: string}> $fixturePairs
     * @return list<array{pptx: string, native: string, pairKey: string}>
     */
    private function unreferencedFixturePairs(array $readerCases, array $fixturePairs): array
    {
        $referenced = [];
        foreach ($readerCases as $case) {
            $referenced[$case['pairKey']] = true;
        }

        $unreferenced = [];
        foreach ($fixturePairs as $pair) {
            if (!isset($referenced[$pair['pairKey']])) {
                $unreferenced[] = $pair;
            }
        }

        return $unreferenced;
    }

    /**
     * @param list<array{name: string, pptx: string, native: string, pairKey: string}> $readerCases
     * @param array<string, array{pptx: string, native: string, pairKey: string}> $fixturePairs
     * @param array{pptx:list<string>, native:list<string>} $unpairedFixtures
     * @return list<string>
     */
    private function validationIssues(string $root, array $readerCases, array $fixturePairs, array $unpairedFixtures): array
    {
        $issues = [];
        if (!is_file($root . '/test/Tests/Readers/Pptx.hs')) {
            $issues[] = 'missing-reader-test-module';
        }
        if (!is_dir($root . '/test/pptx-reader')) {
            $issues[] = 'missing-pptx-reader-fixture-directory';
        }
        if ($readerCases === []) {
            $issues[] = 'no-reader-test-comparisons-found';
        }
        if ($fixturePairs === []) {
            $issues[] = 'no-pptx-native-fixture-pairs-found';
        }
        if ($this->missingReferencedFiles($root, $readerCases) !== []) {
            $issues[] = 'missing-referenced-fixture-files';
        }
        if ($this->unreferencedFixturePairs($readerCases, $fixturePairs) !== []) {
            $issues[] = 'unreferenced-fixture-pairs';
        }
        if ($unpairedFixtures['pptx'] !== []) {
            $issues[] = 'unpaired-pptx-fixtures';
        }
        if ($unpairedFixtures['native'] !== []) {
            $issues[] = 'unpaired-native-fixtures';
        }
        if (count($readerCases) !== count($fixturePairs)) {
            $issues[] = 'reader-test-count-does-not-match-fixture-pair-count';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceInventory(string $root): array
    {
        $relativeFiles = [
            'test/Tests/Readers/Pptx.hs',
            'src/Text/Pandoc/Readers/Pptx.hs',
            'src/Text/Pandoc/Readers/Pptx/Parse.hs',
            'src/Text/Pandoc/Readers/Pptx/Shapes.hs',
            'src/Text/Pandoc/Readers/Pptx/Slides.hs',
            'src/Text/Pandoc/Readers/Pptx/SmartArt.hs',
        ];
        $files = [];
        $presentCount = 0;
        $lineCount = 0;
        foreach ($relativeFiles as $relative) {
            $path = $root . '/' . $relative;
            $present = is_file($path);
            $lines = $present ? count(file($path) ?: []) : 0;
            $files[] = [
                'path' => $relative,
                'present' => $present,
                'lines' => $lines,
            ];
            if ($present) {
                ++$presentCount;
                $lineCount += $lines;
            }
        }

        return [
            'files' => $files,
            'presentFileCount' => $presentCount,
            'missingFileCount' => count($relativeFiles) - $presentCount,
            'presentLineCount' => $lineCount,
        ];
    }

    private function absoluteUpstreamRoot(): string
    {
        if (str_starts_with($this->upstreamRoot, DIRECTORY_SEPARATOR)) {
            return rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
    }

    private function displayPath(string $path): string
    {
        $prefix = $this->repoRoot . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $prefix)) {
            return substr($path, strlen($prefix));
        }

        return $path;
    }

    private function gitHead(string $root): ?string
    {
        if (!is_dir($root . '/.git')) {
            return null;
        }

        $output = [];
        $exitCode = 0;
        exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0 || !is_string($output[0] ?? null)) {
            return null;
        }

        return trim($output[0]);
    }

    private static function pairKey(string $pptx, string $native): string
    {
        return $pptx . '|' . $native;
    }
}
