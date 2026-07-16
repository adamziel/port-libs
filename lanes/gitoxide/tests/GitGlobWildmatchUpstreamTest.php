<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitGlob;

$multiMatch = static function (string $patternText, string $text): array {
    $pattern = GitGlob::parse($patternText);
    if ($pattern === null) {
        throw new RuntimeException("Pattern did not parse: {$patternText}");
    }

    return [
        (int) $pattern->matchesRepoRelativePath(
            $text,
            GitGlob::basenameStartPosition($text),
            false,
            GitGlob::CASE_SENSITIVE,
            GitGlob::WILDMATCH_NO_MATCH_SLASH_LITERAL,
        ),
        (int) $pattern->matchesRepoRelativePath(
            $text,
            GitGlob::basenameStartPosition($text),
            false,
            GitGlob::CASE_FOLD,
            GitGlob::WILDMATCH_NO_MATCH_SLASH_LITERAL,
        ),
        (int) GitGlob::wildmatch($pattern->text, $text),
        (int) GitGlob::wildmatch($pattern->text, $text, GitGlob::WILDMATCH_IGNORE_CASE),
    ];
};

$wildmatchCorpus = static function (): array {
    $json = <<<'JSON'
[[1,1,1,1,"foo","foo"],[0,0,0,0,"foo","bar"],[1,1,1,1,"foo","???"],[0,0,0,0,"foo","??"],[1,1,1,1,"foo","*"],[1,1,1,1,"foo","f*"],[0,0,0,0,"foo","*f"],[1,1,1,1,"foo","*foo*"],[1,1,1,1,"foobar","*ob*a*r*"],[1,1,1,1,"aaaaaaabababab","*ab"],[1,1,1,1,"foo*","foo\\*"],[0,0,0,0,"foobar","foo\\*bar"],[1,1,1,1,"f\\oo","f\\\\oo"],[1,1,1,1,"ball","*[al]?"],[0,0,0,0,"ten","[ten]"],[1,1,1,1,"ten","**[!te]"],[0,0,0,0,"ten","**[!ten]"],[1,1,1,1,"ten","t[a-g]n"],[0,0,0,0,"ten","t[!a-g]n"],[1,1,1,1,"ton","t[!a-g]n"],[1,1,1,1,"ton","t[^a-g]n"],[1,1,1,1,"a]b","a[]]b"],[1,1,1,1,"a-b","a[]-]b"],[1,1,1,1,"a]b","a[]-]b"],[0,0,0,0,"aab","a[]-]b"],[1,1,1,1,"aab","a[]a-]b"],[1,1,1,1,"]","]"],[0,0,1,1,"foo/baz/bar","foo*bar"],[0,0,1,1,"foo/baz/bar","foo**bar"],[1,1,1,1,"foobazbar","foo**bar"],[1,1,1,1,"foo/baz/bar","foo/**/bar"],[1,1,0,0,"foo/baz/bar","foo/**/**/bar"],[1,1,1,1,"foo/b/a/z/bar","foo/**/bar"],[1,1,1,1,"foo/b/a/z/bar","foo/**/**/bar"],[1,1,0,0,"foo/bar","foo/**/bar"],[1,1,0,0,"foo/bar","foo/**/**/bar"],[0,0,1,1,"foo/bar","foo?bar"],[0,0,1,1,"foo/bar","foo[/]bar"],[0,0,1,1,"foo/bar","foo[^a-z]bar"],[0,0,1,1,"foo/bar","f[^eiu][^eiu][^eiu][^eiu][^eiu]r"],[1,1,1,1,"foo-bar","f[^eiu][^eiu][^eiu][^eiu][^eiu]r"],[1,1,0,0,"foo","**/foo"],[1,1,1,1,"XXX/foo","**/foo"],[1,1,1,1,"bar/baz/foo","**/foo"],[0,0,1,1,"bar/baz/foo","*/foo"],[0,0,1,1,"foo/bar/baz","**/bar*"],[1,1,1,1,"deep/foo/bar/baz","**/bar/*"],[0,0,1,1,"deep/foo/bar/baz/","**/bar/*"],[1,1,1,1,"deep/foo/bar/baz/","**/bar/**"],[0,0,0,0,"deep/foo/bar","**/bar/*"],[1,1,1,1,"deep/foo/bar/","**/bar/**"],[0,0,1,1,"foo/bar/baz","**/bar**"],[1,1,1,1,"foo/bar/baz/x","*/bar/**"],[0,0,1,1,"deep/foo/bar/baz/x","*/bar/**"],[1,1,1,1,"deep/foo/bar/baz/x","**/bar/*/*"],[0,0,0,0,"acrt","a[c-c]st"],[1,1,1,1,"acrt","a[c-c]rt"],[0,0,0,0,"]","[!]-]"],[1,1,1,1,"a","[!]-]"],[0,0,0,0,"","\\"],[0,0,0,0,"XXX/\\","*/\\"],[1,1,1,1,"XXX/\\","*/\\\\"],[1,1,1,1,"foo","foo"],[1,1,1,1,"@foo","@foo"],[0,0,0,0,"foo","@foo"],[1,1,1,1,"[ab]","\\[ab]"],[1,1,1,1,"[ab]","[[]ab]"],[1,1,1,1,"[ab]","[[:]ab]"],[0,0,0,0,"[ab]","[[::]ab]"],[1,1,1,1,"[ab]","[[:digit]ab]"],[1,1,1,1,"[ab]","[\\[:]ab]"],[1,1,1,1,"?a?b","\\??\\?b"],[1,1,1,1,"abc","\\a\\b\\c"],[1,1,1,1,"foo/bar/baz/to","**/t[o]"],[1,1,1,1,"a1B","[[:alpha:]][[:digit:]][[:upper:]]"],[0,1,0,1,"a","[[:digit:][:upper:][:space:]]"],[1,1,1,1,"A","[[:digit:][:upper:][:space:]]"],[1,1,1,1,"1","[[:digit:][:upper:][:space:]]"],[0,0,0,0,"1","[[:digit:][:upper:][:spaci:]]"],[1,1,1,1," ","[[:digit:][:upper:][:space:]]"],[0,0,0,0,".","[[:digit:][:upper:][:space:]]"],[1,1,1,1,".","[[:digit:][:punct:][:space:]]"],[1,1,1,1,"5","[[:xdigit:]]"],[1,1,1,1,"f","[[:xdigit:]]"],[1,1,1,1,"D","[[:xdigit:]]"],[1,1,1,1,"_","[[:alnum:][:alpha:][:blank:][:cntrl:][:digit:][:graph:][:lower:][:print:][:punct:][:space:][:upper:][:xdigit:]]"],[1,1,1,1,".","[^[:alnum:][:alpha:][:blank:][:cntrl:][:digit:][:lower:][:space:][:upper:][:xdigit:]]"],[1,1,1,1,"5","[a-c[:digit:]x-z]"],[1,1,1,1,"b","[a-c[:digit:]x-z]"],[1,1,1,1,"y","[a-c[:digit:]x-z]"],[0,0,0,0,"q","[a-c[:digit:]x-z]"],[1,1,1,1,"]","[\\\\-^]"],[0,0,0,0,"[","[\\\\-^]"],[1,1,1,1,"-","[\\-_]"],[1,1,1,1,"]","[\\]]"],[0,0,0,0,"\\]","[\\]]"],[0,0,0,0,"\\","[\\]]"],[0,0,0,0,"ab","a[]b"],[0,0,0,0,"ab","[!"],[0,0,0,0,"ab","[-"],[1,1,1,1,"-","[-]"],[0,0,0,0,"-","[a-"],[0,0,0,0,"-","[!a-"],[1,1,1,1,"-","[--A]"],[1,1,1,1,"5","[--A]"],[1,1,1,1," ","[ --]"],[1,1,1,1,"$","[ --]"],[1,1,1,1,"-","[ --]"],[0,0,0,0,"0","[ --]"],[1,1,1,1,"-","[---]"],[1,1,1,1,"-","[------]"],[0,0,0,0,"j","[a-e-n]"],[1,1,1,1,"-","[a-e-n]"],[1,1,1,1,"a","[!------]"],[0,0,0,0,"[","[]-a]"],[1,1,1,1,"^","[]-a]"],[0,0,0,0,"^","[!]-a]"],[1,1,1,1,"[","[!]-a]"],[1,1,1,1,"^","[a^bc]"],[1,1,1,1,"-b]","[a-]b]"],[0,0,0,0,"\\","[\\]"],[1,1,1,1,"\\","[\\\\]"],[0,0,0,0,"\\","[!\\\\]"],[1,1,1,1,"G","[A-\\\\]"],[0,0,0,0,"aaabbb","b*a"],[0,0,0,0,"aabcaa","*ba*"],[1,1,1,1,",","[,]"],[1,1,1,1,",","[\\\\,]"],[1,1,1,1,"\\","[\\\\,]"],[1,1,1,1,"-","[,-.]"],[0,0,0,0,"+","[,-.]"],[0,0,0,0,"-.]","[,-.]"],[1,1,1,1,"2","[\\1-\\3]"],[1,1,1,1,"3","[\\1-\\3]"],[0,0,0,0,"4","[\\1-\\3]"],[1,1,1,1,"\\","[[-\\]]"],[1,1,1,1,"[","[[-\\]]"],[1,1,1,1,"]","[[-\\]]"],[0,0,0,0,"-","[[-\\]]"],[1,1,1,1,"-adobe-courier-bold-o-normal--12-120-75-75-m-70-iso8859-1","-*-*-*-*-*-*-12-*-*-*-m-*-*-*"],[0,0,0,0,"-adobe-courier-bold-o-normal--12-120-75-75-X-70-iso8859-1","-*-*-*-*-*-*-12-*-*-*-m-*-*-*"],[0,0,0,0,"-adobe-courier-bold-o-normal--12-120-75-75-/-70-iso8859-1","-*-*-*-*-*-*-12-*-*-*-m-*-*-*"],[1,1,1,1,"XXX/adobe/courier/bold/o/normal//12/120/75/75/m/70/iso8859/1","XXX/*/*/*/*/*/*/12/*/*/*/m/*/*/*"],[0,0,0,0,"XXX/adobe/courier/bold/o/normal//12/120/75/75/X/70/iso8859/1","XXX/*/*/*/*/*/*/12/*/*/*/m/*/*/*"],[1,1,1,1,"abcd/abcdefg/abcdefghijk/abcdefghijklmnop.txt","**/*a*b*g*n*t"],[0,0,0,0,"abcd/abcdefg/abcdefghijk/abcdefghijklmnop.txtz","**/*a*b*g*n*t"],[0,0,0,0,"foo","*/*/*"],[0,0,0,0,"foo/bar","*/*/*"],[1,1,1,1,"foo/bba/arr","*/*/*"],[0,0,1,1,"foo/bb/aa/rr","*/*/*"],[1,1,1,1,"foo/bb/aa/rr","**/**/**"],[1,1,1,1,"abcXdefXghi","*X*i"],[0,0,1,1,"ab/cXd/efXg/hi","*X*i"],[1,1,1,1,"ab/cXd/efXg/hi","*/*X*/*/*i"],[1,1,1,1,"ab/cXd/efXg/hi","**/*X*/**/*i"],[0,0,0,0,"foo","fo"],[1,1,1,1,"foo/bar","foo/bar"],[1,1,1,1,"foo/bar","foo/*"],[0,0,1,1,"foo/bba/arr","foo/*"],[1,1,1,1,"foo/bba/arr","foo/**"],[0,0,1,1,"foo/bba/arr","foo*"],[0,0,1,1,"foo/bba/arr","foo/*arr"],[0,0,1,1,"foo/bba/arr","foo/**arr"],[0,0,0,0,"foo/bba/arr","foo/*z"],[0,0,0,0,"foo/bba/arr","foo/**z"],[0,0,1,1,"foo/bar","foo?bar"],[0,0,1,1,"foo/bar","foo[/]bar"],[0,0,1,1,"foo/bar","foo[^a-z]bar"],[0,0,1,1,"ab/cXd/efXg/hi","*Xg*i"],[0,1,0,1,"a","[A-Z]"],[1,1,1,1,"A","[A-Z]"],[0,1,0,1,"A","[a-z]"],[1,1,1,1,"a","[a-z]"],[0,1,0,1,"a","[[:upper:]]"],[1,1,1,1,"A","[[:upper:]]"],[0,1,0,1,"A","[[:lower:]]"],[1,1,1,1,"a","[[:lower:]]"],[0,1,0,1,"A","[B-Za]"],[1,1,1,1,"a","[B-Za]"],[0,1,0,1,"A","[B-a]"],[1,1,1,1,"a","[B-a]"],[0,1,0,1,"z","[Z-y]"],[1,1,1,1,"Z","[Z-y]"]]
JSON;

    $cases = json_decode($json, true);
    if (!is_array($cases)) {
        throw new RuntimeException('Unable to decode upstream wildmatch corpus');
    }

    return $cases;
};

return [
    'upstream gix-glob wildmatch/mod.rs corpus' => static function (TestRunner $t) use ($multiMatch, $wildmatchCorpus): void {
        $cases = $wildmatchCorpus();
        $t->same(183, count($cases));

        foreach ($cases as $index => $case) {
            [$pathMatch, $pathImatch, $globMatch, $globImatch, $text, $patternText] = $case;

            $t->same(
                [$pathMatch, $pathImatch, $globMatch, $globImatch],
                $multiMatch($patternText, $text),
                "wildmatch corpus #{$index}: {$patternText} against {$text}",
            );
        }
    },
];
