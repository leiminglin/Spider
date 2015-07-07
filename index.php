<?php
function getRemoteLmlPhp(){
	$cache_filename = 'lml.min.php';
	$remotelib = 'http://pro8091d8.pic12.websiteonline.cn/upload/lmlphp-release-2014-08-27-v1.txt';
	if( file_exists( $cache_filename ) ) {
		$cachemtime = filemtime($cache_filename);
		if( $cachemtime + 86400 > time() ){
			require $cache_filename;
			return;
		}
		$header = get_headers($remotelib);
		foreach ($header as $k){
			if( preg_match('/^Last-Modified:/i', $k) ){
				$lastmtime = strtotime(preg_replace('/^Last-Modified:/i', '', $k));
				break;
			}
		}
		if( $lastmtime <= $cachemtime ){
			touch($cache_filename);
			require $cache_filename;
			return;
		}
	}
	$code = file_get_contents( $remotelib );
	file_put_contents($cache_filename, $code);
	eval('?>'.$code);
}
getRemoteLmlPhp();

// require 'lml.php.txt';
// var_dump($_SERVER);
// var_dump($argv);

// var_dump(get_defined_constants());
// var_dump(get_defined_vars());


lml()->app()->run();
