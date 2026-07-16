<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\SyntaxHighlighter;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = file_get_contents(__DIR__ . '/../fixtures/wordpress-syntax-highlight.md');
if (!is_string($fixture)) {
    throw new RuntimeException('Unable to read syntax highlight fixture');
}

$document = (new MarkdownReader())->read($fixture);
$codeBlock = $document->children[0] ?? null;
if (!$codeBlock instanceof PortLibs\Pandoc\AstNode || $codeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to start with a code block');
}

$highlighter = new SyntaxHighlighter();
$highlighted = $highlighter->highlightCodeBlock($codeBlock, 'pygments');
$wordpressBlock = $highlighter->wordpressHtmlBlock($codeBlock, 'pygments');
$writerHighlightedBlocks = (new WordPressBlockWriter([
    'highlightCodeBlocks' => true,
    'highlightStyle' => 'kate',
]))->write($document);
$numberedCodeBlock = new AstNode('code_block', [
    'id' => 'migration-review',
    'classes' => ['php', 'numberLines', 'lineAnchors'],
    'attributes' => ['startFrom' => '42'],
    'text' => "<?php\necho esc_html(\$title);",
]);
$numbered = $highlighter->highlightCodeBlock($numberedCodeBlock, 'pygments');
$numberedWordpressBlock = $highlighter->wordpressHtmlBlock($numberedCodeBlock, 'pygments');
$haskellCodeBlock = new AstNode('code_block', [
    'classes' => ['sourceCode', 'literate-haskell'],
    'attributes' => [],
    'text' => implode("\n", [
        '{- migration review -}',
        'module Review.Import where',
        'import Text.Pandoc (Pandoc)',
        'renderBlocks :: Pandoc -> Text',
        'status = Just 42',
    ]),
]);
$haskell = $highlighter->highlightCodeBlock($haskellCodeBlock, 'zenburn');
$haskellWordpressBlock = $highlighter->wordpressHtmlBlock($haskellCodeBlock, 'zenburn');
$latexCodeBlock = $document->children[2] ?? null;
if (!$latexCodeBlock instanceof PortLibs\Pandoc\AstNode || $latexCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a LaTeX code block');
}
$latex = $highlighter->highlightCodeBlock($latexCodeBlock, 'haddock');
$latexWordpressBlock = $highlighter->wordpressHtmlBlock($latexCodeBlock, 'haddock');
$diffCodeBlock = $document->children[3] ?? null;
if (!$diffCodeBlock instanceof PortLibs\Pandoc\AstNode || $diffCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a diff code block');
}
$diff = $highlighter->highlightCodeBlock($diffCodeBlock, 'tango');
$diffWordpressBlock = $highlighter->wordpressHtmlBlock($diffCodeBlock, 'tango');
$markdownCodeBlock = $document->children[4] ?? null;
if (!$markdownCodeBlock instanceof PortLibs\Pandoc\AstNode || $markdownCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Markdown code block');
}
$markdown = $highlighter->highlightCodeBlock($markdownCodeBlock, 'kate');
$markdownWordpressBlock = $highlighter->wordpressHtmlBlock($markdownCodeBlock, 'kate');
$rubyCodeBlock = $document->children[5] ?? null;
if (!$rubyCodeBlock instanceof PortLibs\Pandoc\AstNode || $rubyCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Ruby code block');
}
$ruby = $highlighter->highlightCodeBlock($rubyCodeBlock, 'espresso');
$rubyWordpressBlock = $highlighter->wordpressHtmlBlock($rubyCodeBlock, 'espresso');
$luaCodeBlock = $document->children[6] ?? null;
if (!$luaCodeBlock instanceof PortLibs\Pandoc\AstNode || $luaCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Lua code block');
}
$lua = $highlighter->highlightCodeBlock($luaCodeBlock, 'breezedark');
$luaWordpressBlock = $highlighter->wordpressHtmlBlock($luaCodeBlock, 'breezedark');
$typescriptCodeBlock = $document->children[7] ?? null;
if (!$typescriptCodeBlock instanceof PortLibs\Pandoc\AstNode || $typescriptCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a TypeScript code block');
}
$typescript = $highlighter->highlightCodeBlock($typescriptCodeBlock, 'kate');
$typescriptWordpressBlock = $highlighter->wordpressHtmlBlock($typescriptCodeBlock, 'kate');
$pythonCodeBlock = $document->children[8] ?? null;
if (!$pythonCodeBlock instanceof PortLibs\Pandoc\AstNode || $pythonCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Python code block');
}
$python = $highlighter->highlightCodeBlock($pythonCodeBlock, 'monochrome');
$pythonWordpressBlock = $highlighter->wordpressHtmlBlock($pythonCodeBlock, 'monochrome');
$cppCodeBlock = $document->children[9] ?? null;
if (!$cppCodeBlock instanceof PortLibs\Pandoc\AstNode || $cppCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a C++ code block');
}
$cpp = $highlighter->highlightCodeBlock($cppCodeBlock, 'pygments');
$cppWordpressBlock = $highlighter->wordpressHtmlBlock($cppCodeBlock, 'pygments');
$dockerfileCodeBlock = $document->children[10] ?? null;
if (!$dockerfileCodeBlock instanceof PortLibs\Pandoc\AstNode || $dockerfileCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Dockerfile code block');
}
$dockerfile = $highlighter->highlightCodeBlock($dockerfileCodeBlock, 'tango');
$dockerfileWordpressBlock = $highlighter->wordpressHtmlBlock($dockerfileCodeBlock, 'tango');
$makefileCodeBlock = $document->children[11] ?? null;
if (!$makefileCodeBlock instanceof PortLibs\Pandoc\AstNode || $makefileCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Makefile code block');
}
$makefile = $highlighter->highlightCodeBlock($makefileCodeBlock, 'zenburn');
$makefileWordpressBlock = $highlighter->wordpressHtmlBlock($makefileCodeBlock, 'zenburn');
$jsxCodeBlock = $document->children[12] ?? null;
if (!$jsxCodeBlock instanceof PortLibs\Pandoc\AstNode || $jsxCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a JSX code block');
}
$jsx = $highlighter->highlightCodeBlock($jsxCodeBlock, 'breezedark');
$jsxWordpressBlock = $highlighter->wordpressHtmlBlock($jsxCodeBlock, 'breezedark');
$rCodeBlock = $document->children[13] ?? null;
if (!$rCodeBlock instanceof PortLibs\Pandoc\AstNode || $rCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an R code block');
}
$rScript = $highlighter->highlightCodeBlock($rCodeBlock, 'espresso');
$rWordpressBlock = $highlighter->wordpressHtmlBlock($rCodeBlock, 'espresso');
$iniCodeBlock = $document->children[14] ?? null;
if (!$iniCodeBlock instanceof PortLibs\Pandoc\AstNode || $iniCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an INI code block');
}
$ini = $highlighter->highlightCodeBlock($iniCodeBlock, 'haddock');
$iniWordpressBlock = $highlighter->wordpressHtmlBlock($iniCodeBlock, 'haddock');
$tomlCodeBlock = $document->children[15] ?? null;
if (!$tomlCodeBlock instanceof PortLibs\Pandoc\AstNode || $tomlCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a TOML code block');
}
$toml = $highlighter->highlightCodeBlock($tomlCodeBlock, 'kate');
$tomlWordpressBlock = $highlighter->wordpressHtmlBlock($tomlCodeBlock, 'kate');
$perlCodeBlock = $document->children[16] ?? null;
if (!$perlCodeBlock instanceof PortLibs\Pandoc\AstNode || $perlCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Perl code block');
}
$perl = $highlighter->highlightCodeBlock($perlCodeBlock, 'zenburn');
$perlWordpressBlock = $highlighter->wordpressHtmlBlock($perlCodeBlock, 'zenburn');
$javaCodeBlock = $document->children[17] ?? null;
if (!$javaCodeBlock instanceof PortLibs\Pandoc\AstNode || $javaCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Java code block');
}
$java = $highlighter->highlightCodeBlock($javaCodeBlock, 'tango');
$javaWordpressBlock = $highlighter->wordpressHtmlBlock($javaCodeBlock, 'tango');
$xmlCodeBlock = $document->children[18] ?? null;
if (!$xmlCodeBlock instanceof PortLibs\Pandoc\AstNode || $xmlCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an XML code block');
}
$xml = $highlighter->highlightCodeBlock($xmlCodeBlock, 'haddock');
$xmlWordpressBlock = $highlighter->wordpressHtmlBlock($xmlCodeBlock, 'haddock');
$xslt = $highlighter->highlight(
    "<xsl:template match=\"/rss/channel/item\">\n  <xsl:value-of select=\"normalize-space(title)\"/>\n</xsl:template>",
    'xsl'
);
$shellCodeBlock = $document->children[19] ?? null;
if (!$shellCodeBlock instanceof PortLibs\Pandoc\AstNode || $shellCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Bash shell code block');
}
$shell = $highlighter->highlightCodeBlock($shellCodeBlock, 'pygments');
$shellWordpressBlock = $highlighter->wordpressHtmlBlock($shellCodeBlock, 'pygments');
$tokenTitleCodeBlock = $document->children[20] ?? null;
if (!$tokenTitleCodeBlock instanceof PortLibs\Pandoc\AstNode || $tokenTitleCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a token-title PHP code block');
}
$tokenTitle = $highlighter->highlightCodeBlock($tokenTitleCodeBlock, 'kate');
$tokenTitleWordpressBlock = $highlighter->wordpressHtmlBlock($tokenTitleCodeBlock, 'kate');
$cssCodeBlock = $document->children[21] ?? null;
if (!$cssCodeBlock instanceof PortLibs\Pandoc\AstNode || $cssCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a CSS code block');
}
$css = $highlighter->highlightCodeBlock($cssCodeBlock, 'espresso');
$cssWordpressBlock = $highlighter->wordpressHtmlBlock($cssCodeBlock, 'espresso');
$rustCodeBlock = $document->children[22] ?? null;
if (!$rustCodeBlock instanceof PortLibs\Pandoc\AstNode || $rustCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Rust code block');
}
$rust = $highlighter->highlightCodeBlock($rustCodeBlock, 'zenburn');
$rustWordpressBlock = $highlighter->wordpressHtmlBlock($rustCodeBlock, 'zenburn');
$nixCodeBlock = $document->children[23] ?? null;
if (!$nixCodeBlock instanceof PortLibs\Pandoc\AstNode || $nixCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Nix code block');
}
$nix = $highlighter->highlightCodeBlock($nixCodeBlock, 'kate');
$nixWordpressBlock = $highlighter->wordpressHtmlBlock($nixCodeBlock, 'kate');
$scssCodeBlock = $document->children[24] ?? null;
if (!$scssCodeBlock instanceof PortLibs\Pandoc\AstNode || $scssCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an SCSS code block');
}
$scss = $highlighter->highlightCodeBlock($scssCodeBlock, 'espresso');
$scssWordpressBlock = $highlighter->wordpressHtmlBlock($scssCodeBlock, 'espresso');
$goCodeBlock = $document->children[25] ?? null;
if (!$goCodeBlock instanceof PortLibs\Pandoc\AstNode || $goCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Go code block');
}
$go = $highlighter->highlightCodeBlock($goCodeBlock, 'tango');
$goWordpressBlock = $highlighter->wordpressHtmlBlock($goCodeBlock, 'tango');
$powershellCodeBlock = $document->children[26] ?? null;
if (!$powershellCodeBlock instanceof PortLibs\Pandoc\AstNode || $powershellCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a PowerShell code block');
}
$powershell = $highlighter->highlightCodeBlock($powershellCodeBlock, 'breezedark');
$powershellWordpressBlock = $highlighter->wordpressHtmlBlock($powershellCodeBlock, 'breezedark');
$dotCodeBlock = $document->children[27] ?? null;
if (!$dotCodeBlock instanceof PortLibs\Pandoc\AstNode || $dotCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Graphviz DOT code block');
}
$dot = $highlighter->highlightCodeBlock($dotCodeBlock, 'monochrome');
$dotWordpressBlock = $highlighter->wordpressHtmlBlock($dotCodeBlock, 'monochrome');
$javascriptCodeBlock = $document->children[28] ?? null;
if (!$javascriptCodeBlock instanceof PortLibs\Pandoc\AstNode || $javascriptCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a JavaScript module code block');
}
$javascript = $highlighter->highlightCodeBlock($javascriptCodeBlock, 'kate');
$javascriptWordpressBlock = $highlighter->wordpressHtmlBlock($javascriptCodeBlock, 'kate');
$csharpCodeBlock = $document->children[29] ?? null;
if (!$csharpCodeBlock instanceof PortLibs\Pandoc\AstNode || $csharpCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a C# code block');
}
$csharp = $highlighter->highlightCodeBlock($csharpCodeBlock, 'haddock');
$csharpWordpressBlock = $highlighter->wordpressHtmlBlock($csharpCodeBlock, 'haddock');
$sqlCodeBlock = $document->children[30] ?? null;
if (!$sqlCodeBlock instanceof PortLibs\Pandoc\AstNode || $sqlCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a SQL migration code block');
}
$sql = $highlighter->highlightCodeBlock($sqlCodeBlock, 'tango');
$sqlWordpressBlock = $highlighter->wordpressHtmlBlock($sqlCodeBlock, 'tango');
$postgresqlCodeBlock = $document->children[31] ?? null;
if (!$postgresqlCodeBlock instanceof PortLibs\Pandoc\AstNode || $postgresqlCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a PostgreSQL trigger code block');
}
$postgresql = $highlighter->highlightCodeBlock($postgresqlCodeBlock, 'breezedark');
$postgresqlWordpressBlock = $highlighter->wordpressHtmlBlock($postgresqlCodeBlock, 'breezedark');
$apacheCodeBlock = $document->children[32] ?? null;
if (!$apacheCodeBlock instanceof PortLibs\Pandoc\AstNode || $apacheCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an htaccess rewrite code block');
}
$apache = $highlighter->highlightCodeBlock($apacheCodeBlock, 'kate');
$apacheWordpressBlock = $highlighter->wordpressHtmlBlock($apacheCodeBlock, 'kate');
$luaLongBracketCodeBlock = $document->children[33] ?? null;
if (!$luaLongBracketCodeBlock instanceof PortLibs\Pandoc\AstNode || $luaLongBracketCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Lua long-bracket code block');
}
$luaLongBracket = $highlighter->highlightCodeBlock($luaLongBracketCodeBlock, 'breezedark');
$luaLongBracketWordpressBlock = $highlighter->wordpressHtmlBlock($luaLongBracketCodeBlock, 'breezedark');
$phpHeredocCodeBlock = $document->children[34] ?? null;
if (!$phpHeredocCodeBlock instanceof PortLibs\Pandoc\AstNode || $phpHeredocCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a PHP heredoc code block');
}
$phpHeredoc = $highlighter->highlightCodeBlock($phpHeredocCodeBlock, 'pygments');
$phpHeredocWordpressBlock = $highlighter->wordpressHtmlBlock($phpHeredocCodeBlock, 'pygments');
$rstCodeBlock = $document->children[35] ?? null;
if (!$rstCodeBlock instanceof PortLibs\Pandoc\AstNode || $rstCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a reStructuredText code block');
}
$rst = $highlighter->highlightCodeBlock($rstCodeBlock, 'haddock');
$rstWordpressBlock = $highlighter->wordpressHtmlBlock($rstCodeBlock, 'haddock');
$tsxCodeBlock = $document->children[36] ?? null;
if (!$tsxCodeBlock instanceof PortLibs\Pandoc\AstNode || $tsxCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a TSX code block');
}
$tsx = $highlighter->highlightCodeBlock($tsxCodeBlock, 'kate');
$tsxWordpressBlock = $highlighter->wordpressHtmlBlock($tsxCodeBlock, 'kate');
$cmakeCodeBlock = $document->children[37] ?? null;
if (!$cmakeCodeBlock instanceof PortLibs\Pandoc\AstNode || $cmakeCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a CMake code block');
}
$cmake = $highlighter->highlightCodeBlock($cmakeCodeBlock, 'zenburn');
$cmakeWordpressBlock = $highlighter->wordpressHtmlBlock($cmakeCodeBlock, 'zenburn');
$nginxCodeBlock = $document->children[38] ?? null;
if (!$nginxCodeBlock instanceof PortLibs\Pandoc\AstNode || $nginxCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an Nginx code block');
}
$nginx = $highlighter->highlightCodeBlock($nginxCodeBlock, 'tango');
$nginxWordpressBlock = $highlighter->wordpressHtmlBlock($nginxCodeBlock, 'tango');
$twigCodeBlock = $document->children[39] ?? null;
if (!$twigCodeBlock instanceof PortLibs\Pandoc\AstNode || $twigCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Twig template code block');
}
$twig = $highlighter->highlightCodeBlock($twigCodeBlock, 'espresso');
$twigWordpressBlock = $highlighter->wordpressHtmlBlock($twigCodeBlock, 'espresso');
$handlebarsCodeBlock = $document->children[40] ?? null;
if (!$handlebarsCodeBlock instanceof PortLibs\Pandoc\AstNode || $handlebarsCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Handlebars template code block');
}
$handlebars = $highlighter->highlightCodeBlock($handlebarsCodeBlock, 'kate');
$handlebarsWordpressBlock = $highlighter->wordpressHtmlBlock($handlebarsCodeBlock, 'kate');
$mermaidCodeBlock = $document->children[41] ?? null;
if (!$mermaidCodeBlock instanceof PortLibs\Pandoc\AstNode || $mermaidCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Mermaid diagram code block');
}
$mermaid = $highlighter->highlightCodeBlock($mermaidCodeBlock, 'tango');
$mermaidWordpressBlock = $highlighter->wordpressHtmlBlock($mermaidCodeBlock, 'tango');
$htmlEmbeddedCodeBlock = $document->children[42] ?? null;
if (!$htmlEmbeddedCodeBlock instanceof PortLibs\Pandoc\AstNode || $htmlEmbeddedCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an embedded HTML asset code block');
}
$htmlEmbedded = $highlighter->highlightCodeBlock($htmlEmbeddedCodeBlock, 'pygments');
$htmlEmbeddedWordpressBlock = $highlighter->wordpressHtmlBlock($htmlEmbeddedCodeBlock, 'pygments');
$htmlPhpCodeBlock = $document->children[43] ?? null;
if (!$htmlPhpCodeBlock instanceof PortLibs\Pandoc\AstNode || $htmlPhpCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an HTML/PHP template code block');
}
$htmlPhp = $highlighter->highlightCodeBlock($htmlPhpCodeBlock, 'breezedark');
$htmlPhpWordpressBlock = $highlighter->wordpressHtmlBlock($htmlPhpCodeBlock, 'breezedark');
$graphqlCodeBlock = $document->children[44] ?? null;
if (!$graphqlCodeBlock instanceof PortLibs\Pandoc\AstNode || $graphqlCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a GraphQL code block');
}
$graphql = $highlighter->highlightCodeBlock($graphqlCodeBlock, 'kate');
$graphqlWordpressBlock = $highlighter->wordpressHtmlBlock($graphqlCodeBlock, 'kate');
$phpAttributeCodeBlock = $document->children[45] ?? null;
if (!$phpAttributeCodeBlock instanceof PortLibs\Pandoc\AstNode || $phpAttributeCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a PHP attribute code block');
}
$phpAttribute = $highlighter->highlightCodeBlock($phpAttributeCodeBlock, 'pygments');
$phpAttributeWordpressBlock = $highlighter->wordpressHtmlBlock($phpAttributeCodeBlock, 'pygments');
$asciidocCodeBlock = $document->children[46] ?? null;
if (!$asciidocCodeBlock instanceof PortLibs\Pandoc\AstNode || $asciidocCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an AsciiDoc code block');
}
$asciidoc = $highlighter->highlightCodeBlock($asciidocCodeBlock, 'haddock');
$asciidocWordpressBlock = $highlighter->wordpressHtmlBlock($asciidocCodeBlock, 'haddock');
$phpdocCodeBlock = $document->children[47] ?? null;
if (!$phpdocCodeBlock instanceof PortLibs\Pandoc\AstNode || $phpdocCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a PHPDoc code block');
}
$phpdoc = $highlighter->highlightCodeBlock($phpdocCodeBlock, 'pygments');
$phpdocWordpressBlock = $highlighter->wordpressHtmlBlock($phpdocCodeBlock, 'pygments');
$terraformCodeBlock = $document->children[48] ?? null;
if (!$terraformCodeBlock instanceof PortLibs\Pandoc\AstNode || $terraformCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Terraform HCL code block');
}
$terraform = $highlighter->highlightCodeBlock($terraformCodeBlock, 'monochrome');
$terraformWordpressBlock = $highlighter->wordpressHtmlBlock($terraformCodeBlock, 'monochrome');
$liquidCodeBlock = $document->children[49] ?? null;
if (!$liquidCodeBlock instanceof PortLibs\Pandoc\AstNode || $liquidCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Liquid code block');
}
$liquid = $highlighter->highlightCodeBlock($liquidCodeBlock, 'tango');
$liquidWordpressBlock = $highlighter->wordpressHtmlBlock($liquidCodeBlock, 'tango');
$elmCodeBlock = $document->children[50] ?? null;
if (!$elmCodeBlock instanceof PortLibs\Pandoc\AstNode || $elmCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an Elm code block');
}
$elm = $highlighter->highlightCodeBlock($elmCodeBlock, 'breezedark');
$elmWordpressBlock = $highlighter->wordpressHtmlBlock($elmCodeBlock, 'breezedark');
$jsoncCodeBlock = $document->children[51] ?? null;
if (!$jsoncCodeBlock instanceof PortLibs\Pandoc\AstNode || $jsoncCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a JSON-with-comments code block');
}
$jsonc = $highlighter->highlightCodeBlock($jsoncCodeBlock, 'kate');
$jsoncWordpressBlock = $highlighter->wordpressHtmlBlock($jsoncCodeBlock, 'kate');
$lessCodeBlock = $document->children[52] ?? null;
if (!$lessCodeBlock instanceof PortLibs\Pandoc\AstNode || $lessCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a LESS code block');
}
$less = $highlighter->highlightCodeBlock($lessCodeBlock, 'espresso');
$lessWordpressBlock = $highlighter->wordpressHtmlBlock($lessCodeBlock, 'espresso');
$typstCodeBlock = $document->children[53] ?? null;
if (!$typstCodeBlock instanceof PortLibs\Pandoc\AstNode || $typstCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Typst code block');
}
$typst = $highlighter->highlightCodeBlock($typstCodeBlock, 'haddock');
$typstWordpressBlock = $highlighter->wordpressHtmlBlock($typstCodeBlock, 'haddock');
$kotlinCodeBlock = $document->children[54] ?? null;
if (!$kotlinCodeBlock instanceof PortLibs\Pandoc\AstNode || $kotlinCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Kotlin code block');
}
$kotlin = $highlighter->highlightCodeBlock($kotlinCodeBlock, 'breezedark');
$kotlinWordpressBlock = $highlighter->wordpressHtmlBlock($kotlinCodeBlock, 'breezedark');
$scalaCodeBlock = $document->children[58] ?? null;
if (!$scalaCodeBlock instanceof PortLibs\Pandoc\AstNode || $scalaCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Scala code block');
}
$scala = $highlighter->highlightCodeBlock($scalaCodeBlock, 'zenburn');
$scalaWordpressBlock = $highlighter->wordpressHtmlBlock($scalaCodeBlock, 'zenburn');
$elixirCodeBlock = $document->children[59] ?? null;
if (!$elixirCodeBlock instanceof PortLibs\Pandoc\AstNode || $elixirCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an Elixir code block');
}
$elixir = $highlighter->highlightCodeBlock($elixirCodeBlock, 'tango');
$elixirWordpressBlock = $highlighter->wordpressHtmlBlock($elixirCodeBlock, 'tango');
$vueCodeBlock = $document->children[60] ?? null;
if (!$vueCodeBlock instanceof PortLibs\Pandoc\AstNode || $vueCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Vue SFC code block');
}
$vue = $highlighter->highlightCodeBlock($vueCodeBlock, 'breezedark');
$vueWordpressBlock = $highlighter->wordpressHtmlBlock($vueCodeBlock, 'breezedark');
$vueCustomCodeBlock = $document->children[63] ?? null;
if (!$vueCustomCodeBlock instanceof PortLibs\Pandoc\AstNode || $vueCustomCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Vue custom-block code block');
}
$vueCustom = $highlighter->highlightCodeBlock($vueCustomCodeBlock, 'kate');
$vueCustomWordpressBlock = $highlighter->wordpressHtmlBlock($vueCustomCodeBlock, 'kate');
$ocamlCodeBlock = $document->children[61] ?? null;
if (!$ocamlCodeBlock instanceof PortLibs\Pandoc\AstNode || $ocamlCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an OCaml code block');
}
$ocaml = $highlighter->highlightCodeBlock($ocamlCodeBlock, 'monochrome');
$ocamlWordpressBlock = $highlighter->wordpressHtmlBlock($ocamlCodeBlock, 'monochrome');
$juliaCodeBlock = $document->children[62] ?? null;
if (!$juliaCodeBlock instanceof PortLibs\Pandoc\AstNode || $juliaCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Julia code block');
}
$julia = $highlighter->highlightCodeBlock($juliaCodeBlock, 'kate');
$juliaWordpressBlock = $highlighter->wordpressHtmlBlock($juliaCodeBlock, 'kate');
$awkCodeBlock = $document->children[64] ?? null;
if (!$awkCodeBlock instanceof PortLibs\Pandoc\AstNode || $awkCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an AWK review filter code block');
}
$awk = $highlighter->highlightCodeBlock($awkCodeBlock, 'tango');
$awkWordpressBlock = $highlighter->wordpressHtmlBlock($awkCodeBlock, 'tango');
$batchCodeBlock = $document->children[65] ?? null;
if (!$batchCodeBlock instanceof PortLibs\Pandoc\AstNode || $batchCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Windows batch review script code block');
}
$batch = $highlighter->highlightCodeBlock($batchCodeBlock, 'breezedark');
$batchWordpressBlock = $highlighter->wordpressHtmlBlock($batchCodeBlock, 'breezedark');
$matlabCodeBlock = $document->children[66] ?? null;
if (!$matlabCodeBlock instanceof PortLibs\Pandoc\AstNode || $matlabCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a MATLAB technical review code block');
}
$matlab = $highlighter->highlightCodeBlock($matlabCodeBlock, 'monochrome');
$matlabWordpressBlock = $highlighter->wordpressHtmlBlock($matlabCodeBlock, 'monochrome');
$fishCodeBlock = $document->children[67] ?? null;
if (!$fishCodeBlock instanceof PortLibs\Pandoc\AstNode || $fishCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Fish shell review code block');
}
$fish = $highlighter->highlightCodeBlock($fishCodeBlock, 'haddock');
$fishWordpressBlock = $highlighter->wordpressHtmlBlock($fishCodeBlock, 'haddock');
$sedCodeBlock = $document->children[68] ?? null;
if (!$sedCodeBlock instanceof PortLibs\Pandoc\AstNode || $sedCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Sed stream editor review code block');
}
$sed = $highlighter->highlightCodeBlock($sedCodeBlock, 'tango');
$sedWordpressBlock = $highlighter->wordpressHtmlBlock($sedCodeBlock, 'tango');
$bibtexCodeBlock = $document->children[69] ?? null;
if (!$bibtexCodeBlock instanceof PortLibs\Pandoc\AstNode || $bibtexCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a BibTeX review code block');
}
$bibtex = $highlighter->highlightCodeBlock($bibtexCodeBlock, 'zenburn');
$bibtexWordpressBlock = $highlighter->wordpressHtmlBlock($bibtexCodeBlock, 'zenburn');
$vimCodeBlock = $document->children[70] ?? null;
if (!$vimCodeBlock instanceof PortLibs\Pandoc\AstNode || $vimCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Vimscript review code block');
}
$vim = $highlighter->highlightCodeBlock($vimCodeBlock, 'monochrome');
$vimWordpressBlock = $highlighter->wordpressHtmlBlock($vimCodeBlock, 'monochrome');
$schemeCodeBlock = $document->children[71] ?? null;
if (!$schemeCodeBlock instanceof PortLibs\Pandoc\AstNode || $schemeCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Scheme/Racket review code block');
}
$scheme = $highlighter->highlightCodeBlock($schemeCodeBlock, 'espresso');
$schemeWordpressBlock = $highlighter->wordpressHtmlBlock($schemeCodeBlock, 'espresso');
$csvCodeBlock = $document->children[72] ?? null;
if (!$csvCodeBlock instanceof PortLibs\Pandoc\AstNode || $csvCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a CSV import review code block');
}
$csv = $highlighter->highlightCodeBlock($csvCodeBlock, 'tango');
$csvWordpressBlock = $highlighter->wordpressHtmlBlock($csvCodeBlock, 'tango');
$erlangCodeBlock = $document->children[73] ?? null;
if (!$erlangCodeBlock instanceof PortLibs\Pandoc\AstNode || $erlangCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an Erlang review code block');
}
$erlang = $highlighter->highlightCodeBlock($erlangCodeBlock, 'zenburn');
$erlangWordpressBlock = $highlighter->wordpressHtmlBlock($erlangCodeBlock, 'zenburn');
$objectiveCCodeBlock = $document->children[74] ?? null;
if (!$objectiveCCodeBlock instanceof PortLibs\Pandoc\AstNode || $objectiveCCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an Objective-C review code block');
}
$objectiveC = $highlighter->highlightCodeBlock($objectiveCCodeBlock, 'haddock');
$objectiveCWordpressBlock = $highlighter->wordpressHtmlBlock($objectiveCCodeBlock, 'haddock');
$rakuCodeBlock = $document->children[75] ?? null;
if (!$rakuCodeBlock instanceof PortLibs\Pandoc\AstNode || $rakuCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Raku review code block');
}
$raku = $highlighter->highlightCodeBlock($rakuCodeBlock, 'breezedark');
$rakuWordpressBlock = $highlighter->wordpressHtmlBlock($rakuCodeBlock, 'breezedark');
$fennelCodeBlock = $document->children[76] ?? null;
if (!$fennelCodeBlock instanceof PortLibs\Pandoc\AstNode || $fennelCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Fennel review code block');
}
$fennel = $highlighter->highlightCodeBlock($fennelCodeBlock, 'zenburn');
$fennelWordpressBlock = $highlighter->wordpressHtmlBlock($fennelCodeBlock, 'zenburn');
$mesonCodeBlock = $document->children[77] ?? null;
if (!$mesonCodeBlock instanceof PortLibs\Pandoc\AstNode || $mesonCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Meson build review code block');
}
$meson = $highlighter->highlightCodeBlock($mesonCodeBlock, 'monochrome');
$mesonWordpressBlock = $highlighter->wordpressHtmlBlock($mesonCodeBlock, 'monochrome');
$justCodeBlock = $document->children[78] ?? null;
if (!$justCodeBlock instanceof PortLibs\Pandoc\AstNode || $justCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Justfile review code block');
}
$just = $highlighter->highlightCodeBlock($justCodeBlock, 'haddock');
$justWordpressBlock = $highlighter->wordpressHtmlBlock($justCodeBlock, 'haddock');
$protobufCodeBlock = $document->children[79] ?? null;
if (!$protobufCodeBlock instanceof PortLibs\Pandoc\AstNode || $protobufCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Protobuf review schema code block');
}
$protobuf = $highlighter->highlightCodeBlock($protobufCodeBlock, 'tango');
$protobufWordpressBlock = $highlighter->wordpressHtmlBlock($protobufCodeBlock, 'tango');
$tclCodeBlock = $document->children[80] ?? null;
if (!$tclCodeBlock instanceof PortLibs\Pandoc\AstNode || $tclCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Tcl import review script code block');
}
$tcl = $highlighter->highlightCodeBlock($tclCodeBlock, 'breezedark');
$tclWordpressBlock = $highlighter->wordpressHtmlBlock($tclCodeBlock, 'breezedark');
$lineHighlightCodeBlock = $document->children[81] ?? null;
if (!$lineHighlightCodeBlock instanceof PortLibs\Pandoc\AstNode || $lineHighlightCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a line-highlighted PHP review code block');
}
$lineHighlight = $highlighter->highlightCodeBlock($lineHighlightCodeBlock, 'kate');
$lineHighlightWordpressBlock = $highlighter->wordpressHtmlBlock($lineHighlightCodeBlock, 'kate');
$fortranCodeBlock = $document->children[82] ?? null;
if (!$fortranCodeBlock instanceof PortLibs\Pandoc\AstNode || $fortranCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Fortran review code block');
}
$fortran = $highlighter->highlightCodeBlock($fortranCodeBlock, 'zenburn');
$fortranWordpressBlock = $highlighter->wordpressHtmlBlock($fortranCodeBlock, 'zenburn');
$dCodeBlock = $document->children[83] ?? null;
if (!$dCodeBlock instanceof PortLibs\Pandoc\AstNode || $dCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a D review module code block');
}
$d = $highlighter->highlightCodeBlock($dCodeBlock, 'haddock');
$dWordpressBlock = $highlighter->wordpressHtmlBlock($dCodeBlock, 'haddock');
$commonLispCodeBlock = $document->children[84] ?? null;
if (!$commonLispCodeBlock instanceof PortLibs\Pandoc\AstNode || $commonLispCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Common Lisp review packet code block');
}
$commonLisp = $highlighter->highlightCodeBlock($commonLispCodeBlock, 'monochrome');
$commonLispWordpressBlock = $highlighter->wordpressHtmlBlock($commonLispCodeBlock, 'monochrome');
$pascalCodeBlock = $document->children[85] ?? null;
if (!$pascalCodeBlock instanceof PortLibs\Pandoc\AstNode || $pascalCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Pascal review packet code block');
}
$pascal = $highlighter->highlightCodeBlock($pascalCodeBlock, 'haddock');
$pascalWordpressBlock = $highlighter->wordpressHtmlBlock($pascalCodeBlock, 'haddock');
$groovyCodeBlock = $document->children[86] ?? null;
if (!$groovyCodeBlock instanceof PortLibs\Pandoc\AstNode || $groovyCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Groovy/Gradle review packet code block');
}
$groovy = $highlighter->highlightCodeBlock($groovyCodeBlock, 'zenburn');
$groovyWordpressBlock = $highlighter->wordpressHtmlBlock($groovyCodeBlock, 'zenburn');
$crystalCodeBlock = $document->children[87] ?? null;
if (!$crystalCodeBlock instanceof PortLibs\Pandoc\AstNode || $crystalCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Crystal review packet code block');
}
$crystal = $highlighter->highlightCodeBlock($crystalCodeBlock, 'espresso');
$crystalWordpressBlock = $highlighter->wordpressHtmlBlock($crystalCodeBlock, 'espresso');
$shellSessionCodeBlock = $document->children[88] ?? null;
if (!$shellSessionCodeBlock instanceof PortLibs\Pandoc\AstNode || $shellSessionCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a shell-session transcript code block');
}
$shellSession = $highlighter->highlightCodeBlock($shellSessionCodeBlock, 'tango');
$shellSessionWordpressBlock = $highlighter->wordpressHtmlBlock($shellSessionCodeBlock, 'tango');
$nimCodeBlock = $document->children[89] ?? null;
if (!$nimCodeBlock instanceof PortLibs\Pandoc\AstNode || $nimCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Nim review packet code block');
}
$nim = $highlighter->highlightCodeBlock($nimCodeBlock, 'monochrome');
$nimWordpressBlock = $highlighter->wordpressHtmlBlock($nimCodeBlock, 'monochrome');
$vCodeBlock = $document->children[90] ?? null;
if (!$vCodeBlock instanceof PortLibs\Pandoc\AstNode || $vCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a V review packet code block');
}
$v = $highlighter->highlightCodeBlock($vCodeBlock, 'haddock');
$vWordpressBlock = $highlighter->wordpressHtmlBlock($vCodeBlock, 'haddock');
$idrisCodeBlock = $document->children[91] ?? null;
if (!$idrisCodeBlock instanceof PortLibs\Pandoc\AstNode || $idrisCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an Idris review packet code block');
}
$idris = $highlighter->highlightCodeBlock($idrisCodeBlock, 'zenburn');
$idrisWordpressBlock = $highlighter->wordpressHtmlBlock($idrisCodeBlock, 'zenburn');
$coqCodeBlock = $document->children[92] ?? null;
if (!$coqCodeBlock instanceof PortLibs\Pandoc\AstNode || $coqCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Coq proof review code block');
}
$coq = $highlighter->highlightCodeBlock($coqCodeBlock, 'tango');
$coqWordpressBlock = $highlighter->wordpressHtmlBlock($coqCodeBlock, 'tango');
$agdaCodeBlock = $document->children[93] ?? null;
if (!$agdaCodeBlock instanceof PortLibs\Pandoc\AstNode || $agdaCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an Agda proof review code block');
}
$agda = $highlighter->highlightCodeBlock($agdaCodeBlock, 'espresso');
$agdaWordpressBlock = $highlighter->wordpressHtmlBlock($agdaCodeBlock, 'espresso');
$purescriptCodeBlock = $document->children[94] ?? null;
if (!$purescriptCodeBlock instanceof PortLibs\Pandoc\AstNode || $purescriptCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a PureScript review code block');
}
$purescript = $highlighter->highlightCodeBlock($purescriptCodeBlock, 'espresso');
$purescriptWordpressBlock = $highlighter->wordpressHtmlBlock($purescriptCodeBlock, 'espresso');
$fsharpCodeBlock = $document->children[95] ?? null;
if (!$fsharpCodeBlock instanceof PortLibs\Pandoc\AstNode || $fsharpCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include an F# review code block');
}
$fsharp = $highlighter->highlightCodeBlock($fsharpCodeBlock, 'kate');
$fsharpWordpressBlock = $highlighter->wordpressHtmlBlock($fsharpCodeBlock, 'kate');
$rakuPodQuoteCodeBlock = $document->children[96] ?? null;
if (!$rakuPodQuoteCodeBlock instanceof PortLibs\Pandoc\AstNode || $rakuPodQuoteCodeBlock->type !== 'code_block') {
    throw new RuntimeException('Expected syntax highlight fixture to include a Raku POD quote review code block');
}
$rakuPodQuote = $highlighter->highlightCodeBlock($rakuPodQuoteCodeBlock, 'breezedark');
$rakuPodQuoteWordpressBlock = $highlighter->wordpressHtmlBlock($rakuPodQuoteCodeBlock, 'breezedark');
$customThemeJson = json_encode([
    'name' => 'Review Import',
    'text-color' => '#f8f8f2',
    'background-color' => '#101820',
    'line-number-color' => '#8f9aae',
    'line-number-background-color' => '#202a35',
    'token-styles' => [
        'KeywordTok' => ['text-color' => '#ffcc00', 'bold' => true],
        'StringTok' => ['text-color' => '#7bd88f'],
        'CommentTok' => ['text-color' => '#7f8c8d', 'italic' => true],
        'FunctionTok' => ['text-color' => '#80dfff', 'underline' => true],
        'VariableTok' => ['text-color' => '#ff9f43'],
        'OperatorTok' => ['text-color' => '#ff6b6b'],
    ],
], JSON_THROW_ON_ERROR);
$customThemeCodeBlock = new AstNode('code_block', [
    'id' => 'custom-theme-review',
    'classes' => ['php', 'numberLines'],
    'attributes' => ['startFrom' => '10'],
    'text' => 'echo esc_html($title); // review',
]);
$customTheme = $highlighter->highlightCodeBlock($customThemeCodeBlock, 'pygments', ['themeJson' => $customThemeJson]);
$customThemeWordpressBlock = $highlighter->wordpressHtmlBlock($customThemeCodeBlock, 'pygments', ['themeJson' => $customThemeJson]);

if (($argv[1] ?? '') === '--self-test') {
    if (($highlighted['language'] ?? '') !== 'php') {
        throw new RuntimeException('Expected PHP language alias handoff');
    }
    if (!str_contains($highlighted['html'], '<span class="kw">function</span>')) {
        throw new RuntimeException('Expected keyword token span');
    }
    if (!str_contains($highlighted['html'], '<span class="fu">render_title</span>')) {
        throw new RuntimeException('Expected function token span');
    }
    if (!str_contains($wordpressBlock, '<style data-pandoc-highlight-style="pygments">')) {
        throw new RuntimeException('Expected WordPress highlight style metadata');
    }
    if (!str_contains($writerHighlightedBlocks, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected WordPress writer highlight style metadata');
    }
    if (!str_contains($writerHighlightedBlocks, '<pre class="sourceCode php"><code class="sourceCode php">')) {
        throw new RuntimeException('Expected WordPress writer sourceCode handoff');
    }
    if (($numbered['lineNumbering']['start'] ?? null) !== 42) {
        throw new RuntimeException('Expected Pandoc startFrom line-number handoff');
    }
    if (!str_contains($numberedWordpressBlock, '<pre class="sourceCode numberSource php numberLines lineAnchors">')) {
        throw new RuntimeException('Expected Pandoc numberSource class handoff');
    }
    if (!str_contains($numberedWordpressBlock, '<span id="migration-review-42"><a href="#migration-review-42"></a>')) {
        throw new RuntimeException('Expected Pandoc line anchor handoff');
    }
    if (($haskell['language'] ?? '') !== 'haskell') {
        throw new RuntimeException('Expected literate Haskell alias handoff');
    }
    if (!str_contains($haskell['html'], '<span class="kw">module</span> <span class="dt">Review.Import</span>')) {
        throw new RuntimeException('Expected Haskell module token handoff');
    }
    if (!str_contains($haskellWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Haskell WordPress style metadata');
    }
    if (!str_contains($haskellWordpressBlock, '<span class="cn">Just</span> <span class="dv">42</span>')) {
        throw new RuntimeException('Expected Haskell constructor and number token handoff');
    }
    if (($latex['language'] ?? '') !== 'tex') {
        throw new RuntimeException('Expected LaTeX alias to normalize to TeX');
    }
    if (!str_contains($latex['html'], '<span class="kw">\\documentclass</span>')) {
        throw new RuntimeException('Expected TeX documentclass token handoff');
    }
    if (!str_contains($latex['html'], '<span class="va">$title$</span>')) {
        throw new RuntimeException('Expected Pandoc template variable token handoff inside TeX');
    }
    if (!str_contains($latexWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected TeX WordPress style metadata');
    }
    if (!str_contains($latexWordpressBlock, '<span class="fu">\\includegraphics</span>')) {
        throw new RuntimeException('Expected TeX includegraphics token handoff');
    }
    if (($diff['language'] ?? '') !== 'diff') {
        throw new RuntimeException('Expected patch alias to normalize to diff');
    }
    if (($diff['lineNumbering']['start'] ?? null) !== 9) {
        throw new RuntimeException('Expected diff source startFrom line-number handoff');
    }
    if (!str_contains($diff['html'], '<pre class="sourceCode numberSource patch numberLines"><code class="sourceCode diff" style="counter-reset: source-line 8;">')) {
        throw new RuntimeException('Expected numbered diff source wrapper handoff');
    }
    if (!str_contains($diff['html'], '<span class="re">diff --git a/content.php b/content.php</span>')) {
        throw new RuntimeException('Expected diff header token handoff');
    }
    if (!str_contains($diff['html'], '<span class="al">-echo $old_title;</span>')) {
        throw new RuntimeException('Expected deleted diff line token handoff');
    }
    if (!str_contains($diff['html'], '<span class="in">+echo esc_html($new_title);</span>')) {
        throw new RuntimeException('Expected added diff line token handoff');
    }
    if (!str_contains($diffWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected diff WordPress style metadata');
    }
    if (!str_contains($diffWordpressBlock, '<span class="co">\ No newline at end of file</span>')) {
        throw new RuntimeException('Expected diff no-newline diagnostic token handoff');
    }
    if (($markdown['language'] ?? '') !== 'markdown') {
        throw new RuntimeException('Expected md alias to normalize to Markdown highlighting');
    }
    if (($markdown['lineNumbering']['start'] ?? null) !== 5) {
        throw new RuntimeException('Expected Markdown source startFrom line-number handoff');
    }
    if (!str_contains($markdown['html'], '<span class="re"># Migration Review</span>')) {
        throw new RuntimeException('Expected Markdown heading token handoff');
    }
    if (!str_contains($markdown['html'], '<span class="op">- </span><span class="cn">[x]</span> Preserve <span class="ot">[media](uploads/hero.png)</span>')) {
        throw new RuntimeException('Expected Markdown task-list and link token handoff');
    }
    if (!str_contains($markdown['html'], '<span class="st">`legacy_shortcode`</span>')) {
        throw new RuntimeException('Expected Markdown code-span token handoff');
    }
    if (!str_contains($markdownWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected Markdown WordPress style metadata');
    }
    if (!str_contains($markdownWordpressBlock, '<span class="pp">``` {.php}</span>')) {
        throw new RuntimeException('Expected nested Markdown fence token handoff');
    }
    if (($ruby['language'] ?? '') !== 'ruby') {
        throw new RuntimeException('Expected rb alias to normalize to Ruby highlighting');
    }
    if (!str_contains($ruby['html'], '<span class="fu">require</span> <span class="st">&#039;json&#039;</span>')) {
        throw new RuntimeException('Expected Ruby require token handoff');
    }
    if (!str_contains($ruby['html'], '<span class="kw">class</span> <span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected Ruby class token handoff');
    }
    if (!str_contains($ruby['html'], '<span class="va">@path</span> <span class="op">=</span> <span class="va">path</span>')) {
        throw new RuntimeException('Expected Ruby instance/local variable token handoff');
    }
    if (!str_contains($rubyWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected Ruby WordPress style metadata');
    }
    if (!str_contains($rubyWordpressBlock, '<span class="dt">JSON</span><span class="op">.</span><span class="fu">parse</span>')) {
        throw new RuntimeException('Expected Ruby constant and method token handoff');
    }
    if (($lua['language'] ?? '') !== 'lua') {
        throw new RuntimeException('Expected pandoc-lua alias to normalize to Lua highlighting');
    }
    if (($lua['lineNumbering']['start'] ?? null) !== 3) {
        throw new RuntimeException('Expected Lua source startFrom line-number handoff');
    }
    if (!str_contains($lua['html'], '<span class="kw">function</span> <span class="fu">Header</span>')) {
        throw new RuntimeException('Expected Lua function token handoff');
    }
    if (!str_contains($lua['html'], '<span class="dt">pandoc</span><span class="op">.</span><span class="va">utils</span><span class="op">.</span><span class="fu">stringify</span>')) {
        throw new RuntimeException('Expected Lua pandoc.utils method token handoff');
    }
    if (!str_contains($luaWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected Lua WordPress style metadata');
    }
    if (!str_contains($luaWordpressBlock, '<span class="kw">return</span> <span class="dt">pandoc</span><span class="op">.</span><span class="fu">Div</span>')) {
        throw new RuntimeException('Expected Lua pandoc constructor handoff');
    }
    if (($typescript['language'] ?? '') !== 'typescript') {
        throw new RuntimeException('Expected ts alias to normalize to TypeScript highlighting');
    }
    if (($typescript['lineNumbering']['start'] ?? null) !== 12) {
        throw new RuntimeException('Expected TypeScript source startFrom line-number handoff');
    }
    if (!str_contains($typescript['html'], '<span class="kw">type</span> <span class="dt">BlockPayload</span>')) {
        throw new RuntimeException('Expected TypeScript type alias token handoff');
    }
    if (!str_contains($typescript['html'], '<span class="dt">Record</span><span class="op">&lt;</span><span class="dt">string</span><span class="op">,</span> <span class="dt">unknown</span><span class="op">&gt;;</span>')) {
        throw new RuntimeException('Expected TypeScript generic type token handoff');
    }
    if (!str_contains($typescript['html'], '<span class="va">payload</span><span class="op">.</span><span class="va">meta</span><span class="op">?.</span><span class="va">sourceId</span> <span class="op">!==</span> <span class="dt">undefined</span>')) {
        throw new RuntimeException('Expected TypeScript optional chaining token handoff');
    }
    if (!str_contains($typescriptWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected TypeScript WordPress style metadata');
    }
    if (!str_contains($typescriptWordpressBlock, '<span class="kw">export</span> <span class="kw">async</span> <span class="kw">function</span> <span class="fu">migrateBlock</span>')) {
        throw new RuntimeException('Expected TypeScript async function token handoff');
    }
    if (($python['language'] ?? '') !== 'python') {
        throw new RuntimeException('Expected python3 alias to normalize to Python highlighting');
    }
    if (($python['lineNumbering']['start'] ?? null) !== 20) {
        throw new RuntimeException('Expected Python source startFrom line-number handoff');
    }
    if (!str_contains($python['html'], '<span class="ot">@dataclass</span>')) {
        throw new RuntimeException('Expected Python decorator token handoff');
    }
    if (!str_contains($python['html'], '<span class="kw">class</span> <span class="dt">ReviewPacket</span><span class="op">:</span>')) {
        throw new RuntimeException('Expected Python class datatype token handoff');
    }
    if (!str_contains($python['html'], '<span class="kw">def</span> <span class="fu">normalize_title</span>')) {
        throw new RuntimeException('Expected Python function token handoff');
    }
    if (!str_contains($python['html'], '<span class="st">b&quot;\\xef\\xbb\\xbf&quot;</span>')) {
        throw new RuntimeException('Expected Python bytes string prefix token handoff');
    }
    if (!str_contains($python['html'], '<span class="st">rb&quot;legacy-\\d+&quot;</span>')) {
        throw new RuntimeException('Expected Python raw bytes string prefix token handoff');
    }
    if (!str_contains($python['html'], '<span class="va">json</span><span class="op">.</span><span class="fu">loads</span>')) {
        throw new RuntimeException('Expected Python module function token handoff');
    }
    if (!str_contains($pythonWordpressBlock, '<style data-pandoc-highlight-style="monochrome">')) {
        throw new RuntimeException('Expected Python WordPress style metadata');
    }
    if (!str_contains($pythonWordpressBlock, '<span class="kw">return</span> <span class="va">raw</span><span class="op">.</span><span class="fu">strip</span><span class="op">()</span>')) {
        throw new RuntimeException('Expected Python method call token handoff');
    }
    if (($cpp['language'] ?? '') !== 'cpp') {
        throw new RuntimeException('Expected cpp alias to normalize to C++ highlighting');
    }
    if (($cpp['lineNumbering']['start'] ?? null) !== 30) {
        throw new RuntimeException('Expected C++ source startFrom line-number handoff');
    }
    if (!str_contains($cpp['html'], '<span class="pp">#include &lt;string&gt;</span>')) {
        throw new RuntimeException('Expected C++ preprocessor token handoff');
    }
    if (!str_contains($cpp['html'], '<span class="kw">class</span> <span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected C++ class datatype token handoff');
    }
    if (!str_contains($cpp['html'], '<span class="dt">std</span><span class="op">::</span><span class="dt">string</span>')) {
        throw new RuntimeException('Expected C++ std::string token handoff');
    }
    if (!str_contains($cpp['html'], '<span class="kw">return</span> <span class="va">title_</span><span class="op">.</span><span class="fu">empty</span><span class="op">()</span>')) {
        throw new RuntimeException('Expected C++ method-call token handoff');
    }
    if (!str_contains($cppWordpressBlock, '<style data-pandoc-highlight-style="pygments">')) {
        throw new RuntimeException('Expected C++ WordPress style metadata');
    }
    if (!str_contains($cppWordpressBlock, '<span class="st">&quot;Draft&quot;</span>')) {
        throw new RuntimeException('Expected C++ string token handoff');
    }
    if (($dockerfile['language'] ?? '') !== 'dockerfile') {
        throw new RuntimeException('Expected Dockerfile alias to normalize to Dockerfile highlighting');
    }
    if (($dockerfile['lineNumbering']['start'] ?? null) !== 4) {
        throw new RuntimeException('Expected Dockerfile source startFrom line-number handoff');
    }
    if (!str_contains($dockerfile['html'], '<span class="ot"># syntax=docker/dockerfile:1.7</span>')) {
        throw new RuntimeException('Expected Dockerfile syntax directive token handoff');
    }
    if (!str_contains($dockerfile['html'], '<span class="kw">FROM</span> wordpress<span class="op">:</span>php')) {
        throw new RuntimeException('Expected Dockerfile FROM keyword token handoff');
    }
    if (!str_contains($dockerfile['html'], '<span class="op">--from=source</span>')) {
        throw new RuntimeException('Expected Dockerfile option token handoff');
    }
    if (!str_contains($dockerfile['html'], '<span class="fu">php</span> <span class="op">-</span>m <span class="op">|</span> <span class="fu">grep</span> json')) {
        throw new RuntimeException('Expected Dockerfile shell-form command token handoff');
    }
    if (!str_contains($dockerfileWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected Dockerfile WordPress style metadata');
    }
    if (!str_contains($dockerfileWordpressBlock, '<span class="kw">ENV</span> <span class="ot">WORDPRESS_CONFIG_EXTRA</span>')) {
        throw new RuntimeException('Expected Dockerfile environment assignment token handoff');
    }
    if (($makefile['language'] ?? '') !== 'makefile') {
        throw new RuntimeException('Expected Makefile alias to normalize to Makefile highlighting');
    }
    if (($makefile['lineNumbering']['start'] ?? null) !== 6) {
        throw new RuntimeException('Expected Makefile source startFrom line-number handoff');
    }
    if (!str_contains($makefile['html'], '<span class="ot">PLUGIN_VERSION</span> <span class="op">?=</span> <span class="dv">1.2.3</span>')) {
        throw new RuntimeException('Expected Makefile assignment token handoff');
    }
    if (!str_contains($makefile['html'], '<span class="re">assets/build</span><span class="op">:</span>')) {
        throw new RuntimeException('Expected Makefile target token handoff');
    }
    if (!str_contains($makefile['html'], '<span class="fu">wp</span> <span class="va">i18n</span> <span class="va">make-pot</span>')) {
        throw new RuntimeException('Expected Makefile wp-cli recipe token handoff');
    }
    if (!str_contains($makefileWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Makefile WordPress style metadata');
    }
    if (!str_contains($makefileWordpressBlock, '<span class="op">@</span><span class="va">$(WP_CLI)</span>')) {
        throw new RuntimeException('Expected Makefile quiet recipe variable handoff');
    }
    if (($jsx['language'] ?? '') !== 'jsx') {
        throw new RuntimeException('Expected jsx alias to normalize to JSX highlighting');
    }
    if (($jsx['lineNumbering']['start'] ?? null) !== 18) {
        throw new RuntimeException('Expected JSX source startFrom line-number handoff');
    }
    if (!str_contains($jsx['html'], '<span class="kw">import</span> <span class="dt">React</span> <span class="kw">from</span> <span class="st">&#039;react&#039;</span>')) {
        throw new RuntimeException('Expected JSX import token handoff');
    }
    if (!str_contains($jsx['html'], '<span class="kw">return</span> <span class="kw">&lt;section</span> <span class="ot">className</span>')) {
        throw new RuntimeException('Expected JSX element tag and attribute token handoff');
    }
    if (!str_contains($jsx['html'], '<span class="fu">&lt;InnerBlocks</span> <span class="ot">allowedBlocks</span>')) {
        throw new RuntimeException('Expected JSX component tag and attribute token handoff');
    }
    if (!str_contains($jsxWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected JSX WordPress style metadata');
    }
    if (!str_contains($jsxWordpressBlock, '<span class="fu">&lt;InnerBlocks</span>')) {
        throw new RuntimeException('Expected JSX WordPress component token handoff');
    }
    if (($rScript['language'] ?? '') !== 'r') {
        throw new RuntimeException('Expected R alias to normalize to R highlighting');
    }
    if (($rScript['lineNumbering']['start'] ?? null) !== 27) {
        throw new RuntimeException('Expected R source startFrom line-number handoff');
    }
    if (!str_contains($rScript['html'], '<span class="fu">library</span><span class="op">(</span><span class="va">dplyr</span><span class="op">)</span>')) {
        throw new RuntimeException('Expected R function-call token handoff');
    }
    if (!str_contains($rScript['html'], '<span class="va">scores</span> <span class="ot">&lt;-</span> <span class="fu">data.frame</span>')) {
        throw new RuntimeException('Expected R assignment and data.frame token handoff');
    }
    if (!str_contains($rScript['html'], '<span class="va">scores</span> <span class="op">|&gt;</span>')) {
        throw new RuntimeException('Expected R native pipe token handoff');
    }
    if (!str_contains($rScript['html'], '<span class="cn">NA_integer_</span>')) {
        throw new RuntimeException('Expected R typed NA constant handoff');
    }
    if (!str_contains($rScript['html'], '<span class="kw">if</span> <span class="op">(</span><span class="fu">any</span>')) {
        throw new RuntimeException('Expected R control-flow token handoff');
    }
    if (!str_contains($rWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected R WordPress style metadata');
    }
    if (!str_contains($rWordpressBlock, '<span class="fu">mutate</span><span class="op">(</span><span class="ot">slug</span>')) {
        throw new RuntimeException('Expected R WordPress mutate named-argument handoff');
    }
    if (($ini['language'] ?? '') !== 'ini') {
        throw new RuntimeException('Expected INI alias to normalize to INI highlighting');
    }
    if (($ini['lineNumbering']['start'] ?? null) !== 2) {
        throw new RuntimeException('Expected INI source startFrom line-number handoff');
    }
    if (!str_contains($ini['html'], '<span class="kw">[PHP]</span>')) {
        throw new RuntimeException('Expected INI section token handoff');
    }
    if (!str_contains($ini['html'], '<span class="dt">display_errors</span> <span class="op">=</span> <span class="kw">Off</span>')) {
        throw new RuntimeException('Expected INI keyword value token handoff');
    }
    if (!str_contains($ini['html'], '<span class="dt">opcache.enable</span> <span class="op">=</span> <span class="dv">1</span>')) {
        throw new RuntimeException('Expected INI numeric value token handoff');
    }
    if (!str_contains($iniWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected INI WordPress style metadata');
    }
    if (!str_contains($iniWordpressBlock, '<span class="dt">error_reporting</span> <span class="op">=</span> <span class="kw">E_ALL</span>')) {
        throw new RuntimeException('Expected INI PHP constant token handoff');
    }
    if (($toml['language'] ?? '') !== 'toml') {
        throw new RuntimeException('Expected TOML alias to normalize to TOML highlighting');
    }
    if (($toml['lineNumbering']['start'] ?? null) !== 11) {
        throw new RuntimeException('Expected TOML source startFrom line-number handoff');
    }
    if (!str_contains($toml['html'], '<span class="kw">[tool.wordpress-import]</span>')) {
        throw new RuntimeException('Expected TOML table token handoff');
    }
    if (!str_contains($toml['html'], '<span class="dt">enabled</span> <span class="op">=</span> <span class="cn">true</span>')) {
        throw new RuntimeException('Expected TOML boolean token handoff');
    }
    if (!str_contains($toml['html'], '<span class="dt">published_at</span> <span class="op">=</span> <span class="cn">2026-06-05T08:40:00Z</span>')) {
        throw new RuntimeException('Expected TOML datetime token handoff');
    }
    if (!str_contains($toml['html'], '<span class="dt">media_paths</span> <span class="op">=</span> <span class="op">[</span><span class="st">&quot;uploads&quot;</span>')) {
        throw new RuntimeException('Expected TOML array token handoff');
    }
    if (!str_contains($tomlWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected TOML WordPress style metadata');
    }
    if (!str_contains($tomlWordpressBlock, '<span class="dt">palette</span> <span class="op">=</span> <span class="op">{</span> <span class="dt">primary</span>')) {
        throw new RuntimeException('Expected TOML inline table token handoff');
    }
    if (!str_contains($tomlWordpressBlock, '<span class="op">[[</span><span class="dt">theme</span><span class="op">.</span><span class="st">&quot;palette variants&quot;</span><span class="op">]]</span>')) {
        throw new RuntimeException('Expected TOML quoted array-of-tables token handoff');
    }
    if (!str_contains($tomlWordpressBlock, '<span class="dt">created_at</span> <span class="op">=</span> <span class="cn">2026-06-05T08:40:00</span> <span class="co"># local review time</span>')) {
        throw new RuntimeException('Expected TOML local datetime and trailing comment handoff');
    }
    if (!str_contains($tomlWordpressBlock, '<span class="dt">review</span><span class="op">.</span><span class="dt">cutoff</span> <span class="op">=</span> <span class="cn">08:40:00.125</span>')) {
        throw new RuntimeException('Expected TOML dotted key and local time token handoff');
    }
    if (($perl['language'] ?? '') !== 'perl') {
        throw new RuntimeException('Expected pl alias to normalize to Perl highlighting');
    }
    if (($perl['lineNumbering']['start'] ?? null) !== 14) {
        throw new RuntimeException('Expected Perl source startFrom line-number handoff');
    }
    if (!str_contains($perl['html'], '<span class="kw">#!/usr/bin/env perl</span>')) {
        throw new RuntimeException('Expected Perl shebang token handoff');
    }
    if (!str_contains($perl['html'], '<span class="fu">use</span> <span class="kw">strict</span>')) {
        throw new RuntimeException('Expected Perl pragma token handoff');
    }
    if (!str_contains($perl['html'], '<span class="kw">package</span> <span class="dt">WP::ImportReview</span>')) {
        throw new RuntimeException('Expected Perl package token handoff');
    }
    if (!str_contains($perl['html'], '<span class="va">$title</span> <span class="op">=~</span> <span class="st">s/^\\s+|\\s+$//g</span>')) {
        throw new RuntimeException('Expected Perl substitution token handoff');
    }
    if (!str_contains($perl['html'], '<span class="fu">warn</span> <span class="st">&quot;empty title for $packet-&gt;{id}&quot;</span>')) {
        throw new RuntimeException('Expected Perl warn string token handoff');
    }
    if (!str_contains($perlWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Perl WordPress style metadata');
    }
    if (!str_contains($perlWordpressBlock, '<span class="kw">return</span> <span class="fu">lc</span> <span class="va">$title</span>')) {
        throw new RuntimeException('Expected Perl return/function token handoff');
    }
    if (($java['language'] ?? '') !== 'java') {
        throw new RuntimeException('Expected Java alias to normalize to Java highlighting');
    }
    if (($java['lineNumbering']['start'] ?? null) !== 21) {
        throw new RuntimeException('Expected Java source startFrom line-number handoff');
    }
    if (!str_contains($java['html'], '<span class="kw">public</span> <span class="kw">final</span> <span class="kw">class</span> <span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected Java class token handoff');
    }
    if (!str_contains($java['html'], '<span class="dt">Files</span><span class="op">.</span><span class="fu">readString</span>')) {
        throw new RuntimeException('Expected Java static method token handoff');
    }
    if (!str_contains($java['html'], '<span class="ot">@Deprecated</span>')) {
        throw new RuntimeException('Expected Java annotation token handoff');
    }
    if (!str_contains($javaWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected Java WordPress style metadata');
    }
    if (!str_contains($javaWordpressBlock, '<span class="dt">Optional</span><span class="op">.</span><span class="fu">empty</span><span class="op">();</span>')) {
        throw new RuntimeException('Expected Java Optional method token handoff');
    }
    if (($xml['language'] ?? '') !== 'xml') {
        throw new RuntimeException('Expected XML alias to normalize to XML highlighting');
    }
    if (($xml['lineNumbering']['start'] ?? null) !== 33) {
        throw new RuntimeException('Expected XML source startFrom line-number handoff');
    }
    if (!str_contains($xml['html'], '<span class="pp">&lt;?xml</span> <span class="ot">version</span>')) {
        throw new RuntimeException('Expected XML declaration token handoff');
    }
    if (!str_contains($xml['html'], '<span class="pp">&lt;!DOCTYPE</span> rss <span class="op">[</span><span class="pp">&lt;!ENTITY</span> legacy')) {
        throw new RuntimeException('Expected XML doctype/entity token handoff');
    }
    if (!str_contains($xml['html'], '<span class="kw">&lt;wp:wxr_version</span><span class="op">&gt;</span><span class="dv">1.2</span>')) {
        throw new RuntimeException('Expected XML namespaced tag token handoff');
    }
    if (!str_contains($xml['html'], '<span class="cn">&amp;legacy;</span> <span class="cn">&amp;amp;</span> Reviewed')) {
        throw new RuntimeException('Expected XML entity token handoff');
    }
    if (!str_contains($xml['html'], '<span class="st">&lt;![CDATA[&lt;!-- wp:paragraph --&gt;&lt;p&gt;Legacy shortcode [gallery]&lt;/p&gt;]]&gt;</span>')) {
        throw new RuntimeException('Expected XML CDATA token handoff');
    }
    if (!str_contains($xmlWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected XML WordPress style metadata');
    }
    if (($xslt['language'] ?? '') !== 'xslt') {
        throw new RuntimeException('Expected XSL alias to normalize to XSLT highlighting');
    }
    if (!str_contains($xslt['html'], '<span class="kw">&lt;xsl:value-of</span> <span class="ot">select</span>')) {
        throw new RuntimeException('Expected XSLT value-of token handoff');
    }
    if (($shell['language'] ?? '') !== 'bash') {
        throw new RuntimeException('Expected shell alias to normalize to Bash highlighting');
    }
    if (($shell['lineNumbering']['start'] ?? null) !== 50) {
        throw new RuntimeException('Expected shell source startFrom line-number handoff');
    }
    if (!str_contains($shell['html'], '<span class="kw">#!/usr/bin/env bash</span>')) {
        throw new RuntimeException('Expected shell shebang token handoff');
    }
    if (!str_contains($shell['html'], '<span class="fu">wp</span> <span class="va">post</span> <span class="va">list</span> <span class="ot">--post_type</span>')) {
        throw new RuntimeException('Expected wp-cli command and long-option token handoff');
    }
    if (!str_contains($shell['html'], '<span class="kw">if</span> <span class="op">[[</span> <span class="op">-z</span> <span class="st">&quot;$title&quot;</span>')) {
        throw new RuntimeException('Expected shell test expression token handoff');
    }
    if (!str_contains($shell['html'], '<span class="fu">cat</span> <span class="op">&lt;&lt;</span><span class="st">&#039;HTML&#039;</span>')) {
        throw new RuntimeException('Expected shell heredoc delimiter token handoff');
    }
    if (!str_contains($shell['html'], '<span class="st">&lt;!-- wp:paragraph --&gt;&lt;p&gt;Missing title&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;</span>')) {
        throw new RuntimeException('Expected shell heredoc body string token handoff');
    }
    if (!str_contains($shellWordpressBlock, '<style data-pandoc-highlight-style="pygments">')) {
        throw new RuntimeException('Expected shell WordPress style metadata');
    }
    if (!str_contains($shellWordpressBlock, '<span class="re">HTML</span>')) {
        throw new RuntimeException('Expected shell heredoc close token handoff');
    }
    if (($tokenTitle['tokenTitles'] ?? false) !== true) {
        throw new RuntimeException('Expected token-title opt-in metadata handoff');
    }
    if (!str_contains($tokenTitle['html'], '<span class="kw" title="KeywordTok">echo</span> <span class="fu" title="FunctionTok">esc_html</span>')) {
        throw new RuntimeException('Expected token title attributes on highlighted PHP tokens');
    }
    if (!str_contains($tokenTitleWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected token-title WordPress style metadata');
    }
    if (!str_contains($tokenTitleWordpressBlock, '<span class="co" title="CommentTok">// reviewer token titles</span>')) {
        throw new RuntimeException('Expected token-title WordPress comment metadata');
    }
    if (($css['language'] ?? '') !== 'css') {
        throw new RuntimeException('Expected CSS language handoff');
    }
    if (($css['lineNumbering']['start'] ?? null) !== 70) {
        throw new RuntimeException('Expected CSS source startFrom line-number handoff');
    }
    if (!str_contains($css['html'], '<span class="kw">@media</span> <span class="op">(</span><span class="ot">min-width</span>')) {
        throw new RuntimeException('Expected CSS at-rule token handoff');
    }
    if (!str_contains($css['html'], '<span class="dt">.wp-block-import-card</span> <span class="op">&gt;</span> <span class="dt">a</span><span class="fu">:hover</span>')) {
        throw new RuntimeException('Expected CSS selector and pseudo-class handoff');
    }
    if (!str_contains($css['html'], '<span class="ot">--accent-color</span><span class="op">:</span> <span class="cn">#005cc5</span>')) {
        throw new RuntimeException('Expected CSS custom property and color token handoff');
    }
    if (!str_contains($css['html'], '<span class="fu">var</span><span class="op">(</span><span class="ot">--accent-color</span><span class="op">)</span> <span class="kw">!important</span>')) {
        throw new RuntimeException('Expected CSS var() and important token handoff');
    }
    if (!str_contains($cssWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected CSS WordPress style metadata');
    }
    if (($rust['language'] ?? '') !== 'rust') {
        throw new RuntimeException('Expected Rust alias to normalize to Rust highlighting');
    }
    if (($rust['lineNumbering']['start'] ?? null) !== 88) {
        throw new RuntimeException('Expected Rust source startFrom line-number handoff');
    }
    if (!str_contains($rust['html'], '<span class="kw">use</span> <span class="va">serde_json</span><span class="op">::</span><span class="dt">Value</span>')) {
        throw new RuntimeException('Expected Rust use path token handoff');
    }
    if (!str_contains($rust['html'], '<span class="kw">pub</span> <span class="kw">struct</span> <span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected Rust struct token handoff');
    }
    if (!str_contains($rust['html'], '<span class="kw">return</span> <span class="fu">format!</span>')) {
        throw new RuntimeException('Expected Rust macro token handoff');
    }
    if (!str_contains($rustWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Rust WordPress style metadata');
    }
    if (($nix['language'] ?? '') !== 'nix') {
        throw new RuntimeException('Expected Nix language handoff');
    }
    if (($nix['lineNumbering']['start'] ?? null) !== 101) {
        throw new RuntimeException('Expected Nix source startFrom line-number handoff');
    }
    if (!str_contains($nix['html'], '<span class="fu">import</span> <span class="cn">&lt;nixpkgs&gt;</span>')) {
        throw new RuntimeException('Expected Nix import and angle-path token handoff');
    }
    if (!str_contains($nix['html'], '<span class="kw">inherit</span> <span class="op">(</span><span class="va">pkgs</span><span class="op">)</span> <span class="va">stdenv</span>')) {
        throw new RuntimeException('Expected Nix inherit token handoff');
    }
    if (!str_contains($nix['html'], '<span class="ot">mediaPaths</span> <span class="op">=</span> <span class="op">[</span> <span class="st">./uploads</span> <span class="st">./assets</span>')) {
        throw new RuntimeException('Expected Nix path token handoff');
    }
    if (!str_contains($nixWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected Nix WordPress style metadata');
    }
    if (!str_contains($nixWordpressBlock, '<span class="va">pkgs</span><span class="op">.</span><span class="va">writeText</span>')) {
        throw new RuntimeException('Expected Nix function-application handoff');
    }
    if (($scss['language'] ?? '') !== 'scss') {
        throw new RuntimeException('Expected SCSS language handoff');
    }
    if (($scss['lineNumbering']['start'] ?? null) !== 120) {
        throw new RuntimeException('Expected SCSS source startFrom line-number handoff');
    }
    if (!str_contains($scss['html'], '<span class="va">$accent-color</span><span class="op">:</span> <span class="cn">#005cc5</span> <span class="kw">!default</span>')) {
        throw new RuntimeException('Expected SCSS variable, color, and default flag token handoff');
    }
    if (!str_contains($scss['html'], '<span class="kw">@mixin</span> <span class="fu">import-card</span><span class="op">(</span><span class="va">$selector</span>')) {
        throw new RuntimeException('Expected SCSS mixin token handoff');
    }
    if (!str_contains($scss['html'], '<span class="op">&amp;</span><span class="fu">:hover</span>')) {
        throw new RuntimeException('Expected SCSS parent selector pseudo-class token handoff');
    }
    if (!str_contains($scss['html'], '<span class="kw">@include</span> <span class="fu">import-card</span>')) {
        throw new RuntimeException('Expected SCSS include token handoff');
    }
    if (!str_contains($scssWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected SCSS WordPress style metadata');
    }
    if (($go['language'] ?? '') !== 'go') {
        throw new RuntimeException('Expected Go language handoff');
    }
    if (($go['lineNumbering']['start'] ?? null) !== 135) {
        throw new RuntimeException('Expected Go source startFrom line-number handoff');
    }
    if (!str_contains($go['html'], '<span class="kw">package</span> <span class="va">review</span>')) {
        throw new RuntimeException('Expected Go package token handoff');
    }
    if (!str_contains($go['html'], '<span class="kw">type</span> <span class="dt">ReviewPacket</span> <span class="kw">struct</span>')) {
        throw new RuntimeException('Expected Go struct token handoff');
    }
    if (!str_contains($go['html'], '<span class="kw">func</span> <span class="fu">NormalizeTitle</span>')) {
        throw new RuntimeException('Expected Go function token handoff');
    }
    if (!str_contains($go['html'], '<span class="va">json</span><span class="op">.</span><span class="fu">Unmarshal</span>')) {
        throw new RuntimeException('Expected Go selector/function token handoff');
    }
    if (!str_contains($go['html'], '<span class="kw">go</span> <span class="kw">func</span><span class="op">()</span>')) {
        throw new RuntimeException('Expected Go goroutine token handoff');
    }
    if (!str_contains($goWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected Go WordPress style metadata');
    }
    if (($powershell['language'] ?? '') !== 'powershell') {
        throw new RuntimeException('Expected PowerShell alias to normalize to PowerShell highlighting');
    }
    if (($powershell['lineNumbering']['start'] ?? null) !== 150) {
        throw new RuntimeException('Expected PowerShell source startFrom line-number handoff');
    }
    if (!str_contains($powershell['html'], '<span class="fu">Get-Content</span> <span class="ot">-LiteralPath</span> <span class="va">$SourcePath</span>')) {
        throw new RuntimeException('Expected PowerShell Get-Content command and parameter token handoff');
    }
    if (!str_contains($powershell['html'], '<span class="cn">$null</span> <span class="op">-eq</span>')) {
        throw new RuntimeException('Expected PowerShell null comparison token handoff');
    }
    if (!str_contains($powershell['html'], '<span class="op">@{</span>')) {
        throw new RuntimeException('Expected PowerShell hashtable token handoff');
    }
    if (!str_contains($powershellWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected PowerShell WordPress style metadata');
    }
    if (!str_contains($powershellWordpressBlock, '<span class="fu">Set-Content</span> <span class="ot">-LiteralPath</span>')) {
        throw new RuntimeException('Expected PowerShell Set-Content token handoff');
    }
    if (($dot['language'] ?? '') !== 'dot') {
        throw new RuntimeException('Expected Graphviz DOT alias to normalize to dot highlighting');
    }
    if (($dot['lineNumbering']['start'] ?? null) !== 170) {
        throw new RuntimeException('Expected DOT source startFrom line-number handoff');
    }
    if (!str_contains($dot['html'], '<span class="kw">digraph</span> <span class="va">ImportFlow</span>')) {
        throw new RuntimeException('Expected DOT digraph token handoff');
    }
    if (!str_contains($dot['html'], '<span class="ot">rankdir</span><span class="op">=</span><span class="cn">LR</span>')) {
        throw new RuntimeException('Expected DOT graph attribute token handoff');
    }
    if (!str_contains($dot['html'], '<span class="va">review</span> <span class="op">-&gt;</span> <span class="va">publish</span>')) {
        throw new RuntimeException('Expected DOT directed edge token handoff');
    }
    if (!str_contains($dotWordpressBlock, '<style data-pandoc-highlight-style="monochrome">')) {
        throw new RuntimeException('Expected DOT WordPress style metadata');
    }
    if (($javascript['language'] ?? '') !== 'javascript') {
        throw new RuntimeException('Expected JavaScript module alias to normalize to JavaScript highlighting');
    }
    if (($javascript['lineNumbering']['start'] ?? null) !== 190) {
        throw new RuntimeException('Expected JavaScript source startFrom line-number handoff');
    }
    if (!str_contains($javascript['html'], '<span class="kw">import</span> <span class="op">{</span> <span class="va">registerBlockType</span>')) {
        throw new RuntimeException('Expected JavaScript import binding token handoff');
    }
    if (!str_contains($javascript['html'], '<span class="fu">replace</span><span class="op">(</span><span class="st">/\\s+/gu</span>')) {
        throw new RuntimeException('Expected JavaScript regex literal token handoff');
    }
    if (!str_contains($javascript['html'], '<span class="kw">await</span> <span class="fu">apiFetch</span><span class="op">({</span> <span class="ot">path</span>')) {
        throw new RuntimeException('Expected JavaScript await call and object-key token handoff');
    }
    if (!str_contains($javascript['html'], '<span class="dt">console</span><span class="op">.</span><span class="fu">log</span><span class="op">(</span><span class="dt">JSON</span><span class="op">.</span><span class="fu">stringify</span>')) {
        throw new RuntimeException('Expected JavaScript built-in/function token handoff');
    }
    if (!str_contains($javascriptWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected JavaScript WordPress style metadata');
    }
    if (!str_contains($javascriptWordpressBlock, '<span class="fu">registerBlockType</span><span class="op">(</span><span class="st">&quot;legacy/import-review&quot;</span>')) {
        throw new RuntimeException('Expected JavaScript Gutenberg registration token handoff');
    }
    if (($csharp['language'] ?? '') !== 'csharp') {
        throw new RuntimeException('Expected C# alias to normalize to CSharp highlighting');
    }
    if (($csharp['lineNumbering']['start'] ?? null) !== 210) {
        throw new RuntimeException('Expected C# source startFrom line-number handoff');
    }
    if (!str_contains($csharp['html'], '<span class="kw">public</span> <span class="kw">sealed</span> <span class="kw">record</span> <span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected C# record token handoff');
    }
    if (!str_contains($csharp['html'], '<span class="ot">[property: JsonPropertyName(&quot;title&quot;)]</span> <span class="dt">string</span><span class="op">?</span>')) {
        throw new RuntimeException('Expected C# targeted attribute and nullable token handoff');
    }
    if (!str_contains($csharp['html'], '<span class="dt">JsonSerializer</span><span class="op">.</span><span class="fu">Deserialize</span><span class="op">&lt;</span><span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected C# generic deserialize token handoff');
    }
    if (!str_contains($csharp['html'], '<span class="op">??</span> <span class="st">&quot;Untitled&quot;</span>')) {
        throw new RuntimeException('Expected C# null-coalescing token handoff');
    }
    if (!str_contains($csharpWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected C# WordPress style metadata');
    }
    if (!str_contains($csharpWordpressBlock, '<span class="st">$&quot;&lt;!-- wp:paragraph --&gt;&lt;p&gt;Import {packet?.SourceId}&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;&quot;</span>')) {
        throw new RuntimeException('Expected C# interpolated WordPress block string handoff');
    }
    if (($sql['language'] ?? '') !== 'sql') {
        throw new RuntimeException('Expected MySQL alias to normalize to SQL highlighting');
    }
    if (($sql['lineNumbering']['start'] ?? null) !== 230) {
        throw new RuntimeException('Expected SQL source startFrom line-number handoff');
    }
    if (!str_contains($sql['html'], '<span class="kw">CREATE</span> <span class="kw">TABLE</span> <span class="ot">`wp_posts`</span>')) {
        throw new RuntimeException('Expected SQL create table token handoff');
    }
    if (!str_contains($sql['html'], '<span class="kw">ON</span> <span class="kw">DUPLICATE</span> <span class="kw">KEY</span> <span class="kw">UPDATE</span>')) {
        throw new RuntimeException('Expected SQL duplicate-key update token handoff');
    }
    if (!str_contains($sql['html'], '<span class="fu">JSON_EXTRACT</span><span class="op">(</span><span class="ot">`meta_value`</span>')) {
        throw new RuntimeException('Expected SQL JSON_EXTRACT function token handoff');
    }
    if (!str_contains($sql['html'], '<span class="va">:post_id</span>')) {
        throw new RuntimeException('Expected SQL named bind token handoff');
    }
    if (!str_contains($sqlWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected SQL WordPress style metadata');
    }
    if (!str_contains($sqlWordpressBlock, '<span class="kw">COMMIT</span><span class="op">;</span>')) {
        throw new RuntimeException('Expected SQL commit token handoff');
    }
    if (($postgresql['language'] ?? '') !== 'sql') {
        throw new RuntimeException('Expected PostgreSQL alias to normalize to SQL highlighting');
    }
    if (($postgresql['lineNumbering']['start'] ?? null) !== 250) {
        throw new RuntimeException('Expected PostgreSQL source startFrom line-number handoff');
    }
    if (!str_contains($postgresql['html'], '<span class="kw">CREATE</span> <span class="kw">OR</span> <span class="kw">REPLACE</span> <span class="kw">FUNCTION</span>')) {
        throw new RuntimeException('Expected PostgreSQL function keyword token handoff');
    }
    if (!str_contains($postgresql['html'], '<span class="kw">AS</span> <span class="st">$review$</span>')) {
        throw new RuntimeException('Expected PostgreSQL dollar-quoted opener string handoff');
    }
    if (!str_contains($postgresql['html'], '<span class="st">  RAISE NOTICE &#039;import %&#039;, NEW.post_title;</span>')) {
        throw new RuntimeException('Expected PostgreSQL dollar-quoted body string handoff');
    }
    if (!str_contains($postgresqlWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected PostgreSQL WordPress style metadata');
    }
    if (!str_contains($postgresqlWordpressBlock, '<span class="st">$review$</span><span class="op">;</span>')) {
        throw new RuntimeException('Expected PostgreSQL dollar-quoted closer handoff');
    }
    if (($apache['language'] ?? '') !== 'apache') {
        throw new RuntimeException('Expected htaccess alias to normalize to Apache highlighting');
    }
    if (($apache['lineNumbering']['start'] ?? null) !== 270) {
        throw new RuntimeException('Expected htaccess source startFrom line-number handoff');
    }
    if (!str_contains($apache['html'], '<span class="kw">&lt;IfModule</span> <span class="dt">mod_rewrite.c</span><span class="op">&gt;</span>')) {
        throw new RuntimeException('Expected Apache IfModule token handoff');
    }
    if (!str_contains($apache['html'], '<span class="kw">RewriteCond</span> <span class="va">%{REQUEST_FILENAME}</span> <span class="op">!-f</span>')) {
        throw new RuntimeException('Expected Apache rewrite condition token handoff');
    }
    if (!str_contains($apache['html'], '<span class="kw">RewriteRule</span> <span class="op">.</span> <span class="st">/index.php</span> <span class="ot">[L]</span>')) {
        throw new RuntimeException('Expected Apache rewrite rule target and flag token handoff');
    }
    if (!str_contains($apache['html'], '<span class="kw">Header</span> <span class="kw">set</span> <span class="va">X-Import-Source</span> <span class="st">&quot;legacy&quot;</span>')) {
        throw new RuntimeException('Expected Apache Header directive token handoff');
    }
    if (!str_contains($apacheWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected Apache WordPress style metadata');
    }
    if (!str_contains($apacheWordpressBlock, '<span class="kw">&lt;/IfModule</span><span class="op">&gt;</span>')) {
        throw new RuntimeException('Expected Apache closing section token handoff');
    }
    if (($luaLongBracket['language'] ?? '') !== 'lua') {
        throw new RuntimeException('Expected Lua long-bracket fixture to normalize to Lua highlighting');
    }
    if (($luaLongBracket['lineNumbering']['start'] ?? null) !== 290) {
        throw new RuntimeException('Expected Lua long-bracket source startFrom line-number handoff');
    }
    if (!str_contains($luaLongBracket['html'], '<span class="co">--[=[ WordPress block fixture can contain &lt;!-- comments --&gt; ]=]</span>')) {
        throw new RuntimeException('Expected Lua equal-delimited long comment token handoff');
    }
    if (!str_contains($luaLongBracket['html'], '<span class="st">&lt;p&gt;Imported ${title}&lt;/p&gt;</span>')) {
        throw new RuntimeException('Expected Lua equal-delimited long string body handoff');
    }
    if (!str_contains($luaLongBracket['html'], '<span class="kw">return</span> <span class="dt">pandoc</span><span class="op">.</span><span class="fu">RawBlock</span>')) {
        throw new RuntimeException('Expected Lua RawBlock token handoff after long string');
    }
    if (!str_contains($luaLongBracketWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected Lua long-bracket WordPress style metadata');
    }
    if (($phpHeredoc['language'] ?? '') !== 'php') {
        throw new RuntimeException('Expected PHP heredoc fixture to normalize to PHP highlighting');
    }
    if (($phpHeredoc['lineNumbering']['start'] ?? null) !== 310) {
        throw new RuntimeException('Expected PHP heredoc source startFrom line-number handoff');
    }
    if (!str_contains($phpHeredoc['html'], '<span id="php-heredoc-review-311"><a href="#php-heredoc-review-311"></a><span class="va">$block</span> <span class="op">=</span> <span class="st">&lt;&lt;&lt;HTML</span></span>')) {
        throw new RuntimeException('Expected PHP heredoc opener string token handoff');
    }
    if (!str_contains($phpHeredoc['html'], '<span class="st">&lt;p&gt;Imported {$title}&lt;/p&gt;</span>')) {
        throw new RuntimeException('Expected PHP heredoc WordPress paragraph body token handoff');
    }
    if (!str_contains($phpHeredoc['html'], '<span class="va">$raw</span> <span class="op">=</span> <span class="st">&lt;&lt;&lt;&#039;NOWDOC&#039;</span>')) {
        throw new RuntimeException('Expected PHP nowdoc opener string token handoff');
    }
    if (!str_contains($phpHeredocWordpressBlock, '<style data-pandoc-highlight-style="pygments">')) {
        throw new RuntimeException('Expected PHP heredoc WordPress style metadata');
    }
    if (!str_contains($phpHeredocWordpressBlock, '<span class="st">&lt;!-- wp:html --&gt;</span>')) {
        throw new RuntimeException('Expected PHP nowdoc WordPress block body token handoff');
    }
    if (($rst['language'] ?? '') !== 'rst') {
        throw new RuntimeException('Expected reStructuredText fixture to normalize to RST highlighting');
    }
    if (($rst['lineNumbering']['start'] ?? null) !== 330) {
        throw new RuntimeException('Expected reStructuredText source startFrom line-number handoff');
    }
    if (!str_contains($rst['html'], '<span class="co">.. WordPress import review note</span>')) {
        throw new RuntimeException('Expected reStructuredText comment token handoff');
    }
    if (!str_contains($rst['html'], '<span class="fu">:status:</span> <span class="kw">**needs review**</span>')) {
        throw new RuntimeException('Expected reStructuredText field and bold token handoff');
    }
    if (!str_contains($rst['html'], '<span class="dt">.. code-block:: php</span>')) {
        throw new RuntimeException('Expected reStructuredText code-block directive token handoff');
    }
    if (!str_contains($rst['html'], '<span class="dt">   echo esc_html($title);</span>')) {
        throw new RuntimeException('Expected reStructuredText indented code token handoff');
    }
    if (!str_contains($rst['html'], '<span class="kw">:doc:</span><span class="cn">`media map &lt;uploads&gt;`</span>')) {
        throw new RuntimeException('Expected reStructuredText role and interpreted-text token handoff');
    }
    if (!str_contains($rstWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected reStructuredText WordPress style metadata');
    }
    if (($tsx['language'] ?? '') !== 'tsx') {
        throw new RuntimeException('Expected TSX fixture to normalize to TSX highlighting');
    }
    if (($tsx['lineNumbering']['start'] ?? null) !== 350) {
        throw new RuntimeException('Expected TSX source startFrom line-number handoff');
    }
    if (!str_contains($tsx['html'], '<span class="kw">import</span> <span class="kw">type</span> <span class="op">{</span> <span class="dt">BlockEditProps</span>')) {
        throw new RuntimeException('Expected TSX type-only import token handoff');
    }
    if (!str_contains($tsx['html'], '<span class="kw">type</span> <span class="dt">ReviewAttributes</span> <span class="op">=</span>')) {
        throw new RuntimeException('Expected TSX type alias token handoff');
    }
    if (!str_contains($tsx['html'], '<span class="fu">&lt;PanelBody</span> <span class="ot">title</span><span class="op">={</span><span class="st">`Import ${attributes.sourceId}`</span><span class="op">}&gt;</span>')) {
        throw new RuntimeException('Expected TSX component tag and template string token handoff');
    }
    if (!str_contains($tsx['html'], '<span class="ot">onChange</span><span class="op">={(</span><span class="va">title</span><span class="op">:</span> <span class="dt">string</span><span class="op">)</span> <span class="op">=&gt;</span> <span class="fu">setAttributes</span>')) {
        throw new RuntimeException('Expected TSX typed callback token handoff');
    }
    if (!str_contains($tsxWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected TSX WordPress style metadata');
    }
    if (!str_contains($tsxWordpressBlock, '<span class="fu">&lt;TextControl</span>')) {
        throw new RuntimeException('Expected TSX WordPress component token handoff');
    }
    if (($cmake['language'] ?? '') !== 'cmake') {
        throw new RuntimeException('Expected CMake fixture to normalize to CMake highlighting');
    }
    if (($cmake['lineNumbering']['start'] ?? null) !== 370) {
        throw new RuntimeException('Expected CMake source startFrom line-number handoff');
    }
    if (!str_contains($cmake['html'], '<span class="fu">cmake_minimum_required</span><span class="op">(</span><span class="kw">VERSION</span> <span class="dv">3.20</span>')) {
        throw new RuntimeException('Expected CMake minimum-version token handoff');
    }
    if (!str_contains($cmake['html'], '<span class="fu">target_compile_definitions</span><span class="op">(</span><span class="va">wp_import_review</span> <span class="kw">PRIVATE</span>')) {
        throw new RuntimeException('Expected CMake target compile definitions token handoff');
    }
    if (!str_contains($cmake['html'], '<span class="va">$&lt;$&lt;CONFIG:Debug&gt;:WP_IMPORT_DEBUG=1&gt;</span>')) {
        throw new RuntimeException('Expected CMake generator expression token handoff');
    }
    if (!str_contains($cmakeWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected CMake WordPress style metadata');
    }
    if (!str_contains($cmakeWordpressBlock, '<span class="fu">install</span><span class="op">(</span><span class="kw">TARGETS</span> <span class="va">wp_import_review</span>')) {
        throw new RuntimeException('Expected CMake install target WordPress handoff');
    }
    if (($nginx['language'] ?? '') !== 'nginx') {
        throw new RuntimeException('Expected Nginx fixture to normalize to Nginx highlighting');
    }
    if (($nginx['lineNumbering']['start'] ?? null) !== 390) {
        throw new RuntimeException('Expected Nginx source startFrom line-number handoff');
    }
    if (!str_contains($nginx['html'], '<span class="kw">try_files</span> <span class="va">$uri</span> <span class="va">$uri</span><span class="st">/</span> <span class="st">/index.php?</span><span class="va">$args</span>')) {
        throw new RuntimeException('Expected Nginx try_files variable/path token handoff');
    }
    if (!str_contains($nginx['html'], '<span class="kw">fastcgi_pass</span> <span class="st">unix:/run/php/php-fpm.sock</span>')) {
        throw new RuntimeException('Expected Nginx fastcgi_pass socket token handoff');
    }
    if (!str_contains($nginxWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected Nginx WordPress style metadata');
    }
    if (!str_contains($nginxWordpressBlock, '<span class="kw">rewrite</span> <span class="st">^</span> <span class="st">/index.php</span> <span class="cn">last</span>')) {
        throw new RuntimeException('Expected Nginx rewrite WordPress handoff');
    }
    if (($twig['language'] ?? '') !== 'twig') {
        throw new RuntimeException('Expected Twig fixture to normalize to Twig highlighting');
    }
    if (($twig['lineNumbering']['start'] ?? null) !== 410) {
        throw new RuntimeException('Expected Twig source startFrom line-number handoff');
    }
    if (!str_contains($twig['html'], '<span class="co">{# Timber theme template review #}</span>')) {
        throw new RuntimeException('Expected Twig comment token handoff');
    }
    if (!str_contains($twig['html'], '<span class="kw">for</span> <span class="va">item</span> <span class="kw">in</span> <span class="va">posts</span>')) {
        throw new RuntimeException('Expected Twig for/in token handoff');
    }
    if (!str_contains($twig['html'], '<span class="fu">default</span><span class="op">(</span><span class="st">&quot;Untitled&quot;</span><span class="op">)|</span><span class="fu">e</span>')) {
        throw new RuntimeException('Expected Twig filter token handoff');
    }
    if (!str_contains($twig['html'], '<span class="fu">function</span><span class="op">(</span><span class="st">&quot;wp_kses_post&quot;</span>')) {
        throw new RuntimeException('Expected Twig function call token handoff');
    }
    if (!str_contains($twigWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected Twig WordPress style metadata');
    }
    if (!str_contains($twigWordpressBlock, '<span class="fu">include</span><span class="op">(</span><span class="st">&quot;partials/empty.twig&quot;</span>')) {
        throw new RuntimeException('Expected Twig include fallback WordPress handoff');
    }
    if (($handlebars['language'] ?? '') !== 'mustache') {
        throw new RuntimeException('Expected Handlebars fixture to normalize to Mustache highlighting');
    }
    if (($handlebars['lineNumbering']['start'] ?? null) !== 430) {
        throw new RuntimeException('Expected Handlebars source startFrom line-number handoff');
    }
    if (!str_contains($handlebars['html'], '<span class="co">{{!-- Handlebars theme migration review --}}</span>')) {
        throw new RuntimeException('Expected Handlebars comment token handoff');
    }
    if (!str_contains($handlebars['html'], '<span class="op">{{#</span><span class="kw">if</span> <span class="va">title</span><span class="op">}}</span>')) {
        throw new RuntimeException('Expected Handlebars if-section token handoff');
    }
    if (!str_contains($handlebars['html'], '<span class="fu">default</span> <span class="st">&quot;Untitled&quot;</span>')) {
        throw new RuntimeException('Expected Handlebars helper and string token handoff');
    }
    if (!str_contains($handlebars['html'], '<span class="op">{{&gt;</span> <span class="va">footer</span> <span class="ot">source</span><span class="op">=</span><span class="va">sourceId</span>')) {
        throw new RuntimeException('Expected Handlebars partial and hash argument token handoff');
    }
    if (!str_contains($handlebarsWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected Handlebars WordPress style metadata');
    }
    if (!str_contains($handlebarsWordpressBlock, '<span class="op">{{{</span><span class="va">rawBlock</span><span class="op">}}}</span>')) {
        throw new RuntimeException('Expected Handlebars triple-stash raw block handoff');
    }
    if (($mermaid['language'] ?? '') !== 'mermaid') {
        throw new RuntimeException('Expected Mermaid fixture to normalize to Mermaid highlighting');
    }
    if (($mermaid['lineNumbering']['start'] ?? null) !== 450) {
        throw new RuntimeException('Expected Mermaid source startFrom line-number handoff');
    }
    if (!str_contains($mermaid['html'], '<span class="kw">flowchart</span> <span class="cn">LR</span>')) {
        throw new RuntimeException('Expected Mermaid flowchart declaration token handoff');
    }
    if (!str_contains($mermaid['html'], '<span class="va">ingest</span><span class="st">[Read WXR]</span> <span class="op">--&gt;</span> <span class="va">normalize</span><span class="st">{Normalize blocks}</span>')) {
        throw new RuntimeException('Expected Mermaid node and arrow token handoff');
    }
    if (!str_contains($mermaid['html'], '<span class="kw">classDef</span> <span class="va">warning</span>')) {
        throw new RuntimeException('Expected Mermaid classDef token handoff');
    }
    if (!str_contains($mermaidWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected Mermaid WordPress style metadata');
    }
    if (($htmlEmbedded['language'] ?? '') !== 'html') {
        throw new RuntimeException('Expected embedded HTML fixture to normalize to HTML highlighting');
    }
    if (($htmlEmbedded['lineNumbering']['start'] ?? null) !== 470) {
        throw new RuntimeException('Expected embedded HTML source startFrom line-number handoff');
    }
    if (!str_contains($htmlEmbedded['html'], '<span class="kw">&lt;style</span><span class="op">&gt;</span>')) {
        throw new RuntimeException('Expected embedded HTML style tag token handoff');
    }
    if (!str_contains($htmlEmbedded['html'], '<span class="dt">.wp-block-import-card</span> <span class="op">{</span> <span class="ot">color</span><span class="op">:</span> <span class="fu">var</span>')) {
        throw new RuntimeException('Expected embedded CSS selector/property/function token handoff');
    }
    if (!str_contains($htmlEmbedded['html'], '<span class="kw">@media</span> <span class="op">(</span><span class="ot">min-width</span><span class="op">:</span> <span class="dv">48rem</span>')) {
        throw new RuntimeException('Expected embedded CSS media-query token handoff');
    }
    if (!str_contains($htmlEmbedded['html'], '<span class="kw">const</span> <span class="va">block</span> <span class="op">=</span> <span class="va">wp</span><span class="op">.</span><span class="va">element</span><span class="op">.</span><span class="fu">createElement</span>')) {
        throw new RuntimeException('Expected embedded JavaScript const/function token handoff');
    }
    if (!str_contains($htmlEmbedded['html'], '<span class="dt">console</span><span class="op">.</span><span class="fu">log</span><span class="op">(</span><span class="dt">JSON</span><span class="op">.</span><span class="fu">stringify</span>')) {
        throw new RuntimeException('Expected embedded JavaScript builtin token handoff');
    }
    if (!str_contains($htmlEmbeddedWordpressBlock, '<style data-pandoc-highlight-style="pygments">')) {
        throw new RuntimeException('Expected embedded HTML WordPress style metadata');
    }
    if (($htmlPhp['language'] ?? '') !== 'html') {
        throw new RuntimeException('Expected HTML/PHP template fixture to normalize to HTML highlighting');
    }
    if (($htmlPhp['lineNumbering']['start'] ?? null) !== 490) {
        throw new RuntimeException('Expected HTML/PHP template source startFrom line-number handoff');
    }
    if (!str_contains($htmlPhp['html'], '<span class="pp">&lt;?php</span> <span class="kw">if</span>')) {
        throw new RuntimeException('Expected embedded PHP if token handoff');
    }
    if (!str_contains($htmlPhp['html'], '<span class="pp">&lt;?=</span> <span class="fu">esc_html</span><span class="op">(</span><span class="va">$post_title</span>')) {
        throw new RuntimeException('Expected embedded PHP echo shortcode token handoff');
    }
    if (!str_contains($htmlPhp['html'], '<span class="pp">&lt;?php</span> <span class="kw">endif</span><span class="op">;</span> <span class="pp">?&gt;</span>')) {
        throw new RuntimeException('Expected embedded PHP alternative-syntax closing token handoff');
    }
    if (!str_contains($htmlPhpWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected HTML/PHP template WordPress style metadata');
    }
    if (($graphql['language'] ?? '') !== 'graphql') {
        throw new RuntimeException('Expected GraphQL fixture to normalize to GraphQL highlighting');
    }
    if (($graphql['lineNumbering']['start'] ?? null) !== 510) {
        throw new RuntimeException('Expected GraphQL source startFrom line-number handoff');
    }
    if (!str_contains($graphql['html'], '<span class="kw">query</span> <span class="dt">ImportReview</span>')) {
        throw new RuntimeException('Expected GraphQL query token handoff');
    }
    if (!str_contains($graphql['html'], '<span class="ot">media</span><span class="op">:</span> <span class="va">featuredImage</span> <span class="ot">@include</span>')) {
        throw new RuntimeException('Expected GraphQL alias and directive token handoff');
    }
    if (!str_contains($graphql['html'], '<span class="kw">type</span> <span class="dt">ReviewPacket</span> <span class="kw">implements</span> <span class="dt">Node</span>')) {
        throw new RuntimeException('Expected GraphQL schema type token handoff');
    }
    if (!str_contains($graphqlWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected GraphQL WordPress style metadata');
    }
    if (($phpAttribute['language'] ?? '') !== 'php') {
        throw new RuntimeException('Expected PHP attribute fixture to normalize to PHP highlighting');
    }
    if (($phpAttribute['lineNumbering']['start'] ?? null) !== 530) {
        throw new RuntimeException('Expected PHP attribute source startFrom line-number handoff');
    }
    if (!str_contains($phpAttribute['html'], '<span class="ot">#[BlockVariation(name: &#039;legacy/import&#039;, title: &#039;Legacy Import&#039;)]</span>')) {
        throw new RuntimeException('Expected PHP attribute token handoff');
    }
    if (str_contains($phpAttribute['html'], '<span class="co">#[BlockVariation')) {
        throw new RuntimeException('Expected PHP attribute not to be classified as a comment');
    }
    if (!str_contains($phpAttribute['html'], '<span class="kw">enum</span> <span class="dt">ImportStatus</span><span class="op">:</span> <span class="dt">string</span>')) {
        throw new RuntimeException('Expected PHP enum and backed type token handoff');
    }
    if (!str_contains($phpAttribute['html'], '<span class="kw">fn</span><span class="op">(</span><span class="dt">array</span> <span class="va">$item</span><span class="op">):</span> <span class="dt">string</span>')) {
        throw new RuntimeException('Expected PHP closure type token handoff');
    }
    if (!str_contains($phpAttributeWordpressBlock, '<style data-pandoc-highlight-style="pygments">')) {
        throw new RuntimeException('Expected PHP attribute WordPress style metadata');
    }
    if (($asciidoc['language'] ?? '') !== 'asciidoc') {
        throw new RuntimeException('Expected AsciiDoc fixture to normalize to AsciiDoc highlighting');
    }
    if (($asciidoc['lineNumbering']['start'] ?? null) !== 550) {
        throw new RuntimeException('Expected AsciiDoc source startFrom line-number handoff');
    }
    if (!str_contains($asciidoc['html'], '<span class="re">= Legacy Import Review</span>')) {
        throw new RuntimeException('Expected AsciiDoc heading token handoff');
    }
    if (!str_contains($asciidoc['html'], '<span class="ot">:source-id:</span> legacy<span class="dv">-42</span>')) {
        throw new RuntimeException('Expected AsciiDoc attribute entry token handoff');
    }
    if (!str_contains($asciidoc['html'], '<span class="fu">image::</span>uploads')) {
        throw new RuntimeException('Expected AsciiDoc block macro token handoff');
    }
    if (!str_contains($asciidoc['html'], '<span class="kw">echo</span> <span class="fu">esc_html</span><span class="op">(</span><span class="va">$title</span><span class="op">);</span> <span class="co">// reviewed output &lt;1&gt;</span>')) {
        throw new RuntimeException('Expected AsciiDoc source listing PHP token handoff');
    }
    if (!str_contains($asciidocWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected AsciiDoc WordPress style metadata');
    }
    if (($phpdoc['language'] ?? '') !== 'php') {
        throw new RuntimeException('Expected PHPDoc fixture to normalize to PHP highlighting');
    }
    if (($phpdoc['lineNumbering']['start'] ?? null) !== 570) {
        throw new RuntimeException('Expected PHPDoc source startFrom line-number handoff');
    }
    if (($phpdoc['tokenTitles'] ?? null) !== true) {
        throw new RuntimeException('Expected PHPDoc token title handoff');
    }
    if (!str_contains($phpdoc['html'], '<span class="ot" title="OtherTok">@template</span> <span class="dt" title="DataTypeTok">TPacket</span>')) {
        throw new RuntimeException('Expected PHPDoc template annotation token handoff');
    }
    if (!str_contains($phpdoc['html'], '<span class="ot" title="OtherTok">@param</span> <span class="dt" title="DataTypeTok">array</span><span class="op" title="OperatorTok">&lt;</span><span class="dt" title="DataTypeTok">string</span>')) {
        throw new RuntimeException('Expected PHPDoc typed param token handoff');
    }
    if (!str_contains($phpdoc['html'], '<span class="ot" title="OtherTok">@return</span> <span class="dt" title="DataTypeTok">non-empty-string</span>')) {
        throw new RuntimeException('Expected PHPDoc return type token handoff');
    }
    if (!str_contains($phpdocWordpressBlock, '<style data-pandoc-highlight-style="pygments">')) {
        throw new RuntimeException('Expected PHPDoc WordPress style metadata');
    }
    if (($terraform['language'] ?? '') !== 'hcl') {
        throw new RuntimeException('Expected Terraform fixture to normalize to HCL highlighting');
    }
    if (($terraform['lineNumbering']['start'] ?? null) !== 590) {
        throw new RuntimeException('Expected Terraform source startFrom line-number handoff');
    }
    if (!str_contains($terraform['html'], '<span class="kw">resource</span> <span class="st">&quot;aws_s3_bucket&quot;</span> <span class="st">&quot;media&quot;</span>')) {
        throw new RuntimeException('Expected Terraform resource token handoff');
    }
    if (!str_contains($terraform['html'], '<span class="ot">Source</span> <span class="op">=</span> <span class="va">var.source_id</span>')) {
        throw new RuntimeException('Expected Terraform variable reference token handoff');
    }
    if (!str_contains($terraform['html'], '<span class="ot">value</span> <span class="op">=</span> <span class="fu">jsonencode</span><span class="op">({</span>')) {
        throw new RuntimeException('Expected Terraform function call token handoff');
    }
    if (!str_contains($terraform['html'], '<span class="ot">bucket</span>  <span class="op">=</span> <span class="va">aws_s3_bucket.media.bucket</span>')) {
        throw new RuntimeException('Expected Terraform resource reference token handoff');
    }
    if (!str_contains($terraformWordpressBlock, '<style data-pandoc-highlight-style="monochrome">')) {
        throw new RuntimeException('Expected Terraform WordPress style metadata');
    }
    if (($liquid['language'] ?? '') !== 'liquid') {
        throw new RuntimeException('Expected Shopify fixture to normalize to Liquid highlighting');
    }
    if (($liquid['lineNumbering']['start'] ?? null) !== 620) {
        throw new RuntimeException('Expected Liquid source startFrom line-number handoff');
    }
    if (!str_contains($liquid['html'], '<span class="co">{%- comment -%} WordPress migration review for Shopify product snippets {%- endcomment -%}</span>')) {
        throw new RuntimeException('Expected Liquid comment token handoff');
    }
    if (!str_contains($liquid['html'], '<span class="kw">assign</span> <span class="ot">title</span> <span class="op">=</span> <span class="va">product.title</span>')) {
        throw new RuntimeException('Expected Liquid assign and variable token handoff');
    }
    if (!str_contains($liquid['html'], '<span class="fu">strip_html</span> <span class="op">|</span> <span class="fu">truncatewords</span><span class="op">:</span> <span class="dv">24</span>')) {
        throw new RuntimeException('Expected Liquid filter token handoff');
    }
    if (!str_contains($liquid['html'], '<span class="kw">render</span> <span class="st">&quot;review-badge&quot;</span><span class="op">,</span> <span class="ot">source_id</span>')) {
        throw new RuntimeException('Expected Liquid render named-argument token handoff');
    }
    if (!str_contains($liquidWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected Liquid WordPress style metadata');
    }
    if (($elm['language'] ?? '') !== 'elm') {
        throw new RuntimeException('Expected Elm fixture to normalize to Elm highlighting');
    }
    if (($elm['lineNumbering']['start'] ?? null) !== 640) {
        throw new RuntimeException('Expected Elm source startFrom line-number handoff');
    }
    if (!str_contains($elm['html'], '<span class="kw">module</span> <span class="dt">ImportReview</span> <span class="kw">exposing</span>')) {
        throw new RuntimeException('Expected Elm module token handoff');
    }
    if (!str_contains($elm['html'], '<span class="kw">type</span> <span class="kw">alias</span> <span class="dt">Model</span>')) {
        throw new RuntimeException('Expected Elm type alias token handoff');
    }
    if (!str_contains($elm['html'], '<span class="fu">Decode.map3</span> <span class="dt">Model</span>')) {
        throw new RuntimeException('Expected Elm qualified decoder function handoff');
    }
    if (!str_contains($elm['html'], '<span class="fu">Html.div</span> <span class="op">[</span> <span class="fu">Attr.class</span>')) {
        throw new RuntimeException('Expected Elm qualified view function handoff');
    }
    if (!str_contains($elm['html'], '<span class="kw">if</span> <span class="va">model</span><span class="op">.</span><span class="va">published</span> <span class="kw">then</span>')) {
        throw new RuntimeException('Expected Elm if/then branch token handoff');
    }
    if (!str_contains($elmWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected Elm WordPress style metadata');
    }
    if (($jsonc['language'] ?? '') !== 'jsonc') {
        throw new RuntimeException('Expected JSON-with-comments fixture to normalize to jsonc highlighting');
    }
    if (($jsonc['lineNumbering']['start'] ?? null) !== 660) {
        throw new RuntimeException('Expected JSON-with-comments source startFrom line-number handoff');
    }
    if (!str_contains($jsonc['html'], '<span class="co">// WordPress import review settings</span>')) {
        throw new RuntimeException('Expected JSON-with-comments line comment token handoff');
    }
    if (!str_contains($jsonc['html'], '<span class="ot">unlistedBlocks</span><span class="op">:</span>')) {
        throw new RuntimeException('Expected JSON-with-comments unquoted key token handoff');
    }
    if (!str_contains($jsonc['html'], '<span class="co">/* Reviewer-only routing; ignored by strict JSON consumers. */</span>')) {
        throw new RuntimeException('Expected JSON-with-comments block comment token handoff');
    }
    if (!str_contains($jsonc['html'], '<span class="ot">&quot;dryRun&quot;</span><span class="op">:</span> <span class="cn">false</span>')) {
        throw new RuntimeException('Expected JSON-with-comments constant token handoff');
    }
    if (!str_contains($jsoncWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected JSON-with-comments WordPress style metadata');
    }
    if (($less['language'] ?? '') !== 'less') {
        throw new RuntimeException('Expected LESS fixture to normalize to LESS highlighting');
    }
    if (($less['lineNumbering']['start'] ?? null) !== 680) {
        throw new RuntimeException('Expected LESS source startFrom line-number handoff');
    }
    if (!str_contains($less['html'], '<span class="va">@accent-color</span><span class="op">:</span> <span class="cn">#005cc5</span>')) {
        throw new RuntimeException('Expected LESS variable and color token handoff');
    }
    if (!str_contains($less['html'], '<span class="dt">.import-card</span><span class="op">(</span><span class="va">@selector</span>')) {
        throw new RuntimeException('Expected LESS mixin selector and argument token handoff');
    }
    if (!str_contains($less['html'], '<span class="va">@{selector}</span> <span class="op">{</span>')) {
        throw new RuntimeException('Expected LESS interpolation token handoff');
    }
    if (!str_contains($less['html'], '<span class="fu">darken</span><span class="op">(</span><span class="va">@accent-color</span><span class="op">,</span> <span class="dv">10%</span>')) {
        throw new RuntimeException('Expected LESS color function token handoff');
    }
    if (!str_contains($less['html'], '<span class="kw">@media</span> <span class="op">(</span><span class="ot">min-width</span><span class="op">:</span> <span class="dv">48rem</span><span class="op">)</span>')) {
        throw new RuntimeException('Expected LESS media query token handoff');
    }
    if (!str_contains($lessWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected LESS WordPress style metadata');
    }
    if (($typst['language'] ?? '') !== 'typst') {
        throw new RuntimeException('Expected Typst language handoff');
    }
    if (($typst['lineNumbering']['start'] ?? null) !== 700) {
        throw new RuntimeException('Expected Typst source startFrom line-number handoff');
    }
    if (!str_contains($typst['html'], '<span class="kw">#set</span> <span class="dt">page</span><span class="op">(</span><span class="ot">width</span><span class="op">:</span> <span class="dv">8.5in</span>')) {
        throw new RuntimeException('Expected Typst page setup token handoff');
    }
    if (!str_contains($typst['html'], '<span class="kw">#let</span> <span class="va">source-id</span> <span class="op">=</span> <span class="st">&quot;legacy-42&quot;</span>')) {
        throw new RuntimeException('Expected Typst variable assignment token handoff');
    }
    if (!str_contains($typst['html'], '<span class="kw">#show</span> <span class="dt">link</span><span class="op">:</span> <span class="va">it</span> <span class="op">=&gt;</span> <span class="fu">underline</span>')) {
        throw new RuntimeException('Expected Typst show rule token handoff');
    }
    if (!str_contains($typst['html'], '<span class="fu">#table</span><span class="op">(</span>')) {
        throw new RuntimeException('Expected Typst table function token handoff');
    }
    if (!str_contains($typstWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected Typst WordPress style metadata');
    }
    if (($kotlin['language'] ?? '') !== 'kotlin') {
        throw new RuntimeException('Expected Kotlin language handoff');
    }
    if (($kotlin['lineNumbering']['start'] ?? null) !== 720) {
        throw new RuntimeException('Expected Kotlin source startFrom line-number handoff');
    }
    if (!str_contains($kotlin['html'], '<span class="ot">@Serializable</span>')) {
        throw new RuntimeException('Expected Kotlin annotation token handoff');
    }
    if (!str_contains($kotlin['html'], '<span class="kw">data</span> <span class="kw">class</span> <span class="dt">ReviewPacket</span><span class="op">(</span>')) {
        throw new RuntimeException('Expected Kotlin data class token handoff');
    }
    if (!str_contains($kotlin['html'], '<span class="kw">val</span> <span class="va">packet</span> <span class="op">=</span> <span class="dt">Json</span><span class="op">.</span><span class="fu">decodeFromString</span>')) {
        throw new RuntimeException('Expected Kotlin generic decode function token handoff');
    }
    if (!str_contains($kotlin['html'], '<span class="op">?:</span> <span class="st">&quot;Untitled&quot;</span>')) {
        throw new RuntimeException('Expected Kotlin Elvis operator token handoff');
    }
    if (!str_contains($kotlinWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected Kotlin WordPress style metadata');
    }
    if (($scala['language'] ?? '') !== 'scala') {
        throw new RuntimeException('Expected Scala language handoff');
    }
    if (($scala['lineNumbering']['start'] ?? null) !== 800) {
        throw new RuntimeException('Expected Scala source startFrom line-number handoff');
    }
    if (!str_contains($scala['html'], '<span class="kw">final</span> <span class="kw">case</span> <span class="kw">class</span> <span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected Scala case class token handoff');
    }
    if (!str_contains($scala['html'], '<span class="kw">if</span> <span class="va">title</span><span class="op">.</span><span class="va">isEmpty</span> <span class="kw">then</span> <span class="st">s&quot;Import ${packet.sourceId}&quot;</span>')) {
        throw new RuntimeException('Expected Scala then/else string interpolation token handoff');
    }
    if (!str_contains($scalaWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Scala WordPress style metadata');
    }
    if (($elixir['language'] ?? '') !== 'elixir') {
        throw new RuntimeException('Expected Elixir language handoff');
    }
    if (($elixir['lineNumbering']['start'] ?? null) !== 820) {
        throw new RuntimeException('Expected Elixir source startFrom line-number handoff');
    }
    if (!str_contains($elixir['html'], '<span class="kw">defmodule</span> <span class="dt">Importer</span><span class="op">.</span><span class="dt">ReviewPacket</span> <span class="kw">do</span>')) {
        throw new RuntimeException('Expected Elixir module token handoff');
    }
    if (!str_contains($elixir['html'], '<span class="ot">@enforce_keys</span> <span class="op">[</span><span class="cn">:source_id</span>')) {
        throw new RuntimeException('Expected Elixir module-attribute atom token handoff');
    }
    if (!str_contains($elixir['html'], '<span class="op">|&gt;</span> <span class="dt">String</span><span class="op">.</span><span class="fu">trim</span><span class="op">()</span>')) {
        throw new RuntimeException('Expected Elixir pipeline function token handoff');
    }
    if (!str_contains($elixir['html'], '<span class="kw">with</span> <span class="op">{</span><span class="cn">:ok</span><span class="op">,</span> <span class="va">packet</span><span class="op">}</span> <span class="op">&lt;-</span>')) {
        throw new RuntimeException('Expected Elixir with atom tuple token handoff');
    }
    if (!str_contains($elixirWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected Elixir WordPress style metadata');
    }
    if (($vue['language'] ?? '') !== 'vue') {
        throw new RuntimeException('Expected Vue SFC language handoff');
    }
    if (($vue['lineNumbering']['start'] ?? null) !== 840) {
        throw new RuntimeException('Expected Vue SFC source startFrom line-number handoff');
    }
    if (!str_contains($vue['html'], '<span class="kw">&lt;template</span><span class="op">&gt;</span>')) {
        throw new RuntimeException('Expected Vue template token handoff');
    }
    if (!str_contains($vue['html'], '<span class="ot">:data-source</span><span class="op">=</span><span class="st">&quot;packet.sourceId&quot;</span>')) {
        throw new RuntimeException('Expected Vue bound attribute token handoff');
    }
    if (!str_contains($vue['html'], '<span class="op">&gt;{{</span> <span class="va">packet</span><span class="op">.</span><span class="va">title</span><span class="op">?.</span><span class="fu">trim</span><span class="op">()</span>')) {
        throw new RuntimeException('Expected Vue interpolation expression token handoff');
    }
    if (!str_contains($vue['html'], '<span class="kw">type</span> <span class="dt">ReviewPacket</span> <span class="op">=</span>')) {
        throw new RuntimeException('Expected Vue embedded TypeScript token handoff');
    }
    if (!str_contains($vue['html'], '<span class="ot">--accent-color</span><span class="op">:</span> <span class="cn">#005cc5</span><span class="op">;</span>')) {
        throw new RuntimeException('Expected Vue embedded CSS token handoff');
    }
    if (!str_contains($vueWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected Vue WordPress style metadata');
    }
    if (($vueCustom['language'] ?? '') !== 'vue') {
        throw new RuntimeException('Expected Vue custom-block language handoff');
    }
    if (($vueCustom['lineNumbering']['start'] ?? null) !== 920) {
        throw new RuntimeException('Expected Vue custom-block source startFrom line-number handoff');
    }
    if (!str_contains($vueCustom['html'], '<span class="ot">&quot;title&quot;</span><span class="op">:</span><span class="st">&quot;Imported&quot;</span>')) {
        throw new RuntimeException('Expected Vue i18n JSON token handoff');
    }
    if (!str_contains($vueCustom['html'], '<span class="ot">requiresReview</span><span class="op">:</span> <span class="cn">true</span>')) {
        throw new RuntimeException('Expected Vue route YAML token handoff');
    }
    if (!str_contains($vueCustom['html'], '<span class="re">## Import Notes</span>')) {
        throw new RuntimeException('Expected Vue docs Markdown token handoff');
    }
    if (!str_contains($vueCustomWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected Vue custom-block WordPress style metadata');
    }
    if (($ocaml['language'] ?? '') !== 'ocaml') {
        throw new RuntimeException('Expected OCaml language handoff');
    }
    if (($ocaml['requestedLanguage'] ?? '') !== 'ml') {
        throw new RuntimeException('Expected ML requested-language wrapper handoff');
    }
    if (($ocaml['lineNumbering']['start'] ?? null) !== 880) {
        throw new RuntimeException('Expected OCaml source startFrom line-number handoff');
    }
    if (!str_contains($ocaml['html'], '<span class="kw">open</span> <span class="dt">Yojson.Safe</span>')) {
        throw new RuntimeException('Expected OCaml open module token handoff');
    }
    if (!str_contains($ocaml['html'], '<span class="ot">source_id</span> <span class="op">:</span> <span class="dt">int</span><span class="op">;</span>')) {
        throw new RuntimeException('Expected OCaml record-field token handoff');
    }
    if (!str_contains($ocaml['html'], '<span class="kw">let</span> <span class="fu">normalize_title</span>')) {
        throw new RuntimeException('Expected OCaml function binding token handoff');
    }
    if (!str_contains($ocaml['html'], '<span class="dt">Printf</span><span class="op">.</span><span class="fu">sprintf</span>')) {
        throw new RuntimeException('Expected OCaml module function token handoff');
    }
    if (!str_contains($ocamlWordpressBlock, '<style data-pandoc-highlight-style="monochrome">')) {
        throw new RuntimeException('Expected OCaml WordPress style metadata');
    }
    if (($julia['language'] ?? '') !== 'julia') {
        throw new RuntimeException('Expected Julia language handoff');
    }
    if (($julia['requestedLanguage'] ?? '') !== 'jl') {
        throw new RuntimeException('Expected JL requested-language wrapper handoff');
    }
    if (($julia['lineNumbering']['start'] ?? null) !== 900) {
        throw new RuntimeException('Expected Julia source startFrom line-number handoff');
    }
    if (!str_contains($julia['html'], '<span class="dt">Base</span><span class="op">.</span><span class="ot">@kwdef</span> <span class="kw">struct</span> <span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected Julia macro and struct token handoff');
    }
    if (!str_contains($julia['html'], '<span class="dt">JSON3</span><span class="op">.</span><span class="fu">read</span>')) {
        throw new RuntimeException('Expected Julia module function token handoff');
    }
    if (!str_contains($julia['html'], '<span class="ot">@info</span> <span class="st">&quot;review packet&quot;</span> <span class="ot">source</span>')) {
        throw new RuntimeException('Expected Julia macro keyword-argument token handoff');
    }
    if (!str_contains($juliaWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected Julia WordPress style metadata');
    }
    if (($awk['language'] ?? '') !== 'awk') {
        throw new RuntimeException('Expected AWK language handoff');
    }
    if (($awk['requestedLanguage'] ?? '') !== 'awk') {
        throw new RuntimeException('Expected AWK requested-language wrapper handoff');
    }
    if (($awk['lineNumbering']['start'] ?? null) !== 940) {
        throw new RuntimeException('Expected AWK source startFrom line-number handoff');
    }
    if (!str_contains($awk['html'], '<span class="re">BEGIN</span> <span class="op">{</span>')) {
        throw new RuntimeException('Expected AWK BEGIN region token handoff');
    }
    if (!str_contains($awk['html'], '<span class="va">NR</span> <span class="op">&gt;</span> <span class="dv">1</span> <span class="op">&amp;&amp;</span> <span class="va">$3</span> <span class="op">~</span> <span class="st">/publish|draft/</span>')) {
        throw new RuntimeException('Expected AWK field and regex token handoff');
    }
    if (!str_contains($awk['html'], '<span class="fu">gsub</span><span class="op">(</span><span class="st">/[[:space:]]+/</span>')) {
        throw new RuntimeException('Expected AWK char-class regex token handoff');
    }
    if (!str_contains($awk['html'], '<span class="kw">printf</span> <span class="st">&quot;%s\\t%s\\t%s\\n&quot;</span>')) {
        throw new RuntimeException('Expected AWK printf string token handoff');
    }
    if (!str_contains($awkWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected AWK WordPress style metadata');
    }
    if (($batch['language'] ?? '') !== 'batch') {
        throw new RuntimeException('Expected Windows batch language handoff');
    }
    if (($batch['requestedLanguage'] ?? '') !== 'bat') {
        throw new RuntimeException('Expected BAT requested-language wrapper handoff');
    }
    if (($batch['lineNumbering']['start'] ?? null) !== 960) {
        throw new RuntimeException('Expected Windows batch source startFrom line-number handoff');
    }
    if (!str_contains($batch['html'], '<span class="kw">setlocal</span> <span class="va">EnableExtensions</span> <span class="va">EnableDelayedExpansion</span>')) {
        throw new RuntimeException('Expected Windows batch setlocal token handoff');
    }
    if (!str_contains($batch['html'], '<span class="kw">if</span> <span class="va">!ERRORLEVEL!</span> <span class="op">NEQ</span> <span class="dv">0</span> <span class="kw">goto</span> <span class="re">:failed</span>')) {
        throw new RuntimeException('Expected Windows batch delayed-variable and operator token handoff');
    }
    if (!str_contains($batch['html'], '<span class="fu">wp</span> <span class="va">post</span> <span class="va">list</span> <span class="ot">--format</span><span class="op">=</span><span class="va">ids</span>')) {
        throw new RuntimeException('Expected Windows batch WordPress CLI token handoff');
    }
    if (!str_contains($batchWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected Windows batch WordPress style metadata');
    }
    if (($matlab['language'] ?? '') !== 'matlab') {
        throw new RuntimeException('Expected MATLAB language handoff');
    }
    if (($matlab['requestedLanguage'] ?? '') !== 'matlab') {
        throw new RuntimeException('Expected MATLAB requested-language wrapper handoff');
    }
    if (($matlab['lineNumbering']['start'] ?? null) !== 980) {
        throw new RuntimeException('Expected MATLAB source startFrom line-number handoff');
    }
    if (!str_contains($matlab['html'], '<span class="kw">function</span> <span class="op">[</span><span class="va">score</span>')) {
        throw new RuntimeException('Expected MATLAB function output token handoff');
    }
    if (!str_contains($matlab['html'], '<span class="va">packet</span><span class="op">.</span><span class="va">views</span> <span class="dt">double</span> <span class="op">=</span> <span class="cn">NaN</span>')) {
        throw new RuntimeException('Expected MATLAB arguments block datatype handoff');
    }
    if (!str_contains($matlab['html'], '<span class="fu">regexprep</span><span class="op">(</span><span class="va">title</span><span class="op">,</span> <span class="st">&quot;[^a-z0-9]+&quot;</span>')) {
        throw new RuntimeException('Expected MATLAB regexprep string token handoff');
    }
    if (!str_contains($matlabWordpressBlock, '<style data-pandoc-highlight-style="monochrome">')) {
        throw new RuntimeException('Expected MATLAB WordPress style metadata');
    }
    if (($fish['language'] ?? '') !== 'fish') {
        throw new RuntimeException('Expected Fish shell language handoff');
    }
    if (($fish['requestedLanguage'] ?? '') !== 'fish') {
        throw new RuntimeException('Expected Fish requested-language wrapper handoff');
    }
    if (($fish['lineNumbering']['start'] ?? null) !== 1000) {
        throw new RuntimeException('Expected Fish source startFrom line-number handoff');
    }
    if (!str_contains($fish['html'], '<span class="kw">function</span> <span class="va">normalize_title</span> <span class="ot">--argument-names</span>')) {
        throw new RuntimeException('Expected Fish function and option token handoff');
    }
    if (!str_contains($fish['html'], '<span class="kw">set</span> <span class="ot">-l</span> <span class="va">title</span> <span class="op">(</span><span class="fu">jq</span>')) {
        throw new RuntimeException('Expected Fish set and command-substitution token handoff');
    }
    if (!str_contains($fish['html'], '<span class="fu">string</span> <span class="va">trim</span> <span class="op">--</span> <span class="va">$title</span> <span class="op">|</span> <span class="fu">read</span>')) {
        throw new RuntimeException('Expected Fish pipe and builtin token handoff');
    }
    if (!str_contains($fish['html'], '<span class="fu">wp</span> <span class="va">post</span> <span class="va">meta</span> <span class="va">update</span>')) {
        throw new RuntimeException('Expected Fish WordPress CLI token handoff');
    }
    if (!str_contains($fishWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected Fish WordPress style metadata');
    }
    if (($sed['language'] ?? '') !== 'sed') {
        throw new RuntimeException('Expected Sed stream editor language handoff');
    }
    if (($sed['requestedLanguage'] ?? '') !== 'sed') {
        throw new RuntimeException('Expected Sed requested-language wrapper handoff');
    }
    if (($sed['lineNumbering']['start'] ?? null) !== 1020) {
        throw new RuntimeException('Expected Sed source startFrom line-number handoff');
    }
    if (!str_contains($sed['html'], '<span class="st">/^[[:space:]]*$/</span><span class="kw">d</span>')) {
        throw new RuntimeException('Expected Sed regex address and delete command token handoff');
    }
    if (!str_contains($sed['html'], '<span class="kw">s</span><span class="st">#&lt;script[^&gt;]*&gt;.*&lt;/script&gt;##</span><span class="ot">g</span>')) {
        throw new RuntimeException('Expected Sed hash-delimited substitution and flag token handoff');
    }
    if (!str_contains($sed['html'], '<span id="sed-review-1022"><a href="#sed-review-1022"></a><span class="st">&lt;!-- wp:paragraph --&gt;</span></span>')) {
        throw new RuntimeException('Expected Sed insert-command text payload token handoff');
    }
    if (!str_contains($sed['html'], '<span class="re">:normalized</span>')) {
        throw new RuntimeException('Expected Sed branch label token handoff');
    }
    if (!str_contains($sed['html'], '<span id="sed-review-1032"><a href="#sed-review-1032"></a><span class="kw">p</span></span>')) {
        throw new RuntimeException('Expected Sed print command token handoff');
    }
    if (!str_contains($sedWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected Sed WordPress style metadata');
    }
    if (($bibtex['language'] ?? '') !== 'bibtex') {
        throw new RuntimeException('Expected BibTeX language handoff');
    }
    if (($bibtex['requestedLanguage'] ?? '') !== 'biblatex') {
        throw new RuntimeException('Expected BibLaTeX requested-language wrapper handoff');
    }
    if (($bibtex['lineNumbering']['start'] ?? null) !== 1040) {
        throw new RuntimeException('Expected BibTeX source startFrom line-number handoff');
    }
    if (!str_contains($bibtex['html'], '<span class="kw">@online</span><span class="op">{</span><span class="va">wp-data-liberation</span>')) {
        throw new RuntimeException('Expected BibTeX entry keyword and citation key token handoff');
    }
    if (!str_contains($bibtex['html'], '<span class="ot">title</span> <span class="op">=</span> <span class="va">wp</span> <span class="op">#</span> <span class="st">&quot; shortcode audit&quot;</span>')) {
        throw new RuntimeException('Expected BibTeX string macro concatenation token handoff');
    }
    if (!str_contains($bibtexWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected BibTeX WordPress style metadata');
    }
    if (($vim['language'] ?? '') !== 'vim') {
        throw new RuntimeException('Expected Vimscript language handoff');
    }
    if (($vim['requestedLanguage'] ?? '') !== 'vim') {
        throw new RuntimeException('Expected Vim requested-language wrapper handoff');
    }
    if (($vim['lineNumbering']['start'] ?? null) !== 1060) {
        throw new RuntimeException('Expected Vimscript source startFrom line-number handoff');
    }
    if (!str_contains($vim['html'], '<span class="kw">function</span><span class="op">!</span> <span class="va">s:NormalizeTitle</span>')) {
        throw new RuntimeException('Expected Vimscript function token handoff');
    }
    if (!str_contains($vim['html'], '<span class="kw">command</span><span class="op">!</span> <span class="ot">-nargs</span>')) {
        throw new RuntimeException('Expected Vimscript user-command token handoff');
    }
    if (!str_contains($vim['html'], '<span class="kw">syntax</span> <span class="kw">match</span> <span class="va">wpImportSource</span> <span class="st">/\v(import_source|post_title)/</span>')) {
        throw new RuntimeException('Expected Vimscript syntax match token handoff');
    }
    if (!str_contains($vim['html'], '<span class="kw">highlight</span> <span class="va">wpImportSource</span> <span class="kw">ctermfg</span><span class="op">=</span><span class="va">Green</span>')) {
        throw new RuntimeException('Expected Vimscript highlight command token handoff');
    }
    if (!str_contains($vimWordpressBlock, '<style data-pandoc-highlight-style="monochrome">')) {
        throw new RuntimeException('Expected Vimscript WordPress style metadata');
    }
    if (($scheme['language'] ?? '') !== 'scheme') {
        throw new RuntimeException('Expected Racket alias to normalize to Scheme highlighting');
    }
    if (($scheme['requestedLanguage'] ?? '') !== 'racket') {
        throw new RuntimeException('Expected Racket requested-language wrapper handoff');
    }
    if (($scheme['lineNumbering']['start'] ?? null) !== 1080) {
        throw new RuntimeException('Expected Scheme/Racket source startFrom line-number handoff');
    }
    if (!str_contains($scheme['html'], '<span class="kw">#lang</span> <span class="dt">racket</span>')) {
        throw new RuntimeException('Expected Scheme/Racket #lang token handoff');
    }
    if (!str_contains($scheme['html'], '<span class="kw">struct</span> <span class="va">packet</span> <span class="op">(</span><span class="va">source-id</span>')) {
        throw new RuntimeException('Expected Scheme/Racket struct token handoff');
    }
    if (!str_contains($scheme['html'], '<span class="kw">define</span> <span class="op">(</span><span class="va">normalize-title</span>')) {
        throw new RuntimeException('Expected Scheme/Racket define token handoff');
    }
    if (!str_contains($scheme['html'], '<span class="ot">#:transparent</span>')) {
        throw new RuntimeException('Expected Scheme/Racket keyword-argument token handoff');
    }
    if (!str_contains($scheme['html'], '<span class="ot">#:when</span> <span class="op">(</span><span class="fu">hash-ref</span> <span class="va">block</span> <span class="cn">&#039;review?</span> <span class="cn">#t</span>')) {
        throw new RuntimeException('Expected Scheme/Racket keyword and boolean token handoff');
    }
    if (!str_contains($schemeWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected Scheme/Racket WordPress style metadata');
    }
    if (($csv['language'] ?? '') !== 'csv') {
        throw new RuntimeException('Expected CSV language handoff');
    }
    if (($csv['requestedLanguage'] ?? '') !== 'csv') {
        throw new RuntimeException('Expected CSV requested-language wrapper handoff');
    }
    if (($csv['lineNumbering']['start'] ?? null) !== 1100) {
        throw new RuntimeException('Expected CSV source startFrom line-number handoff');
    }
    if (!str_contains($csv['html'], '<span class="ot">source_id</span><span class="op">,</span><span class="ot">title</span>')) {
        throw new RuntimeException('Expected CSV header field token handoff');
    }
    if (!str_contains($csv['html'], '<span class="st">&quot;Legacy, &quot;&quot;quoted&quot;&quot; title&quot;</span>')) {
        throw new RuntimeException('Expected CSV quoted field token handoff');
    }
    if (!str_contains($csv['html'], '<span class="cn">true</span>')) {
        throw new RuntimeException('Expected CSV boolean constant token handoff');
    }
    if (!str_contains($csvWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected CSV WordPress style metadata');
    }
    if (($erlang['language'] ?? '') !== 'erlang') {
        throw new RuntimeException('Expected Erlang language alias handoff');
    }
    if (($erlang['requestedLanguage'] ?? '') !== 'erl') {
        throw new RuntimeException('Expected Erlang requested-language wrapper handoff');
    }
    if (($erlang['lineNumbering']['start'] ?? null) !== 1120) {
        throw new RuntimeException('Expected Erlang source startFrom line-number handoff');
    }
    if (!str_contains($erlang['html'], '<span class="ot">-module</span><span class="op">(</span><span class="cn">wp_import_review</span><span class="op">).</span>')) {
        throw new RuntimeException('Expected Erlang module attribute token handoff');
    }
    if (!str_contains($erlang['html'], '<span class="fu">normalize_title</span><span class="op">(</span><span class="dt">#review_packet</span>')) {
        throw new RuntimeException('Expected Erlang record function-head token handoff');
    }
    if (!str_contains($erlang['html'], '<span class="dt">maps</span><span class="op">:</span><span class="fu">get</span><span class="op">(&lt;&lt;</span><span class="st">&quot;blockName&quot;</span>')) {
        throw new RuntimeException('Expected Erlang maps:get binary-string token handoff');
    }
    if (!str_contains($erlangWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Erlang WordPress style metadata');
    }
    if (($objectiveC['language'] ?? '') !== 'objectivec') {
        throw new RuntimeException('Expected Objective-C language alias handoff');
    }
    if (($objectiveC['requestedLanguage'] ?? '') !== 'objc') {
        throw new RuntimeException('Expected Objective-C requested-language wrapper handoff');
    }
    if (($objectiveC['lineNumbering']['start'] ?? null) !== 1140) {
        throw new RuntimeException('Expected Objective-C source startFrom line-number handoff');
    }
    if (!str_contains($objectiveC['html'], '<span class="kw">@interface</span> <span class="dt">WPImportReviewPacket</span>')) {
        throw new RuntimeException('Expected Objective-C interface token handoff');
    }
    if (!str_contains($objectiveC['html'], '<span class="kw">@property</span> <span class="op">(</span><span class="kw">nonatomic</span><span class="op">,</span> <span class="kw">copy</span><span class="op">,</span> <span class="kw">nullable</span><span class="op">)</span> <span class="dt">NSString</span>')) {
        throw new RuntimeException('Expected Objective-C property token handoff');
    }
    if (!str_contains($objectiveC['html'], '<span class="fu">NSLog</span><span class="op">(</span><span class="st">@&quot;%@&quot;</span>')) {
        throw new RuntimeException('Expected Objective-C NSLog string token handoff');
    }
    if (!str_contains($objectiveCWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected Objective-C WordPress style metadata');
    }
    if (($raku['language'] ?? '') !== 'raku') {
        throw new RuntimeException('Expected Raku language alias handoff');
    }
    if (($raku['lineNumbering']['start'] ?? null) !== 1160) {
        throw new RuntimeException('Expected Raku source startFrom line-number handoff');
    }
    if (!str_contains($raku['html'], '<span class="kw">unit</span> <span class="kw">module</span> <span class="dt">WP::Import::Review</span>')) {
        throw new RuntimeException('Expected Raku module token handoff');
    }
    if (!str_contains($raku['html'], '<span class="kw">sub</span> <span class="fu">normalize-title</span>')) {
        throw new RuntimeException('Expected Raku sub token handoff');
    }
    if (!str_contains($raku['html'], '<span class="va">$title</span><span class="op">.</span><span class="fu">subst</span>')) {
        throw new RuntimeException('Expected Raku method and regex token handoff');
    }
    if (!str_contains($rakuWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected Raku WordPress style metadata');
    }
    if (($fennel['language'] ?? '') !== 'fennel') {
        throw new RuntimeException('Expected Fennel language alias handoff');
    }
    if (($fennel['requestedLanguage'] ?? '') !== 'fnl') {
        throw new RuntimeException('Expected FNL requested-language wrapper handoff');
    }
    if (($fennel['lineNumbering']['start'] ?? null) !== 1180) {
        throw new RuntimeException('Expected Fennel source startFrom line-number handoff');
    }
    if (!str_contains($fennel['html'], '<span class="kw">fn</span> <span class="fu">normalize-title</span>')) {
        throw new RuntimeException('Expected Fennel function declaration token handoff');
    }
    if (!str_contains($fennel['html'], '<span class="kw">collect</span> <span class="op">[</span><span class="va">_</span> <span class="va">block</span>')) {
        throw new RuntimeException('Expected Fennel collect binding token handoff');
    }
    if (!str_contains($fennel['html'], '<span class="ot">:source-id</span> <span class="va">packet.source_id</span>')) {
        throw new RuntimeException('Expected Fennel table keyword token handoff');
    }
    if (!str_contains($fennelWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Fennel WordPress style metadata');
    }
    if (($meson['language'] ?? '') !== 'meson') {
        throw new RuntimeException('Expected Meson fixture to normalize to meson highlighting');
    }
    if (($meson['requestedLanguage'] ?? '') !== 'meson') {
        throw new RuntimeException('Expected Meson requested-language metadata');
    }
    if (($meson['lineNumbering']['start'] ?? null) !== 1200) {
        throw new RuntimeException('Expected Meson line-number start handoff');
    }
    if (!str_contains($meson['html'], '<span class="fu">project</span><span class="op">(</span><span class="st">&#039;wp-import-review&#039;</span>')) {
        throw new RuntimeException('Expected Meson project call token handoff');
    }
    if (!str_contains($meson['html'], '<span class="va">wp_cli</span> <span class="op">=</span> <span class="fu">find_program</span>')) {
        throw new RuntimeException('Expected Meson find_program token handoff');
    }
    if (!str_contains($meson['html'], '<span class="ot">dependencies</span><span class="op">:</span> <span class="fu">dependency</span>')) {
        throw new RuntimeException('Expected Meson keyword-argument token handoff');
    }
    if (!str_contains($mesonWordpressBlock, '<style data-pandoc-highlight-style="monochrome">')) {
        throw new RuntimeException('Expected Meson WordPress style metadata');
    }
    if (($just['language'] ?? '') !== 'just') {
        throw new RuntimeException('Expected Justfile fixture to normalize to just highlighting');
    }
    if (($just['requestedLanguage'] ?? '') !== 'Justfile') {
        throw new RuntimeException('Expected Justfile requested-language metadata');
    }
    if (($just['lineNumbering']['start'] ?? null) !== 1220) {
        throw new RuntimeException('Expected Justfile line-number start handoff');
    }
    if (!str_contains($just['html'], '<span class="kw">set</span> <span class="ot">shell</span> <span class="op">:=</span>')) {
        throw new RuntimeException('Expected Justfile setting token handoff');
    }
    if (!str_contains($just['html'], '<span class="re">review source_id</span><span class="op">:</span>')) {
        throw new RuntimeException('Expected Justfile recipe-header token handoff');
    }
    if (!str_contains($just['html'], '<span class="fu">wp</span> <span class="va">post</span> <span class="va">list</span>')) {
        throw new RuntimeException('Expected Justfile wp command token handoff');
    }
    if (!str_contains($justWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected Justfile WordPress style metadata');
    }
    if (($protobuf['language'] ?? '') !== 'protobuf') {
        throw new RuntimeException('Expected Protobuf language alias handoff');
    }
    if (($protobuf['requestedLanguage'] ?? '') !== 'proto') {
        throw new RuntimeException('Expected proto requested-language metadata');
    }
    if (($protobuf['lineNumbering']['start'] ?? null) !== 1240) {
        throw new RuntimeException('Expected Protobuf line-number start handoff');
    }
    if (!str_contains($protobuf['html'], '<span class="kw">message</span> <span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected Protobuf message token handoff');
    }
    if (!str_contains($protobuf['html'], '<span class="dt">map</span><span class="op">&lt;</span><span class="dt">string</span><span class="op">,</span> <span class="dt">string</span><span class="op">&gt;</span> <span class="ot">metadata</span>')) {
        throw new RuntimeException('Expected Protobuf map field token handoff');
    }
    if (!str_contains($protobuf['html'], '<span class="kw">rpc</span> <span class="fu">Queue</span><span class="op">(</span><span class="dt">ReviewPacket</span><span class="op">)</span> <span class="kw">returns</span>')) {
        throw new RuntimeException('Expected Protobuf rpc token handoff');
    }
    if (!str_contains($protobufWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected Protobuf WordPress style metadata');
    }
    if (($tcl['language'] ?? '') !== 'tcl') {
        throw new RuntimeException('Expected Tcl language alias handoff');
    }
    if (($tcl['lineNumbering']['start'] ?? null) !== 1260) {
        throw new RuntimeException('Expected Tcl line-number start handoff');
    }
    if (!str_contains($tcl['html'], '<span class="kw">proc</span> <span class="fu">normalize_title</span>')) {
        throw new RuntimeException('Expected Tcl procedure token handoff');
    }
    if (!str_contains($tcl['html'], '<span class="kw">if</span> <span class="op">{</span><span class="va">$title</span> <span class="op">eq</span>')) {
        throw new RuntimeException('Expected Tcl expression operator token handoff');
    }
    if (!str_contains($tcl['html'], '<span class="fu">exec</span> <span class="fu">wp</span> <span class="va">post</span> <span class="va">meta</span>')) {
        throw new RuntimeException('Expected Tcl WordPress CLI command token handoff');
    }
    if (!str_contains($tclWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected Tcl WordPress style metadata');
    }
    if (($lineHighlight['highlightLines'] ?? []) !== [1281, 1283, 1284]) {
        throw new RuntimeException('Expected Pandoc highlight-lines range metadata');
    }
    if (!str_contains($lineHighlight['html'], '<span id="line-highlight-review-1281" class="highlighted-line" data-pandoc-line-highlight="1281"><a href="#line-highlight-review-1281"></a><span class="va">$title</span>')) {
        throw new RuntimeException('Expected highlighted source-line metadata in HTML output');
    }
    if (!str_contains($lineHighlightWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected line-highlight WordPress style metadata');
    }
    if (!str_contains($lineHighlightWordpressBlock, 'data-pandoc-line-highlight="1283"')) {
        throw new RuntimeException('Expected line-highlight metadata in WordPress HTML block');
    }
    if (($fortran['language'] ?? '') !== 'fortran') {
        throw new RuntimeException('Expected Fortran language alias handoff');
    }
    if (($fortran['requestedLanguage'] ?? '') !== 'f90') {
        throw new RuntimeException('Expected Fortran requested-language metadata');
    }
    if (($fortran['lineNumbering']['start'] ?? null) !== 1300) {
        throw new RuntimeException('Expected Fortran line-number start handoff');
    }
    if (!str_contains($fortran['html'], '<span class="kw">module</span> <span class="va">wp_import_review</span>')) {
        throw new RuntimeException('Expected Fortran module token handoff');
    }
    if (!str_contains($fortran['html'], '<span class="kw">pure</span> <span class="kw">function</span> <span class="fu">normalized_title</span>')) {
        throw new RuntimeException('Expected Fortran function declaration token handoff');
    }
    if (!str_contains($fortran['html'], '<span class="fu">write</span><span class="op">(</span><span class="va">title</span>')) {
        throw new RuntimeException('Expected Fortran write intrinsic token handoff');
    }
    if (!str_contains($fortranWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Fortran WordPress style metadata');
    }
    if (($d['language'] ?? '') !== 'd') {
        throw new RuntimeException('Expected D language alias handoff');
    }
    if (($d['requestedLanguage'] ?? '') !== 'd') {
        throw new RuntimeException('Expected D requested-language metadata');
    }
    if (($d['lineNumbering']['start'] ?? null) !== 1320) {
        throw new RuntimeException('Expected D line-number start handoff');
    }
    if (!str_contains($d['html'], '<span class="kw">module</span> <span class="va">wp</span><span class="op">.</span><span class="va">review</span><span class="op">.</span><span class="va">packet</span><span class="op">;</span>')) {
        throw new RuntimeException('Expected D module token handoff');
    }
    if (!str_contains($d['html'], '<span class="ot">@safe</span> <span class="kw">pure</span> <span class="dt">string</span> <span class="fu">normalizedTitle</span>')) {
        throw new RuntimeException('Expected D attribute and function token handoff');
    }
    if (!str_contains($d['html'], '<span class="kw">return</span> <span class="fu">format</span><span class="op">!</span><span class="st">&quot;Import %s&quot;</span>')) {
        throw new RuntimeException('Expected D template function token handoff');
    }
    if (!str_contains($dWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected D WordPress style metadata');
    }
    if (!str_contains($dWordpressBlock, '<span class="kw">struct</span> <span class="dt">ReviewPacket</span>')) {
        throw new RuntimeException('Expected D struct token handoff');
    }
    if (($commonLisp['language'] ?? '') !== 'commonlisp') {
        throw new RuntimeException('Expected Common Lisp alias handoff');
    }
    if (($commonLisp['requestedLanguage'] ?? '') !== 'common-lisp') {
        throw new RuntimeException('Expected Common Lisp requested-language metadata');
    }
    if (($commonLisp['lineNumbering']['start'] ?? null) !== 1340) {
        throw new RuntimeException('Expected Common Lisp line-number start handoff');
    }
    if (!str_contains($commonLisp['html'], '<span class="op">(</span><span class="kw">defpackage</span> <span class="cn">#:wp-import.review</span>')) {
        throw new RuntimeException('Expected Common Lisp package token handoff');
    }
    if (!str_contains($commonLisp['html'], '<span class="kw">defun</span> <span class="va">normalized-title</span>')) {
        throw new RuntimeException('Expected Common Lisp defun token handoff');
    }
    if (!str_contains($commonLisp['html'], '<span class="fu">string-trim</span> <span class="st">&quot; &quot;</span>')) {
        throw new RuntimeException('Expected Common Lisp string-trim token handoff');
    }
    if (!str_contains($commonLisp['html'], '<span class="fu">format</span> <span class="cn">nil</span> <span class="st">&quot;Import ~A&quot;</span>')) {
        throw new RuntimeException('Expected Common Lisp nil constant and format token handoff');
    }
    if (!str_contains($commonLispWordpressBlock, '<style data-pandoc-highlight-style="monochrome">')) {
        throw new RuntimeException('Expected Common Lisp WordPress style metadata');
    }
    if (!str_contains($commonLispWordpressBlock, '<span class="fu">remove-if-not</span> <span class="op">#&#039;</span><span class="fu">identity</span>')) {
        throw new RuntimeException('Expected Common Lisp function quote token handoff');
    }
    if (($pascal['language'] ?? '') !== 'pascal') {
        throw new RuntimeException('Expected Pascal language alias handoff');
    }
    if (($pascal['requestedLanguage'] ?? '') !== 'pascal') {
        throw new RuntimeException('Expected Pascal requested-language metadata');
    }
    if (($pascal['lineNumbering']['start'] ?? null) !== 1360) {
        throw new RuntimeException('Expected Pascal line-number start handoff');
    }
    if (!str_contains($pascal['html'], '<span class="pp">{$mode objfpc}{$H+}</span>')) {
        throw new RuntimeException('Expected Pascal compiler directive token handoff');
    }
    if (!str_contains($pascal['html'], '<span class="kw">function</span> <span class="fu">NormalizedTitle</span>')) {
        throw new RuntimeException('Expected Pascal function token handoff');
    }
    if (!str_contains($pascal['html'], '<span class="fu">Format</span><span class="op">(</span><span class="st">&#039;Import %d&#039;</span>')) {
        throw new RuntimeException('Expected Pascal Format token handoff');
    }
    if (!str_contains($pascalWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected Pascal WordPress style metadata');
    }
    if (!str_contains($pascalWordpressBlock, '<span class="fu">WriteLn</span><span class="op">(</span><span class="fu">NormalizedTitle</span>')) {
        throw new RuntimeException('Expected Pascal WordPress block token handoff');
    }
    if (($groovy['language'] ?? '') !== 'groovy') {
        throw new RuntimeException('Expected Groovy language alias handoff');
    }
    if (($groovy['requestedLanguage'] ?? '') !== 'gradle') {
        throw new RuntimeException('Expected Gradle requested-language metadata');
    }
    if (($groovy['lineNumbering']['start'] ?? null) !== 1380) {
        throw new RuntimeException('Expected Groovy line-number start handoff');
    }
    if (!str_contains($groovy['html'], '<span class="ot">@Grab</span><span class="op">(</span><span class="st">&#039;org.codehaus.groovy:groovy-json:3.0.21&#039;</span><span class="op">)</span>')) {
        throw new RuntimeException('Expected Groovy annotation token handoff');
    }
    if (!str_contains($groovy['html'], '<span class="kw">def</span> <span class="va">normalizedTitle</span> <span class="op">=</span> <span class="va">packet</span><span class="op">.</span><span class="va">title</span><span class="op">?.</span><span class="fu">trim</span><span class="op">()</span> <span class="op">?:</span>')) {
        throw new RuntimeException('Expected Groovy safe navigation and Elvis operator handoff');
    }
    if (!str_contains($groovy['html'], '<span class="fu">pipeline</span> <span class="op">{</span>')) {
        throw new RuntimeException('Expected Groovy Jenkins pipeline token handoff');
    }
    if (!str_contains($groovyWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Groovy WordPress style metadata');
    }
    if (!str_contains($groovyWordpressBlock, '<span class="fu">writeJSON</span> <span class="ot">file</span><span class="op">:</span> <span class="st">&#039;review.json&#039;</span>')) {
        throw new RuntimeException('Expected Groovy Gradle/Jenkins WordPress block token handoff');
    }
    if (($crystal['language'] ?? '') !== 'crystal') {
        throw new RuntimeException('Expected Crystal language alias handoff');
    }
    if (($crystal['requestedLanguage'] ?? '') !== 'crystal') {
        throw new RuntimeException('Expected Crystal requested-language metadata');
    }
    if (($crystal['lineNumbering']['start'] ?? null) !== 1400) {
        throw new RuntimeException('Expected Crystal line-number start handoff');
    }
    if (!str_contains($crystal['html'], '<span class="ot">@[Link(&quot;wp-review&quot;)]</span>')) {
        throw new RuntimeException('Expected Crystal annotation token handoff');
    }
    if (!str_contains($crystal['html'], '<span class="kw">include</span> <span class="dt">JSON</span><span class="op">::</span><span class="dt">Serializable</span>')) {
        throw new RuntimeException('Expected Crystal namespace datatype token handoff');
    }
    if (!str_contains($crystal['html'], '<span class="fu">try</span><span class="op">(&amp;.</span><span class="fu">strip</span><span class="op">)</span>')) {
        throw new RuntimeException('Expected Crystal block shorthand call token handoff');
    }
    if (!str_contains($crystal['html'], '<span class="kw">rescue</span> <span class="va">ex</span> <span class="op">:</span> <span class="dt">JSON</span><span class="op">::</span><span class="dt">ParseException</span>')) {
        throw new RuntimeException('Expected Crystal rescue datatype token handoff');
    }
    if (!str_contains($crystalWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected Crystal WordPress style metadata');
    }
    if (!str_contains($crystalWordpressBlock, '<span class="cn">STDERR</span><span class="op">.</span><span class="fu">puts</span>')) {
        throw new RuntimeException('Expected Crystal WordPress block token handoff');
    }
    if (($shellSession['language'] ?? '') !== 'shellsession') {
        throw new RuntimeException('Expected shell-session language handoff');
    }
    if (($shellSession['requestedLanguage'] ?? '') !== 'shell-session') {
        throw new RuntimeException('Expected shell-session requested-language metadata');
    }
    if (($shellSession['lineNumbering']['start'] ?? null) !== 1420) {
        throw new RuntimeException('Expected shell-session line-number start handoff');
    }
    if (!str_contains($shellSession['html'], '<span class="re">$ </span><span class="fu">wp</span> <span class="va">post</span> <span class="va">list</span>')) {
        throw new RuntimeException('Expected shell-session prompt command token handoff');
    }
    if (!str_contains($shellSession['html'], '<span class="in">Legacy Review</span>')) {
        throw new RuntimeException('Expected shell-session output token handoff');
    }
    if (!str_contains($shellSessionWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected shell-session WordPress style metadata');
    }
    if (!str_contains($shellSessionWordpressBlock, '<span class="re">$ </span><span class="fu">printf</span>')) {
        throw new RuntimeException('Expected shell-session WordPress command token handoff');
    }
    if (($nim['language'] ?? '') !== 'nim') {
        throw new RuntimeException('Expected Nim language alias handoff');
    }
    if (($nim['requestedLanguage'] ?? '') !== 'nim') {
        throw new RuntimeException('Expected Nim requested-language metadata');
    }
    if (($nim['lineNumbering']['start'] ?? null) !== 1440) {
        throw new RuntimeException('Expected Nim line-number start handoff');
    }
    if (!str_contains($nim['html'], '<span class="kw">proc</span> <span class="fu">normalizeTitle*</span><span class="op">(</span><span class="va">packet</span><span class="op">:</span> <span class="dt">ReviewPacket</span><span class="op">):</span> <span class="dt">string</span> <span class="ot">{.raises: [ValueError].}</span>')) {
        throw new RuntimeException('Expected Nim proc and pragma token handoff');
    }
    if (!str_contains($nim['html'], '<span class="kw">return</span> <span class="st">&quot;Import &quot;</span> <span class="op">&amp;</span> <span class="op">$</span><span class="va">packet</span><span class="op">.</span><span class="va">sourceId</span>')) {
        throw new RuntimeException('Expected Nim string concat and dollar conversion token handoff');
    }
    if (!str_contains($nim['html'], '<span class="st">&quot;dryRun&quot;</span><span class="op">:</span> <span class="cn">true</span>')) {
        throw new RuntimeException('Expected Nim JSON builder boolean token handoff');
    }
    if (!str_contains($nimWordpressBlock, '<style data-pandoc-highlight-style="monochrome">')) {
        throw new RuntimeException('Expected Nim WordPress style metadata');
    }
    if (!str_contains($nimWordpressBlock, '<span class="fu">normalizeTitle</span><span class="op">(</span><span class="va">packet</span><span class="op">),</span>')) {
        throw new RuntimeException('Expected Nim WordPress block token handoff');
    }
    if (($v['language'] ?? '') !== 'v') {
        throw new RuntimeException('Expected V language alias handoff');
    }
    if (($v['requestedLanguage'] ?? '') !== 'v') {
        throw new RuntimeException('Expected V requested-language metadata');
    }
    if (($v['lineNumbering']['start'] ?? null) !== 1460) {
        throw new RuntimeException('Expected V line-number start handoff');
    }
    if (!str_contains($v['html'], '<span class="kw">module</span> <span class="va">review</span>')) {
        throw new RuntimeException('Expected V module token handoff');
    }
    if (!str_contains($v['html'], '<span class="ot">[json: source_id]</span>')) {
        throw new RuntimeException('Expected V attribute token handoff');
    }
    if (!str_contains($v['html'], '<span class="kw">pub</span> <span class="kw">fn</span> <span class="fu">normalize_title</span>')) {
        throw new RuntimeException('Expected V function token handoff');
    }
    if (!str_contains($vWordpressBlock, '<style data-pandoc-highlight-style="haddock">')) {
        throw new RuntimeException('Expected V WordPress style metadata');
    }
    if (!str_contains($vWordpressBlock, '<span class="kw">$if</span> <span class="va">debug</span>')) {
        throw new RuntimeException('Expected V compile-time guard token handoff');
    }
    if (($idris['language'] ?? '') !== 'idris') {
        throw new RuntimeException('Expected Idris language alias handoff');
    }
    if (($idris['requestedLanguage'] ?? '') !== 'idris') {
        throw new RuntimeException('Expected Idris requested-language metadata');
    }
    if (($idris['lineNumbering']['start'] ?? null) !== 1480) {
        throw new RuntimeException('Expected Idris line-number start handoff');
    }
    if (!str_contains($idris['html'], '<span class="kw">module</span> <span class="dt">WP.Import.Review</span>')) {
        throw new RuntimeException('Expected Idris module token handoff');
    }
    if (!str_contains($idris['html'], '<span class="pp">%default</span> <span class="kw">total</span>')) {
        throw new RuntimeException('Expected Idris directive token handoff');
    }
    if (!str_contains($idris['html'], '<span class="fu">normalizeTitle</span> <span class="op">:</span> <span class="dt">ReviewPacket</span> <span class="op">-&gt;</span> <span class="dt">String</span>')) {
        throw new RuntimeException('Expected Idris type signature token handoff');
    }
    if (!str_contains($idrisWordpressBlock, '<style data-pandoc-highlight-style="zenburn">')) {
        throw new RuntimeException('Expected Idris WordPress style metadata');
    }
    if (!str_contains($idrisWordpressBlock, '<span class="cn">Right</span> <span class="va">packet</span>')) {
        throw new RuntimeException('Expected Idris constructor token handoff');
    }
    if (($coq['language'] ?? '') !== 'coq') {
        throw new RuntimeException('Expected Coq language alias handoff');
    }
    if (($coq['requestedLanguage'] ?? '') !== 'coq') {
        throw new RuntimeException('Expected Coq requested-language metadata');
    }
    if (($coq['lineNumbering']['start'] ?? null) !== 1510) {
        throw new RuntimeException('Expected Coq line-number start handoff');
    }
    if (!str_contains($coq['html'], '<span class="kw">From</span> <span class="dt">Coq</span> <span class="kw">Require</span> <span class="kw">Import</span>')) {
        throw new RuntimeException('Expected Coq import vernacular token handoff');
    }
    if (!str_contains($coq['html'], '<span class="kw">Theorem</span> <span class="ot">normalize_title_idempotent</span>')) {
        throw new RuntimeException('Expected Coq theorem token handoff');
    }
    if (!str_contains($coqWordpressBlock, '<style data-pandoc-highlight-style="tango">')) {
        throw new RuntimeException('Expected Coq WordPress style metadata');
    }
    if (!str_contains($coqWordpressBlock, '<span class="kw">Qed</span><span class="op">.</span>')) {
        throw new RuntimeException('Expected Coq proof terminator token handoff');
    }
    if (($agda['language'] ?? '') !== 'agda') {
        throw new RuntimeException('Expected Agda language alias handoff');
    }
    if (($agda['lineNumbering']['start'] ?? null) !== 1535) {
        throw new RuntimeException('Expected Agda line-number start handoff');
    }
    if (!str_contains($agda['html'], '<span class="kw">open</span> <span class="kw">import</span> <span class="dt">Agda.Builtin.Nat</span>')) {
        throw new RuntimeException('Expected Agda import token handoff');
    }
    if (!str_contains($agda['html'], '<span class="fu">normalizeTitle</span> <span class="op">:</span> <span class="dt">ReviewPacket</span> <span class="op">-&gt;</span> <span class="dt">String</span>')) {
        throw new RuntimeException('Expected Agda type signature token handoff');
    }
    if (!str_contains($agdaWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected Agda WordPress style metadata');
    }
    if (!str_contains($agdaWordpressBlock, '<span class="kw">postulate</span>')) {
        throw new RuntimeException('Expected Agda postulate token handoff');
    }
    if (($purescript['language'] ?? '') !== 'purescript') {
        throw new RuntimeException('Expected PureScript language alias handoff');
    }
    if (($purescript['requestedLanguage'] ?? '') !== 'purs') {
        throw new RuntimeException('Expected PureScript requested-language metadata');
    }
    if (($purescript['lineNumbering']['start'] ?? null) !== 1565) {
        throw new RuntimeException('Expected PureScript line-number start handoff');
    }
    if (!str_contains($purescript['html'], '<span class="kw">module</span> <span class="dt">WP.Import.Review</span>')) {
        throw new RuntimeException('Expected PureScript module token handoff');
    }
    if (!str_contains($purescript['html'], '<span class="fu">normalizeTitle</span> <span class="op">::</span> <span class="dt">ReviewPacket</span> <span class="op">-&gt;</span> <span class="dt">String</span>')) {
        throw new RuntimeException('Expected PureScript type signature token handoff');
    }
    if (!str_contains($purescriptWordpressBlock, '<style data-pandoc-highlight-style="espresso">')) {
        throw new RuntimeException('Expected PureScript WordPress style metadata');
    }
    if (!str_contains($purescriptWordpressBlock, '<span class="ot">blocks</span><span class="op">:</span> <span class="op">[</span><span class="st">&quot;core/paragraph&quot;</span><span class="op">]</span>')) {
        throw new RuntimeException('Expected PureScript record-literal token handoff');
    }
    if (($fsharp['language'] ?? '') !== 'fsharp') {
        throw new RuntimeException('Expected F# language alias handoff');
    }
    if (($fsharp['requestedLanguage'] ?? '') !== 'fsx') {
        throw new RuntimeException('Expected F# requested-language metadata');
    }
    if (($fsharp['lineNumbering']['start'] ?? null) !== 1585) {
        throw new RuntimeException('Expected F# line-number start handoff');
    }
    if (!str_contains($fsharp['html'], '<span class="kw">module</span> <span class="dt">WP</span><span class="op">.</span><span class="dt">Import</span><span class="op">.</span><span class="dt">Review</span>')) {
        throw new RuntimeException('Expected F# module token handoff');
    }
    if (!str_contains($fsharp['html'], '<span class="kw">let</span> <span class="fu">normalizeTitle</span> <span class="op">(</span><span class="va">packet</span><span class="op">:</span> <span class="dt">ReviewPacket</span><span class="op">)</span>')) {
        throw new RuntimeException('Expected F# function definition token handoff');
    }
    if (!str_contains($fsharpWordpressBlock, '<style data-pandoc-highlight-style="kate">')) {
        throw new RuntimeException('Expected F# WordPress style metadata');
    }
    if (!str_contains($fsharpWordpressBlock, '<span class="kw">return</span> <span class="op">{|</span> <span class="va">title</span> <span class="op">=</span> <span class="fu">normalizeTitle</span>')) {
        throw new RuntimeException('Expected F# anonymous-record token handoff');
    }
    if (($rakuPodQuote['language'] ?? '') !== 'raku') {
        throw new RuntimeException('Expected Raku POD quote language alias handoff');
    }
    if (($rakuPodQuote['requestedLanguage'] ?? '') !== 'rakudoc') {
        throw new RuntimeException('Expected Raku POD quote requested-language metadata');
    }
    if (($rakuPodQuote['lineNumbering']['start'] ?? null) !== 1610) {
        throw new RuntimeException('Expected Raku POD quote line-number start handoff');
    }
    if (!str_contains($rakuPodQuote['html'], '<span class="co">=end pod</span></span>')) {
        throw new RuntimeException('Expected Raku POD terminator comment boundary handoff');
    }
    if (!str_contains($rakuPodQuote['html'], '<span class="kw">my</span> <span class="va">$title</span> <span class="op">=</span> <span class="st">q:to/END/;</span>')) {
        throw new RuntimeException('Expected Raku q:to heredoc opener string handoff');
    }
    if (!str_contains($rakuPodQuote['html'], '<span class="fu">say</span> <span class="va">$title</span><span class="op">.</span><span class="va">trim</span><span class="op">;</span>')) {
        throw new RuntimeException('Expected Raku code after POD and heredoc to remain tokenized');
    }
    if (!str_contains($rakuPodQuoteWordpressBlock, '<style data-pandoc-highlight-style="breezedark">')) {
        throw new RuntimeException('Expected Raku POD quote WordPress style metadata');
    }
    if (!str_contains($rakuPodQuoteWordpressBlock, '<span class="st">&lt;!-- wp:paragraph --&gt;&lt;p&gt;$title&lt;/p&gt;&lt;!-- /wp:paragraph --&gt;</span>')) {
        throw new RuntimeException('Expected Raku qq:to heredoc body token handoff');
    }
    if (($customTheme['style'] ?? '') !== 'review-import') {
        throw new RuntimeException('Expected custom Pandoc JSON theme name handoff');
    }
    if (!str_contains($customTheme['css'], '.sourceCode .kw { color: #ffcc00; font-weight: 700; }')) {
        throw new RuntimeException('Expected custom theme keyword CSS handoff');
    }
    if (!str_contains($customTheme['css'], 'color: #8f9aae; background-color: #202a35;')) {
        throw new RuntimeException('Expected custom theme line-number CSS handoff');
    }
    if (!str_contains($customThemeWordpressBlock, '<style data-pandoc-highlight-style="review-import">')) {
        throw new RuntimeException('Expected custom theme WordPress style metadata');
    }
    if (!str_contains($customThemeWordpressBlock, '<span id="custom-theme-review-10"><a href="#custom-theme-review-10"></a><span class="kw">echo</span>')) {
        throw new RuntimeException('Expected custom theme numbered code handoff');
    }

    echo "syntax highlighting handoff self-test ok\n";
    exit(0);
}

echo "Syntax highlighting handoff for WordPress import:\n";
echo "language: " . $highlighted['language'] . "\n";
echo "highlightedHtml:\n" . $highlighted['html'] . "\n";
echo "numberedHighlightedHtml:\n" . $numbered['html'] . "\n";
echo "haskellHighlightedHtml:\n" . $haskell['html'] . "\n";
echo "latexHighlightedHtml:\n" . $latex['html'] . "\n";
echo "diffHighlightedHtml:\n" . $diff['html'] . "\n";
echo "markdownHighlightedHtml:\n" . $markdown['html'] . "\n";
echo "rubyHighlightedHtml:\n" . $ruby['html'] . "\n";
echo "luaHighlightedHtml:\n" . $lua['html'] . "\n";
echo "typescriptHighlightedHtml:\n" . $typescript['html'] . "\n";
echo "pythonHighlightedHtml:\n" . $python['html'] . "\n";
echo "cppHighlightedHtml:\n" . $cpp['html'] . "\n";
echo "dockerfileHighlightedHtml:\n" . $dockerfile['html'] . "\n";
echo "makefileHighlightedHtml:\n" . $makefile['html'] . "\n";
echo "jsxHighlightedHtml:\n" . $jsx['html'] . "\n";
echo "rHighlightedHtml:\n" . $rScript['html'] . "\n";
echo "iniHighlightedHtml:\n" . $ini['html'] . "\n";
echo "tomlHighlightedHtml:\n" . $toml['html'] . "\n";
echo "perlHighlightedHtml:\n" . $perl['html'] . "\n";
echo "javaHighlightedHtml:\n" . $java['html'] . "\n";
echo "xmlHighlightedHtml:\n" . $xml['html'] . "\n";
echo "xsltHighlightedHtml:\n" . $xslt['html'] . "\n";
echo "shellHighlightedHtml:\n" . $shell['html'] . "\n";
echo "tokenTitleHighlightedHtml:\n" . $tokenTitle['html'] . "\n";
echo "cssHighlightedHtml:\n" . $css['html'] . "\n";
echo "rustHighlightedHtml:\n" . $rust['html'] . "\n";
echo "nixHighlightedHtml:\n" . $nix['html'] . "\n";
echo "scssHighlightedHtml:\n" . $scss['html'] . "\n";
echo "goHighlightedHtml:\n" . $go['html'] . "\n";
echo "powershellHighlightedHtml:\n" . $powershell['html'] . "\n";
echo "dotHighlightedHtml:\n" . $dot['html'] . "\n";
echo "javascriptHighlightedHtml:\n" . $javascript['html'] . "\n";
echo "csharpHighlightedHtml:\n" . $csharp['html'] . "\n";
echo "sqlHighlightedHtml:\n" . $sql['html'] . "\n";
echo "postgresqlHighlightedHtml:\n" . $postgresql['html'] . "\n";
echo "apacheHighlightedHtml:\n" . $apache['html'] . "\n";
echo "luaLongBracketHighlightedHtml:\n" . $luaLongBracket['html'] . "\n";
echo "phpHeredocHighlightedHtml:\n" . $phpHeredoc['html'] . "\n";
echo "rstHighlightedHtml:\n" . $rst['html'] . "\n";
echo "tsxHighlightedHtml:\n" . $tsx['html'] . "\n";
echo "cmakeHighlightedHtml:\n" . $cmake['html'] . "\n";
echo "nginxHighlightedHtml:\n" . $nginx['html'] . "\n";
echo "twigHighlightedHtml:\n" . $twig['html'] . "\n";
echo "handlebarsHighlightedHtml:\n" . $handlebars['html'] . "\n";
echo "mermaidHighlightedHtml:\n" . $mermaid['html'] . "\n";
echo "htmlEmbeddedHighlightedHtml:\n" . $htmlEmbedded['html'] . "\n";
echo "htmlPhpHighlightedHtml:\n" . $htmlPhp['html'] . "\n";
echo "graphqlHighlightedHtml:\n" . $graphql['html'] . "\n";
echo "phpAttributeHighlightedHtml:\n" . $phpAttribute['html'] . "\n";
echo "asciidocHighlightedHtml:\n" . $asciidoc['html'] . "\n";
echo "phpdocHighlightedHtml:\n" . $phpdoc['html'] . "\n";
echo "terraformHighlightedHtml:\n" . $terraform['html'] . "\n";
echo "liquidHighlightedHtml:\n" . $liquid['html'] . "\n";
echo "elmHighlightedHtml:\n" . $elm['html'] . "\n";
echo "jsoncHighlightedHtml:\n" . $jsonc['html'] . "\n";
echo "typstHighlightedHtml:\n" . $typst['html'] . "\n";
echo "kotlinHighlightedHtml:\n" . $kotlin['html'] . "\n";
echo "scalaHighlightedHtml:\n" . $scala['html'] . "\n";
echo "elixirHighlightedHtml:\n" . $elixir['html'] . "\n";
echo "vueHighlightedHtml:\n" . $vue['html'] . "\n";
echo "vueCustomHighlightedHtml:\n" . $vueCustom['html'] . "\n";
echo "ocamlHighlightedHtml:\n" . $ocaml['html'] . "\n";
echo "juliaHighlightedHtml:\n" . $julia['html'] . "\n";
echo "awkHighlightedHtml:\n" . $awk['html'] . "\n";
echo "batchHighlightedHtml:\n" . $batch['html'] . "\n";
echo "matlabHighlightedHtml:\n" . $matlab['html'] . "\n";
echo "fishHighlightedHtml:\n" . $fish['html'] . "\n";
echo "sedHighlightedHtml:\n" . $sed['html'] . "\n";
echo "vimHighlightedHtml:\n" . $vim['html'] . "\n";
echo "schemeHighlightedHtml:\n" . $scheme['html'] . "\n";
echo "csvHighlightedHtml:\n" . $csv['html'] . "\n";
echo "erlangHighlightedHtml:\n" . $erlang['html'] . "\n";
echo "objectiveCHighlightedHtml:\n" . $objectiveC['html'] . "\n";
echo "rakuHighlightedHtml:\n" . $raku['html'] . "\n";
echo "fennelHighlightedHtml:\n" . $fennel['html'] . "\n";
echo "mesonHighlightedHtml:\n" . $meson['html'] . "\n";
echo "justHighlightedHtml:\n" . $just['html'] . "\n";
echo "protobufHighlightedHtml:\n" . $protobuf['html'] . "\n";
echo "tclHighlightedHtml:\n" . $tcl['html'] . "\n";
echo "lineHighlightHighlightedHtml:\n" . $lineHighlight['html'] . "\n";
echo "dHighlightedHtml:\n" . $d['html'] . "\n";
echo "commonLispHighlightedHtml:\n" . $commonLisp['html'] . "\n";
echo "pascalHighlightedHtml:\n" . $pascal['html'] . "\n";
echo "groovyHighlightedHtml:\n" . $groovy['html'] . "\n";
echo "crystalHighlightedHtml:\n" . $crystal['html'] . "\n";
echo "shellSessionHighlightedHtml:\n" . $shellSession['html'] . "\n";
echo "nimHighlightedHtml:\n" . $nim['html'] . "\n";
echo "vHighlightedHtml:\n" . $v['html'] . "\n";
echo "idrisHighlightedHtml:\n" . $idris['html'] . "\n";
echo "coqHighlightedHtml:\n" . $coq['html'] . "\n";
echo "agdaHighlightedHtml:\n" . $agda['html'] . "\n";
echo "purescriptHighlightedHtml:\n" . $purescript['html'] . "\n";
echo "fsharpHighlightedHtml:\n" . $fsharp['html'] . "\n";
echo "rakuPodQuoteHighlightedHtml:\n" . $rakuPodQuote['html'] . "\n";
echo "customThemeHighlightedHtml:\n" . $customTheme['html'] . "\n";
echo "wordpressBlock:\n" . $wordpressBlock . "\n";
echo "writerHighlightedBlocks:\n" . $writerHighlightedBlocks . "\n";
echo "pythonWordpressBlock:\n" . $pythonWordpressBlock . "\n";
echo "cppWordpressBlock:\n" . $cppWordpressBlock . "\n";
echo "dockerfileWordpressBlock:\n" . $dockerfileWordpressBlock . "\n";
echo "makefileWordpressBlock:\n" . $makefileWordpressBlock . "\n";
echo "jsxWordpressBlock:\n" . $jsxWordpressBlock . "\n";
echo "rWordpressBlock:\n" . $rWordpressBlock . "\n";
echo "iniWordpressBlock:\n" . $iniWordpressBlock . "\n";
echo "tomlWordpressBlock:\n" . $tomlWordpressBlock . "\n";
echo "perlWordpressBlock:\n" . $perlWordpressBlock . "\n";
echo "javaWordpressBlock:\n" . $javaWordpressBlock . "\n";
echo "xmlWordpressBlock:\n" . $xmlWordpressBlock . "\n";
echo "shellWordpressBlock:\n" . $shellWordpressBlock . "\n";
echo "tokenTitleWordpressBlock:\n" . $tokenTitleWordpressBlock . "\n";
echo "cssWordpressBlock:\n" . $cssWordpressBlock . "\n";
echo "rustWordpressBlock:\n" . $rustWordpressBlock . "\n";
echo "nixWordpressBlock:\n" . $nixWordpressBlock . "\n";
echo "scssWordpressBlock:\n" . $scssWordpressBlock . "\n";
echo "goWordpressBlock:\n" . $goWordpressBlock . "\n";
echo "powershellWordpressBlock:\n" . $powershellWordpressBlock . "\n";
echo "dotWordpressBlock:\n" . $dotWordpressBlock . "\n";
echo "javascriptWordpressBlock:\n" . $javascriptWordpressBlock . "\n";
echo "csharpWordpressBlock:\n" . $csharpWordpressBlock . "\n";
echo "sqlWordpressBlock:\n" . $sqlWordpressBlock . "\n";
echo "postgresqlWordpressBlock:\n" . $postgresqlWordpressBlock . "\n";
echo "apacheWordpressBlock:\n" . $apacheWordpressBlock . "\n";
echo "luaLongBracketWordpressBlock:\n" . $luaLongBracketWordpressBlock . "\n";
echo "phpHeredocWordpressBlock:\n" . $phpHeredocWordpressBlock . "\n";
echo "rstWordpressBlock:\n" . $rstWordpressBlock . "\n";
echo "tsxWordpressBlock:\n" . $tsxWordpressBlock . "\n";
echo "cmakeWordpressBlock:\n" . $cmakeWordpressBlock . "\n";
echo "nginxWordpressBlock:\n" . $nginxWordpressBlock . "\n";
echo "twigWordpressBlock:\n" . $twigWordpressBlock . "\n";
echo "handlebarsWordpressBlock:\n" . $handlebarsWordpressBlock . "\n";
echo "mermaidWordpressBlock:\n" . $mermaidWordpressBlock . "\n";
echo "htmlEmbeddedWordpressBlock:\n" . $htmlEmbeddedWordpressBlock . "\n";
echo "htmlPhpWordpressBlock:\n" . $htmlPhpWordpressBlock . "\n";
echo "graphqlWordpressBlock:\n" . $graphqlWordpressBlock . "\n";
echo "phpAttributeWordpressBlock:\n" . $phpAttributeWordpressBlock . "\n";
echo "asciidocWordpressBlock:\n" . $asciidocWordpressBlock . "\n";
echo "phpdocWordpressBlock:\n" . $phpdocWordpressBlock . "\n";
echo "terraformWordpressBlock:\n" . $terraformWordpressBlock . "\n";
echo "liquidWordpressBlock:\n" . $liquidWordpressBlock . "\n";
echo "elmWordpressBlock:\n" . $elmWordpressBlock . "\n";
echo "jsoncWordpressBlock:\n" . $jsoncWordpressBlock . "\n";
echo "kotlinWordpressBlock:\n" . $kotlinWordpressBlock . "\n";
echo "scalaWordpressBlock:\n" . $scalaWordpressBlock . "\n";
echo "elixirWordpressBlock:\n" . $elixirWordpressBlock . "\n";
echo "vueWordpressBlock:\n" . $vueWordpressBlock . "\n";
echo "vueCustomWordpressBlock:\n" . $vueCustomWordpressBlock . "\n";
echo "ocamlWordpressBlock:\n" . $ocamlWordpressBlock . "\n";
echo "juliaWordpressBlock:\n" . $juliaWordpressBlock . "\n";
echo "awkWordpressBlock:\n" . $awkWordpressBlock . "\n";
echo "batchWordpressBlock:\n" . $batchWordpressBlock . "\n";
echo "matlabWordpressBlock:\n" . $matlabWordpressBlock . "\n";
echo "fishWordpressBlock:\n" . $fishWordpressBlock . "\n";
echo "sedWordpressBlock:\n" . $sedWordpressBlock . "\n";
echo "bibtexWordpressBlock:\n" . $bibtexWordpressBlock . "\n";
echo "vimWordpressBlock:\n" . $vimWordpressBlock . "\n";
echo "schemeWordpressBlock:\n" . $schemeWordpressBlock . "\n";
echo "csvWordpressBlock:\n" . $csvWordpressBlock . "\n";
echo "erlangWordpressBlock:\n" . $erlangWordpressBlock . "\n";
echo "objectiveCWordpressBlock:\n" . $objectiveCWordpressBlock . "\n";
echo "rakuWordpressBlock:\n" . $rakuWordpressBlock . "\n";
echo "fennelWordpressBlock:\n" . $fennelWordpressBlock . "\n";
echo "mesonWordpressBlock:\n" . $mesonWordpressBlock . "\n";
echo "justWordpressBlock:\n" . $justWordpressBlock . "\n";
echo "protobufWordpressBlock:\n" . $protobufWordpressBlock . "\n";
echo "tclWordpressBlock:\n" . $tclWordpressBlock . "\n";
echo "lineHighlightWordpressBlock:\n" . $lineHighlightWordpressBlock . "\n";
echo "dWordpressBlock:\n" . $dWordpressBlock . "\n";
echo "commonLispWordpressBlock:\n" . $commonLispWordpressBlock . "\n";
echo "pascalWordpressBlock:\n" . $pascalWordpressBlock . "\n";
echo "groovyWordpressBlock:\n" . $groovyWordpressBlock . "\n";
echo "crystalWordpressBlock:\n" . $crystalWordpressBlock . "\n";
echo "shellSessionWordpressBlock:\n" . $shellSessionWordpressBlock . "\n";
echo "nimWordpressBlock:\n" . $nimWordpressBlock . "\n";
echo "vWordpressBlock:\n" . $vWordpressBlock . "\n";
echo "idrisWordpressBlock:\n" . $idrisWordpressBlock . "\n";
echo "coqWordpressBlock:\n" . $coqWordpressBlock . "\n";
echo "agdaWordpressBlock:\n" . $agdaWordpressBlock . "\n";
echo "purescriptWordpressBlock:\n" . $purescriptWordpressBlock . "\n";
echo "fsharpWordpressBlock:\n" . $fsharpWordpressBlock . "\n";
echo "rakuPodQuoteWordpressBlock:\n" . $rakuPodQuoteWordpressBlock . "\n";
echo "customThemeWordpressBlock:\n" . $customThemeWordpressBlock . "\n";
