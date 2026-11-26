<?php
// Runs Url_Seo_State::same_url() against the fixture. On a site with SCOS:
// wp eval-file same-url-test.php <path-to-same-url-cases.json>
$file  = $args[0] ?? __DIR__ . '/fixtures/same-url-cases.json';
$cases = json_decode( file_get_contents( $file ), true );
$fail  = 0;
foreach ( $cases as $c ) {
	$got = \SiteEssentials\Modules\SeoMeta\Url_Seo_State::same_url( $c['a'], $c['b'] );
	if ( $got !== $c['same'] ) { $fail++; echo "FAIL {$c['why']}: {$c['a']} | {$c['b']}\n"; }
}
echo ( count( $cases ) - $fail ) . '/' . count( $cases ) . " pass\n";
