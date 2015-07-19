<?php
abstract class LmlSiteSpider extends LmlSpiderBase{

    public $links = array();
    public $home = 'http://test.com';
    public $targetLinkRegexp = '/href="(.*?\.html)"/';
    public $titleRegexp = '/title.*?>(.*?)<\/div>/';
    public $contentRegexp = '/content.*?>([\s\S]*?)<\/div[\s\S]*?<div\sclass="content/';
    public $imageTagTitleSuffix = '-LMLPHP';

    // this for process img
    public static $title = '';

    public $dbconfig = array(
        'hostname' => 'localhost',
        'hostport' => '3306',
        'username' => 'root',
        'password' => 'root',
        'database' => 'testdb',
        'charset' => 'utf8',
        'persist' => false,
        'dbprefix' => '',
    );

    public static $config = array(
        'hostname' => 'localhost',
        'database' => 'testdb',
        'username' => 'root',
        'password' => 'root',
    );

    public function start()
    {
        $this->pageContent = self::getRemoteContent($this->home);
        preg_match_all('/<a.*?>[\s\S]*?<\/a>/', $this->pageContent, $matches);
        $this->links = isset($matches[0])?$matches[0]:array();
        self::pl('match link length is '. count($this->links));
        $this->links = array_unique($this->links);
        self::pl('unique links count is '. count($this->links));
        $this->run();
    }

    public function getNextUrl()
    {
        while(count($this->links)){
            $x = array_pop($this->links);
            if(preg_match($this->targetLinkRegexp, $x, $m)){
                $fullUrl = self::getPageLinkUrl($this->home, $m[1]);
                self::pl('next url is '.$fullUrl);

                // check is done before
                if($this->checkWhetherCrawled($fullUrl) ){
                    continue;
                }

                return $fullUrl;
            }
        }
        return '';
    }

    public function process($url)
    {
        preg_match($this->titleRegexp, $this->pageContent, $matches_title);
        preg_match($this->contentRegexp, $this->pageContent, $matches_content);

        self::pl(isset($matches_title[1])?$matches_title[1]:'no match title');
        self::pl(isset($matches_content[0])?'content length is '.strlen($matches_content[0]):'no match content');

        $title = isset($matches_title[1])?$matches_title[1]:'no match title';
        $content = isset($matches_content[1])?$matches_content[1]:'no match content';

        self::$title = $title;

        $content = preg_replace('/<!--.*?-->/', '', $content);

        $content = str_replace('<p>&nbsp;</p>', '', $content);
        $content = str_replace('<p></p>', '', $content);
        $content = preg_replace('/<p>[\s]+<\/p>/', '', $content);

        $content = preg_replace('/<p.*?>/', '<p>', $content);
        
        $content = $this->processContent($content);

        $content = preg_replace_callback('/<img[\s\S]*?>/', array($this, 'processImg'), $content);

        lml()->fileDebug($url, APP_PATH.'data'.date('Ymd').'.txt');
        lml()->fileDebug($title, APP_PATH.'data'.date('Ymd').'.txt');
        lml()->fileDebug($content, APP_PATH.'data'.date('Ymd').'.txt');

        // save to mysql db
        $this->save($url, $title, $content);
    }

    public function processImg($s){
        $matches = '';
        preg_match('/src=.([^\'"]+)/', $s[0], $matches);

        if(!isset($matches[1])){
            throw new LmlException('image no src attribute!');
        }
        $link = self::getPageLinkUrl($this->home, $matches[1]);
        $x = getimagesize($link);
        $width = $x[0];
        $height = $x[1];

        if($width > 640){
            $height = round(640 * $height / $width);
            $width = 640;
        }

        $re = '<img osrc="'.$link.'" osrc-bak="'.$link.'" alt="'.
        self::$title.$this->imageTagTitleSuffix.'" title="'.
        self::$title.$this->imageTagTitleSuffix.'" width="'.$width.'" height="'.$height.'">';
        self::pl('return img is ' . $re);
        return $re;
    }

    abstract function checkWhetherCrawled($url);
    /*
    {
        $db = MysqlPdo::getInstance(self::$config);
        $sql = "select url from table_name where url = '".$url."'";
        $rs = $db->select($sql);
        self::pl('sql is '. $sql);
        if(count($rs) > 0){
            self::pl('url :'.$this->home.$m[1].' had crawled.');
            return true;
        }
        return false;
    }
    */

    abstract function save($url, $title, $content);
    /*
    {
        $data = array($url, 1, time(), $title, $content);
        $db = MysqlPdo::getInstance(self::$config);
        $x = $db->insert("insert into table_name(url, type, createtime, title, content) values(?,?,?,?,?)", $data);
    }
    */
    
   abstract function processContent($content);
}

