<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PortLibs\Pandoc\TagSoupParser;
use PortLibs\Pandoc\TagSoupTag;

$root = dirname(__DIR__);
$stackProject = $root . '/.upstream-cache/pandoc-current';
$requireMatches = in_array('--require-matches', $argv, true);
$outputJson = optionValue($argv, '--output-json') ?? $root . '/lanes/pandoc/reports/tagsoup-token-comparison.json';

$samples = [
    'malformed-formatting-source-order' => '<p><b>one<i>two</b>three</i>',
    'attributes-entities-self-closing' => '<BR class=x disabled data-v="A&amp;B"/>',
    'declaration-comment-cdata-script' => '<!doctype html><!--c--><![CDATA[x<y]]><script>a < b && c</script>',
    'processing-instruction-and-declaration' => '<?xml version="1.0"?><!review value=yes>',
    'entity-resolution' => 'A&nbsp;B &copy and &NotEqualTilde; &#x41;',
    'bogus-angle-markup' => '<> <3 </> </$funky>',
    'attribute-without-name' => '<!review "loose" value=yes>',
];

$script = writeHaskellTagSoupEmitter();
$comparisons = [];
$matchCount = 0;
foreach ($samples as $name => $source) {
    $haskell = runHaskellTagSoup($script, $stackProject, $source);
    $php = phpTagSoupTokens($source);
    $matched = $haskell === $php;
    if ($matched) {
        $matchCount++;
    }
    $comparisons[] = [
        'name' => $name,
        'source' => $source,
        'matched' => $matched,
        'php' => $php,
        'haskell' => $haskell,
    ];
}

@unlink($script);

$report = [
    'schemaVersion' => 1,
    'tool' => basename(__FILE__),
    'upstream' => [
        'project' => '.upstream-cache/pandoc-current',
        'package' => 'tagsoup',
        'version' => '0.14.8',
    ],
    'total' => count($comparisons),
    'matched' => $matchCount,
    'mismatched' => count($comparisons) - $matchCount,
    'status' => $matchCount === count($comparisons) ? 'passed' : 'mismatched',
    'comparisons' => $comparisons,
];

$dir = dirname($outputJson);
if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
    throw new RuntimeException('Unable to create output directory: ' . $dir);
}
file_put_contents($outputJson, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

fwrite(STDOUT, "Wrote {$outputJson}\n");
fwrite(STDOUT, "Summary: {$matchCount}/" . count($comparisons) . " TagSoup token samples matched\n");
if ($requireMatches && $matchCount !== count($comparisons)) {
    fwrite(STDERR, "TagSoup token comparison mismatches remain.\n");
    exit(1);
}

/**
 * @param list<string> $argv
 */
function optionValue(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }

    return null;
}

function writeHaskellTagSoupEmitter(): string
{
    $script = <<<'HS'
import Data.Char (ord)
import Text.HTML.TagSoup

jsonString :: String -> String
jsonString s = "\"" ++ concatMap esc s ++ "\""
  where
    esc '"' = "\\\""
    esc '\\' = "\\\\"
    esc '\b' = "\\b"
    esc '\f' = "\\f"
    esc '\n' = "\\n"
    esc '\r' = "\\r"
    esc '\t' = "\\t"
    esc c
      | ord c < 32 = "\\u" ++ pad4 (showHex (ord c))
      | otherwise = [c]

showHex :: Int -> String
showHex n = let digits = "0123456789abcdef"
                go x | x < 16 = [digits !! x]
                     | otherwise = go (x `div` 16) ++ [digits !! (x `mod` 16)]
            in go n

pad4 :: String -> String
pad4 s = replicate (4 - length s) '0' ++ s

attrsJson :: [(String, String)] -> String
attrsJson attrs = "[" ++ join "," (map attrJson attrs) ++ "]"
  where attrJson (name, value) = "{\"name\":" ++ jsonString name ++ ",\"value\":" ++ jsonString value ++ "}"

tagJson :: Tag String -> String
tagJson (TagOpen name attrs) = "{\"type\":\"open\",\"name\":" ++ jsonString name ++ ",\"text\":\"\",\"attributes\":" ++ attrsJson attrs ++ "}"
tagJson (TagClose name) = "{\"type\":\"close\",\"name\":" ++ jsonString name ++ ",\"text\":\"\",\"attributes\":[]}"
tagJson (TagText text) = "{\"type\":\"text\",\"name\":\"\",\"text\":" ++ jsonString text ++ ",\"attributes\":[]}"
tagJson (TagComment text) = "{\"type\":\"comment\",\"name\":\"\",\"text\":" ++ jsonString text ++ ",\"attributes\":[]}"
tagJson (TagWarning text) = "{\"type\":\"warning\",\"name\":\"\",\"text\":" ++ jsonString text ++ ",\"attributes\":[]}"
tagJson (TagPosition row col) = "{\"type\":\"position\",\"name\":\"\",\"text\":\"\",\"attributes\":[],\"row\":" ++ show row ++ ",\"column\":" ++ show col ++ "}"

join :: String -> [String] -> String
join _ [] = ""
join _ [x] = x
join sep (x:xs) = x ++ sep ++ join sep xs

main :: IO ()
main = do
  input <- getContents
  putStrLn $ "[" ++ join "," (map tagJson (parseTags input :: [Tag String])) ++ "]"
HS;

    $path = sys_get_temp_dir() . '/pandoc-tagsoup-emitter-' . bin2hex(random_bytes(6)) . '.hs';
    file_put_contents($path, $script);

    return $path;
}

/**
 * @return list<array{type:string,name:string,text:string,attributes:list<array{name:string,value:string}>}>
 */
function runHaskellTagSoup(string $script, string $stackProject, string $source): array
{
    $process = proc_open(
        ['stack', 'runghc', '--package', 'tagsoup', $script],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $stackProject
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to run stack runghc for TagSoup comparison');
    }
    fwrite($pipes[0], $source);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) {
        throw new RuntimeException("Haskell TagSoup emitter failed with exit {$exit}: {$stderr}");
    }

    $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Haskell TagSoup emitter returned non-array JSON');
    }

    return $decoded;
}

/**
 * @return list<array{type:string,name:string,text:string,attributes:list<array{name:string,value:string}>}>
 */
function phpTagSoupTokens(string $source): array
{
    $tokens = (new TagSoupParser())->parse($source);

    return array_map(
        static fn (TagSoupTag $token): array => [
            'type' => $token->type,
            'name' => $token->name,
            'text' => $token->text,
            'attributes' => $token->attributes,
        ],
        $tokens
    );
}
