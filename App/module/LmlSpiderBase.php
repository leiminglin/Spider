<?php
if (!function_exists('gzdecode')) {
	function gzdecode($data)
	{
		return gzinflate(substr($data,10,-8));
	}
}

/*
if (!function_exists('gzdecode')) {
	function gzdecode ($data) {
		$flags = ord(substr($data, 3, 1));
		$headerlen = 10;
		$extralen = 0;
		$filenamelen = 0;
		if ($flags & 4) {
			$extralen = unpack('v' ,substr($data, 10, 2));
			$extralen = $extralen[1];
			$headerlen += 2 + $extralen;
		}
		if ($flags & 8) // Filename
			$headerlen = strpos($data, chr(0), $headerlen) + 1;
		if ($flags & 16) // Comment
			$headerlen = strpos($data, chr(0), $headerlen) + 1;
		if ($flags & 2) // CRC at end of file
			$headerlen += 2;
		$unpacked = @gzinflate(substr($data, $headerlen));
		if ($unpacked === FALSE)
			$unpacked = $data;
		return $unpacked;
	}
}
*/

abstract class LmlSpiderBase extends LmlBase{
	
	/**
	 * 根据链接获取完整URL
	 * 
	 * @param string $page_url 访问的页面地址
	 * @param string $link_url 页面中链接的地址
	 * @return string
	 */
	public static function getPageLinkUrl($page_url, $link_url){
		if(preg_match('/^http:\/\/|^https:\/\//', $link_url)){
			return $link_url;
		}
		$return_link = '';
		$homeurl = self::getDomainUrlByUrl($page_url);
		if( substr($link_url, 0, 1) == '/' ) {
			// 首字母是/时，在域名后加上当前地址
			$return_link = $homeurl.$link_url;
		}else if( substr($link_url, 0, 1) == '?' ) {
			// 当首字母是问号时，需要匹配页面URL中问号之前的部分加上当前地址。
			$url_arr = explode('?', $page_url, 2);
			$return_link = $url_arr[0].'?'.$link_url;
		}else{
			// 首字母是是其他字母时，在页面URL中最后一个 / 后加上当前地址
			if($page_url != $homeurl){
				$path = substr($page_url, 0, strrpos($page_url, '/')+1);
			}else{
				$path = $homeurl.'/';
			}
			$return_link = $path.$link_url;
		}
		return $return_link;
	}
	
	/**
	 * 根据链接获取域名首页地址
	 * @param string $url
	 * @return string
	 */
	public static function getDomainUrlByUrl( $url ){
		preg_match( '/^(?:http|https):\/\/[^\/]+/i', $url, $matches );
		if( isset($matches[0]) ){
			return $matches[0];
		}
		return $url;
	}
	
	/**
	 * 获取页面 meta 中的字符编码
	 * @param unknown_type $content
	 * @return string
	 */
	public static function getPageCharset( $content ){
		// <meta http-equiv="Content-Type" content="text/html; charset=gb2312">
		$matches = '';
		preg_match("/<meta.+charset=([a-zA-Z0-9]{1,10}).*?>/i", $content, $matches);
		return isset($matches[1])?$matches[1]:'';
	}
	
	/**
	 * 将内容在控制台输出
	 * @param string $s
	 */
	public static function pl($s){
		echo '['.date("Y-m-d H:i:s").'] '.$s."\n";
		ob_flush();
		flush();
	}
	
	/**
	 * 发出请求
	 */
	public static function getRemoteContent( $url ){
		if( !$url ){
			return ;
		}
		
		$context = array(
			'http' => array (
				'timeout' => 10,
				'method' => 'GET',
				'header' => "User-Agent: Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)\n"
					."Accept: */*\n"
					."Accept-Language: zh-cn,zh-tw\n"
					."Accept-Encoding: gzip\n"
					// ."Connection: keep-alive\n"
			)
		);
		$http_response_header = '';
		self::pl("start: request url is ".$url);
		$htmlpage = @file_get_contents( $url, false, stream_context_create($context) );
		self::pl("end: request url is ".$url);
		self::pl("info: get page length is ".strlen($htmlpage));
		
		foreach ( $http_response_header as $k=>$v){
			if( preg_match('/^Content-Encoding:\s*gzip/i', $v) ){
				self::pl('start: gzdecode');
				$htmlpage = gzdecode($htmlpage);
				self::pl('end: gzdecode');
			}
		}
		$charset = self::getPageCharset($htmlpage);
		if( $charset != 'utf-8' ){
			self::pl('start: convert '.$charset.' to utf-8');
			
			
			/**
			 * - to C
			 */
			// setlocale(LC_ALL, 'zh_CN');
			// setlocale(LC_CTYPE, 'POSIX');
			// setlocale(LC_CTYPE, 'cs_CZ');
			$htmlpage = iconv($charset, 'utf-8//IGNORE', $htmlpage);
			//ini_set('mbstring.substitute_character', "-");
			//$htmlpage = mb_convert_encoding($htmlpage, 'UTF-8', $charset);
			
			
			// err 
			// $htmlpage = iconv($charset, 'utf-8//TRANSLIT', $htmlpage);
			
			/**
			 * err
			 */
			// $htmlpage = iconv($charset, 'utf-8', $htmlpage);

			/**
			 * - to ?
			 */
			//$htmlpage = mb_convert_encoding($htmlpage, 'utf-8', $charset);

			self::pl('end: convert '.$charset.' to utf-8');
		}
		
		return $htmlpage;
	}
	
	public $pageContent;
	
	public function run() {
		while ( ($url = $this->getNextUrl()) != '' ){
			$this->pageContent = self::getRemoteContent($url);
			$this->process();
			sleep(1);
		}
	}
	
	/**
	 * entrance method
	 */
	abstract function start();
	
	/**
	 * next url
	 */
	abstract function getNextUrl();
	
	/**
	 * process data
	 */
	abstract function process();
	
	
}


