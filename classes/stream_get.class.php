<?php
class stream_get extends getinfo
{
	function stream_get()
	{
		$this->config();
		$this->max_size_other_host = $this->file_size_limit;
		$this->load_jobs();
		$this->load_cookies();
		$this->cookie = '';
		if (preg_match('%^(http.+.index.php)/(.*?)/(.*?)/%U', $this->self, $redir)) {
			if (stristr($redir[3], 'mega_')) $this->downloadmega($redir[3]);
			else $this->download($redir[3]);
		}
		elseif (isset($_REQUEST['file'])) {
			if (stristr($_REQUEST['file'], 'mega_')) $this->downloadmega($_REQUEST['file']);
			else $this->download($_REQUEST['file']);
		}
		else{
			include ("hosts/hosts.php");
			ksort($host);
			$this->list_host = $host;
			$this->load_account();
		}
		if (isset($_COOKIE['owner'])) {
			$this->owner = $_COOKIE['owner'];
		}
		else {
			$this->owner = intval(rand() * 10000);
			setcookie('owner', $this->owner, 0);
		}
	}
	function download($hash)
	{
		error_reporting(0);
		$job = $this->lookup_job($hash);
		if (!$job) {
			sleep(15);
			header("HTTP/1.1 404 Not Found");
			die($this->lang['errorget']);
		}
		if (($_SERVER['REMOTE_ADDR'] !== $job['ip']) && $this->privateip == true) {
			sleep(15);
			die($this->lang['errordl']);
		}
		if ($this->get_load() > $this->max_load) sleep(15);
		$link = '';
		$filesize = $job['size'];
		$filename = $this->download_prefix . Tools_get::convert_name($job['filename']) . $this->download_suffix;
		$directlink = urldecode($job['directlink']['url']);
		$this->cookie = $job['directlink']['cookies'];
		$link = $directlink;
		$link = str_replace(" ", "%20", $link);
		if (!$link) {
			sleep(15);
			header("HTTP/1.1 404 Not Found");
			$this->error1('erroracc');
		}
		if ($job['proxy'] != 0 && $this->redirdl == true) {
			list($ip, ) = explode(":", $job['proxy']);
			if($_SERVER['REMOTE_ADDR'] != $ip) {
				$this->wrong_proxy($job['proxy']);
			}
			else {
				header('Location: '.$link);
				die;
			}
		}
		$range = '';
		if (isset($_SERVER['HTTP_RANGE'])) {
			$range = substr($_SERVER['HTTP_RANGE'], 6);
			list($start, $end) = explode('-', $range);
			$new_length = $filesize - $start;
		}
		$port = 80;
		$schema = parse_url(trim($link));
		$host = $schema['host'];
		$scheme = "http://";
		$gach = explode("/", $link);
		list($path1, $path) = explode($gach[2], $link);
		if (isset($schema['port'])) $port = $schema['port'];
		elseif ($schema['scheme'] == 'https') {
			$scheme = "ssl://";
			$port = 443;
		}
		if ($scheme != "ssl://") {
			$scheme = "";
		}
		$hosts = $scheme . $host . ':' . $port;
		if($job['proxy'] != 0){
			if(strpos($job['proxy'], "|")){
				list($ip, $user) = explode("|", $job['proxy']);
				$auth = base64_encode($user);
			}
			else $ip = $job['proxy'];
			$data = "GET {$link} HTTP/1.1\r\n";
			if(isset($auth)) $data.= "Proxy-Authorization: Basic $auth\r\n";
			$fp = @stream_socket_client("tcp://{$ip}", $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
		}
		else {
			$data = "GET {$path} HTTP/1.1\r\n";
			$fp = @stream_socket_client($hosts, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
		}
		if (!$fp) {
			sleep(15);
			header("HTTP/1.1 404 Not Found");
			die("HTTP/1.1 404 Not Found");
		}
		$data.= "User-Agent: " . $this->UserAgent . "\r\n";
		$data.= "Host: {$host}\r\n";
		$data.= "Accept: */*\r\n";
		$data.= $this->cookie ? "Cookie: " . $this->cookie . "\r\n" : '';
		if (!empty($range)) $data.= "Range: bytes={$range}\r\n";
		$data.= "Connection: Close\r\n\r\n";
		@stream_set_timeout($fp, 2);
		fputs($fp, $data);
		fflush($fp);
		$header = '';
		do {
			if (!$header) {
				$header.= stream_get_line($fp, $this->unit);
				if (!stristr($header, "HTTP/1")) break;
			}
			else $header.= stream_get_line($fp, $this->unit);
		}
		while (strpos($header, "\r\n\r\n") === false);
		/* debug */
		if ($this->isadmin() && isset($_GET['debug'])) {
			// Uncomment next line for enable to admins this debug code.
			// echo "<pre>connected to : $hosts ".($job['proxy'] == 0 ? '' : "via {$job['proxy']}")."\r\n$data\r\n\r\nServer replied: \r\n$header</pre>";
			die();
		}
		/* debug */
		// Must be fresh start
		if (headers_sent()) die('Headers Sent');
		// Required for some browsers
		if (ini_get('zlib.output_compression')) ini_set('zlib.output_compression', 'Off');
		header("Pragma: public"); // required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false); // required for certain browsers
		header("Content-Transfer-Encoding: binary");
		header("Accept-Ranges: bytes");
		if (stristr($header, "TTP/1.0 200 OK") || stristr($header, "TTP/1.1 200 OK")) {
			if (!is_numeric($filesize)) $filesize = trim($this->cut_str($header, "Content-Length:", "\n"));
			if (stristr($header, "filename")) {
				$filename = trim($this->cut_str($header, "filename", "\n"));
				$filename = preg_replace("/(\"\;\?\=|\"|=|\*|UTF-8|\')/", "", $filename);
				$filename = $this->download_prefix . $filename . $this->download_suffix;
			}
			if (is_numeric($filesize)) {
				header("HTTP/1.1 200 OK");
				header("Content-Type: application/force-download");
				header("Content-Disposition: attachment; filename=" . $filename);
				header("Content-Length: {$filesize}");
			}
			else {
				sleep(5);
				header("HTTP/1.1 404 Not Found");
				die("HTTP/1.1 404 Not Found");
			}
		}
		elseif (stristr($header, "TTP/1.1 206") || stristr($header, "TTP/1.0 206")) {
			sleep(2);
			header("HTTP/1.1 206 Partial Content");
			header("Content-Type: application/force-download");
			header("Content-Length: $new_length");
			header("Content-Range: bytes $range/{$filesize}");
		}
		else {
			sleep(10);
			header("HTTP/1.1 404 Not Found");
			die("HTTP/1.1 404 Not Found");
		}
		$tmp = explode("\r\n\r\n", $header);
		$max = count($tmp);
		for ($i = 1; $i < $max; $i++) {
			print $tmp[$i];
			if ($i != $max - 1) echo "\r\n\r\n";
		}
		while (!feof($fp) && (connection_status() == 0)) {
			$recv = @stream_get_line($fp, $this->unit);
			@print $recv;
			@flush();
			@ob_flush();
		}
		fclose($fp);
		exit;
	}

	function downloadmega($hash)
	{
		error_reporting(0);
		$job = $this->lookup_job($hash);
		if (!$job) {
			sleep(15);
			header("HTTP/1.1 404 Not Found");
			die($this->lang['errorget']);
		}
		if (($_SERVER['REMOTE_ADDR'] !== $job['ip']) && $this->privateip == true) {
			sleep(15);
			die($this->lang['errordl']);
		}
		if ($this->get_load() > $this->max_load) sleep(15);

		$megafile = new MEGA(urldecode($job['url']));
		$megafile->stream_download();
	}

	function CheckMBIP()
	{
		$this->countMBIP = 0;
		$this->totalMB = 0;
		$this->timebw = 0;
		$timedata = time();
		foreach($this->jobs as $job) {
			if ($job['ip'] == $_SERVER['REMOTE_ADDR']) {
				$this->countMBIP = $this->countMBIP + $job['size'] / 1024 / 1024;
				if ($job['mtime'] < $timedata) $timedata = $job['mtime'];
				$this->timebw = $this->ttl * 60 + $timedata - time();
			}

			if ($this->privatef == false) {
				$this->totalMB = $this->totalMB + $job['size'] / 1024 / 1024;
				$this->totalMB = round($this->totalMB);
			}
			else {
				if ($job['owner'] == $this->owner) {
					$this->totalMB = $this->totalMB + $job['size'] / 1024 / 1024;
					$this->totalMB = round($this->totalMB);
				}
			}
		}

		$this->countMBIP = round($this->countMBIP);
		if ($this->countMBIP >= $this->limitMBIP) return false;
		return true;
	}

	function curl($url, $cookies, $post, $header = 1, $json = 0, $ref = 0, $xml = 0)
	{
		$ch = @curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		if ($json == 1) {
			$head[] = "Content-type: application/json";
			$head[] = "X-Requested-With: XMLHttpRequest";
		}
		if ($xml == 1) {
			$head[] = "X-Requested-With: XMLHttpRequest";
		}
		$head[] = "Connection: keep-alive";
		$head[] = "Keep-Alive: 300";
		$head[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
		$head[] = "Accept-Language: en-us,en;q=0.5";
		if ($cookies) curl_setopt($ch, CURLOPT_COOKIE, $cookies);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->UserAgent);
		curl_setopt($ch, CURLOPT_REFERER, $ref == 0 ? $url : $ref);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $head);
		if($header == -1){
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_NOBODY, 1);
		}
		else curl_setopt($ch, CURLOPT_HEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if ($post) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		if ($this->proxy != false) {
			if(strpos($this->proxy, "|")) {
				list($ip, $auth) = explode("|", $this->proxy);
				curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, $auth);
			}
			else $ip = $this->proxy;
			curl_setopt($ch, CURLOPT_PROXYTYPE, 'HTTP');
			curl_setopt($ch, CURLOPT_PROXY, $ip);
		}
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
		$page = curl_exec($ch);
		curl_close($ch);
		return $page;
	}

	function cut_str($str, $left, $right)
	{
		$str = substr(stristr($str, $left) , strlen($left));
		$leftLen = strlen(stristr($str, $right));
		$leftLen = $leftLen ? -($leftLen) : strlen($str);
		$str = substr($str, 0, $leftLen);
		return $str;
	}

	function GetCookies($content)
	{
		preg_match_all('/Set-Cookie: (.*);/U',$content,$temp);
		$cookie = $temp[1];
		$cookies = "";
		$a = array();
		foreach($cookie as $c){
			$pos = strpos($c, "=");
			$key = substr($c, 0, $pos);
			$val = substr($c, $pos+1);
			$a[$key] = $val;
		}
		foreach($a as $b => $c){
			$cookies .= "{$b}={$c}; ";
		}
		return $cookies;
	}

	function GetAllCookies($page)
	{
		$lines = explode("\n", $page);
		$retCookie = "";
		foreach($lines as $val) {
			preg_match('/Set-Cookie: (.*)/', $val, $temp);
			if (isset($temp[1])) {
				if ($cook = substr($temp[1], 0, stripos($temp[1], ';'))) $retCookie.= $cook . ";";
			}
		}

		return $retCookie;
	}

	function mf_str_conv($str_or)
	{
		$str_or = stripslashes($str_or);
		if (!preg_match("/unescape\(\W([0-9a-f]+)\W\);\w+=([0-9]+);[^\^]+\)([0-9\^]+)?\)\);eval/", $str_or, $match)) return $str_or;
		$match[3] = $match[3] ? $match[3] : "";
		$str_re = "";
		for ($i = 0; $i < $match[2]; $i++) {
			$c = HexDec(substr($match[1], $i * 2, 2));
			eval("\$c = \$c" . $match[3] . ";");
			$str_re.= chr($c);
		}

		$str_re = str_replace($match[0], stripslashes($str_re) , $str_or);
		if (preg_match("/unescape\(\W([0-9a-f]+)\W\);\w+=([0-9]+);[^\^]+\)([0-9\^]+)?\)\);eval/", $str_re, $dummy)) $str_re = $this->mf_str_conv($str_re);
		return $str_re;
	}

	function main()
	{
		if ($this->get_load() > $this->max_load) {
			echo '<center><b><i><font color=red>' . $this->lang['svload'] . '</font></i></b></center>';
			return;
		}

		if (isset($_POST['urllist'])) {
			$url = $_POST['urllist'];
			$url = str_replace("\r", "", $url);
			$url = str_replace("\n", "", $url);
			$url = str_replace("<", "", $url);
			$url = str_replace(">", "", $url);
			$url = str_replace(" ", "", $url);
		}

		if (isset($url) && strlen($url) > 10) {
			if (substr($url, 0, 4) == 'www.') $url = "http://" . $url;
			if (!$this->check3x) {
				if (stristr($url, 'mega.co.nz')) $dlhtml = $this->mega($url);
				else $dlhtml = $this->get($url);
			}
			else {

				// ################## CHECK 3X #########################

				$check3x = false;
				if (strpos($url, "|not3x")) $url = str_replace("|not3x", "", $url);
				else {
					$data = strtolower($this->google($url));
					if(strlen($data) > 1){
						foreach($this->badword as $bad){
							if(stristr($data, " {$bad}") || stristr($data, "_{$bad}") || stristr($data, ".{$bad}") || stristr($data, "-{$bad}")){
								$check3x = $bad;
								break;
							}
						}
					}
				}

				if ($check3x == false) {
					if (stristr($url, 'mega.co.nz')) $dlhtml = $this->mega($url);
					else $dlhtml = $this->get($url);
				}
				else {
					$dlhtml = printf($this->lang['issex'], $url);
					unset($check3x);
				}
				// ################## CHECK 3X #########################

			}
		}
		else $dlhtml = "<b><a href=" . $url . " style='TEXT-DECORATION: none'><font color=red face=Arial size=2><s>" . $url . "</s></font></a> <img src=images/chk_error.png width='15' alt='errorlink'> <font color=#ffcc33><B>" . $this->lang['errorlink'] . "</B></font><br />";
		echo $dlhtml;
	}

	function google($q){
		$q = urldecode($q);
		$q = str_replace(' ', '+', $q);
		$oldagent = $this->UserAgent;
		$this->UserAgent = "Mozilla/5.0 (compatible; MSIE 9.0; Windows Phone OS 7.5; Trident/5.0; IEMobile/9.0; NOKIA; Lumia 800)";
		$data = $this->curl("http://www.google.com/search?q={$q}&hl=en", '', '', 0);
		$this->UserAgent = $oldagent;
		$parsing = $this->cut_str($data, '<ol>', '</ol>');
		$new = "<ol>{$parsing}</ol>";
		$new = str_replace('<ol><li class="g">', "", $new);
		$new = str_replace('</li><li class="g">', "\n\n\n", $new);
		$new = str_replace('</li></ol>', "", $new);
		$new = preg_replace ('%<a(.*?)href[^<>]+>|</a>%s', "", $new);
		$new = preg_replace ('%<b>|</b>%s', "", $new);
		$new = preg_replace ('%<h3 class="r">|</h3>%s', "", $new);
		$new = preg_replace ('%<div class="s"><div class="kv" style="margin-bottom:2px"><cite>[^<]+</cite></div><span class="st">%s', " ", $new);
		$new = str_replace(' ...', "", $new);
		$new = strip_tags($new);
		$new = str_replace('â€Ž', '', $new);
		$new = str_replace('', '', $new);
		$new = htmlspecialchars_decode($new);
		return $new;
	}

	function getsize($link, $cookie=""){
		$size_name = Tools_get::size_name($link, $cookie=="" ? $this->cookie : $cookie);
		return $size_name[0];
	}

	function getname($link, $cookie=""){
		$size_name = Tools_get::size_name($link, $cookie=="" ? $this->cookie : $cookie);
		return $size_name[1];
	}

	function get($url)
	{
		$this->reserved = array();
		$this->CheckMBIP();
		$dlhtml = '';
		if (count($this->jobs) >= $this->max_jobs) {
			$this->error1('manyjob');
		}
		if ($this->countMBIP >= $this->limitMBIP) {
			$this->error1('countMBIP', Tools_get::convertmb($this->limitMBIP * 1024 * 1024) , Tools_get::convert_time($this->ttl * 60) , Tools_get::convert_time($this->timebw));
		}
		/* check 1 */
		$checkjobs = $this->Checkjobs();
		$heute = $checkjobs[0];
		$lefttime = $checkjobs[1];
		if ($heute >= $this->limitPERIP) {
			$this->error1('limitPERIP', $this->limitPERIP, Tools_get::convert_time($this->ttl_ip * 60) , $lefttime);
		}
		/* /check 1 */
		if ($this->lookup_ip($_SERVER['REMOTE_ADDR']) >= $this->max_jobs_per_ip) {
			$this->error1('limitip');
		}

		$url = trim($url);

		if (empty($url)) return;
		$Original = $url;
		$link = '';
		$cookie = '';
		$report = false;

		if (!$link) {
			$site = $this->using;
			$this->proxy = isset($this->acc[$site]['proxy']) ? $this->acc[$site]['proxy'] : false;
			$this->proxy = isset($this->prox) ? $this->prox : false;
			if($this->get_account($site) != ""){
				require_once ('hosts/' . $this->list_host[$site]['file']);
				$download = new $this->list_host[$site]['class']($this, $this->list_host[$site]['site']);
				$link = $download->General($url);
			}
		}

		if (!$link) {
			$domain = str_replace("www.", "", $this->cut_str($Original, "://", "/"));
			if(strpos($domain, "1fichier.com")) $domain = "1fichier.com";
			if(strpos($domain, "letitbit.net"))   $domain = "letitbit.net";
			if(strpos($domain, "shareflare.net")) $domain = "shareflare.net";
			if(isset($this->list_host[$domain])){
				require_once ('hosts/' . $this->list_host[$domain]['file']);
				$download = new $this->list_host[$domain]['class']($this, $this->list_host[$domain]['site']);
				$site = $this->list_host[$domain]['site'];
				$this->proxy = isset($this->acc[$site]['proxy']) ? $this->acc[$site]['proxy'] : false;
				$this->proxy = isset($this->prox) ? $this->prox : false;
				$link = $download->General($url);
			}
		}

		if (!$link) {
			$this->proxy = isset($this->acc[$site]['proxy']) ? $this->acc[$site]['proxy'] : false;
			$this->proxy = isset($this->prox) ? $this->prox : false;
			$size_name = Tools_get::size_name($Original, "");
			$filesize = $size_name[0];
			$filename = $size_name[1];
			$this->max_size = $this->max_size_other_host;
			if ($size_name[0] > 1024 * 100) $link = $url;
			else $this->error2('notsupport', $Original);
		}
		else{
			$size_name = Tools_get::size_name($link, $this->cookie);
			$filesize = $size_name[0];
			$filename = isset($this->reserved['filename']) ? $this->reserved['filename'] : $size_name[1];
		}

		$hosting = Tools_get::site_hash($Original);
		if (!isset($filesize)) {
			$this->error2('notsupport', $Original);
		}
		$this->max_size = $this->acc[$site]['max_size'];
		if (!isset($this->max_size)) $this->max_size = $this->max_size_other_host;
		$msize = Tools_get::convertmb($filesize);
		$hash = md5($_SERVER['REMOTE_ADDR'] . $Original);
		if ($hash === false) {
			$this->error1('cantjob');
		}

		if ($filesize > $this->max_size * 1024 * 1024) {
			$this->error2('filebig', $Original, $msize, Tools_get::convertmb($this->max_size * 1024 * 1024));
		}

		if (($this->countMBIP + $filesize / (1024 * 1024)) >= $this->limitMBIP) {
			$this->error1('countMBIP', Tools_get::convertmb($this->limitMBIP * 1024 * 1024) , Tools_get::convert_time($this->ttl * 60) , Tools_get::convert_time($this->timebw));
		}

		/* check 2 */
		$checkjobs = $this->Checkjobs();
		$heute = $checkjobs[0];
		$lefttime = $checkjobs[1];
		if ($heute >= $this->limitPERIP) {
			$this->error1('limitPERIP', $this->limitPERIP, Tools_get::convert_time($this->ttl_ip * 60) , $lefttime);
		}
		/* /check 2 */
		$job = array(
			'hash' => substr(md5($hash) , 0, 10) ,
			'path' => substr(md5(rand()) , 0, 5) ,
			'filename' => urlencode($filename) ,
			'size' => $filesize,
			'msize' => $msize,
			'mtime' => time() ,
			'speed' => 0,
			'url' => urlencode($Original) ,
			'owner' => $this->owner,
			'ip' => $_SERVER['REMOTE_ADDR'],
			'type' => 'direct',
			'proxy' => $this->proxy == false ? 0 : $this->proxy,
			'directlink' => array(
				'url' => urlencode($link) ,
				'cookies' => $this->cookie,
			) ,
		);
		$this->jobs[$hash] = $job;
		$this->save_jobs();
		$tiam = time() . rand(0, 999);
		$gach = explode('/', $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
		$sv_name = "";
		for ($i = 0; $i < count($gach) - 1; $i++) $sv_name.= $gach[$i] . "/";
		if($this->acc[$site]['direct']) $linkdown = $link;
		elseif($this->longurl){
			if(function_exists("apache_get_modules") && in_array('mod_rewrite',@apache_get_modules())) $linkdown = 'http://'.$sv_name.$hosting.'/'.$job['hash'].'/'.urlencode($filename);
			else $linkdown = 'http://'.$sv_name.'index.php/'.$hosting.'/'.$job['hash'].'/'.urlencode($filename);
		}
		else $linkdown = 'http://'.$sv_name.'?file='.$job['hash'];
		// #########Begin short link ############  //    Short link by giaythuytinh176@rapidleech.com
		if (empty($this->zlink) == true && empty($link) == false && empty($this->Googlzip) == false && empty($this->bitly) == true) {
			$datalink = $this->Googlzip($linkdown);
			if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
			else $lik = $linkdown;
		}
		elseif (empty($this->zlink) == true && empty($link) == false && empty($this->Googlzip) == true && empty($this->bitly) == false) {
			$datalink = $this->bitly($linkdown);
			if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
			else $lik = $linkdown;
		}
		elseif (empty($this->zlink) == false && empty($link) == false) {
			if (empty($this->Googlzip) == true && empty($this->bitly) == true) {
				if (empty($this->link_zip) == false) {
					if (empty($this->link_rutgon) == true) {
						$datalink = $this->curl($this->link_zip . $linkdown, '', '', 0);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
					elseif (empty($this->link_rutgon) == false) {
						$apizip = $this->curl($this->link_zip . $linkdown, '', '', 0);
						$apizip2 = $this->curl($this->link_rutgon . $apizip, '', '', 0);
						if (preg_match('%(http:\/\/.++)%U', $apizip2, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
				}
				elseif (empty($this->link_zip) == true) {
					if (empty($this->link_rutgon) == true) {
						$lik = $linkdown;
					}
					elseif (empty($this->link_rutgon) == false) {
						$datalink = $this->curl($this->link_rutgon . $linkdown, '', '', 0);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
				}
			}
			elseif (empty($this->Googlzip) == false && empty($this->bitly) == true) {
				if (empty($this->link_zip) == false) {
					if (empty($this->link_rutgon) == true) {
						$apizip = $this->curl($this->link_zip . $linkdown, '', '', 0);
						$datalink = $this->Googlzip($apizip);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
					elseif (empty($this->link_rutgon) == false) {
						$apizip = $this->curl($this->link_zip . $linkdown, '', '', 0);
						$apizip2 = $this->curl($this->link_rutgon . $apizip, '', '', 0);
						$datalink = $this->Googlzip($apizip2);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
				}
				elseif (empty($this->link_zip) == true) {
					if (empty($this->link_rutgon) == true) {
						$datalink = $this->Googlzip($linkdown);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
					elseif (empty($this->link_rutgon) == false) {
						$apizip = $this->curl($this->link_rutgon . $linkdown, '', '', 0);
						$datalink = $this->Googlzip($apizip);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
				}
			}
			elseif (empty($this->Googlzip) == true && empty($this->bitly) == false) {
				if (empty($this->link_zip) == false) {
					if (empty($this->link_rutgon) == true) {
						$apizip = $this->curl($this->link_zip . $linkdown, '', '', 0);
						$datalink = $this->bitly($apizip);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
					elseif (empty($this->link_rutgon) == false) {
						$apizip = $this->curl($this->link_zip . $linkdown, '', '', 0);
						$apizip2 = $this->curl($this->link_rutgon . $apizip, '', '', 0);
						$datalink = $this->bitly($apizip2);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
				}
				elseif (empty($this->link_zip) == true) {
					if (empty($this->link_rutgon) == true) {
						$datalink = $this->bitly($linkdown);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
					elseif (empty($this->link_rutgon) == false) {
						$apizip = $this->curl($this->link_rutgon . $linkdown, '', '', 0);
						$datalink = $this->bitly($apizip);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
				}
			}
		}
		// ########### End short link  ##########
		else $lik = $linkdown;

		if($this->bbcode){
			if($this->proxy != false && $this->redirdl == true) {
				if(strpos($this->proxy, "|")){
					list($prox, $userpass) = explode("|", $this->proxy);
					list($ip, $port) = explode(":", $prox);
					list($user, $pass) = explode(":", $userpass);
				}
				else list($ip, $port) = explode(":", $this->proxy);
				echo "<input name='176' type='text' size='100' value='[center][b][URL={$lik}]{$this->title} | [color={$this->colorfn}]{$filename}[/color][color={$this->colorfs}] ({$msize})[/color]  [/b][/url][b] [br] ([color=green]You must add this proxy[/color] ".(strpos($this->proxy, "|") ? 'IP: '.$ip.' Port: '.$port.' User: '.$user.' & Pass: '.$pass.'' : 'IP: '.$ip.' Port: '.$port.'').")[/b][/center]' onClick='this.select()'>";
				echo "<br>";
			}
			else {
				echo "<input name='176' type='text' size='100' value='[center][b][URL={$lik}]{$this->title} | [color={$this->colorfn}]{$filename}[/color][color={$this->colorfs}] ({$msize}) [/color][/url][/b][/center]' onClick='this.select()'>";
				echo "<br>";
			}
		}
		$dlhtml = "<b><a title='click here to download' href='$lik' style='TEXT-DECORATION: none' target='$tiam'> <font color='#00CC00'>" . $filename . "</font> <font color='#FF66FF'>($msize)</font> ".($this->directdl && !$this->acc[$site]['direct'] ? "<a href='{$link}'>Direct<a> " : ""). "</a>" .($this->proxy != false ? "<font id='proxy'>({$this->proxy})</font>" : ""). "</b>".(($this->proxy != false && $this->redirdl == true) ? "<br/><b><font color=\"green\">You must add proxy or you can not download this link</font></b>" : "");
		return $dlhtml;
	}

	function mega($url)
	{
		$this->reserved = array();
		$this->CheckMBIP();
		$dlhtml = '';
		if (count($this->jobs) >= $this->max_jobs) {
			$this->error1('manyjob');
		}
		if ($this->countMBIP >= $this->limitMBIP) {
			$this->error1('countMBIP', Tools_get::convertmb($this->limitMBIP * 1024 * 1024) , Tools_get::convert_time($this->ttl * 60) , Tools_get::convert_time($this->timebw));
		}
		/* check 1 */
		$checkjobs = $this->Checkjobs();
		$heute = $checkjobs[0];
		$lefttime = $checkjobs[1];
		if ($heute >= $this->limitPERIP) {
			$this->error1('limitPERIP', $this->limitPERIP, Tools_get::convert_time($this->ttl_ip * 60) , $lefttime);
		}
		/* /check 1 */
		if ($this->lookup_ip($_SERVER['REMOTE_ADDR']) >= $this->max_jobs_per_ip) {
			$this->error1('limitip');
		}

		$url = trim($url);

		if (empty($url)) return;
		$Original = $url;
		$link = '';
		$cookie = '';
		$report = false;

		$megafile = new MEGA(urldecode($url));

		$info = $megafile->file_info();

		$link = 'https://mega.co.nz/';

		$filesize = $info['size'];
		$filename = isset($this->reserved['filename']) ? $this->reserved['filename'] : Tools_get::convert_name($info['attr']['n']);

		$hosting = Tools_get::site_hash($Original);
		if (!isset($filesize)) {
			$this->error2('notsupport', $Original);
		}
		$this->max_size = $this->acc[$site]['max_size'];
		if (!isset($this->max_size)) $this->max_size = $this->max_size_other_host;
		$msize = Tools_get::convertmb($filesize);
		$hash = md5($_SERVER['REMOTE_ADDR'] . $Original);
		if ($hash === false) {
			$this->error1('cantjob');
		}

		if ($filesize > $this->max_size * 1024 * 1024) {
			$this->error2('filebig', $Original, $msize, Tools_get::convertmb($this->max_size * 1024 * 1024));
		}

		if (($this->countMBIP + $filesize / (1024 * 1024)) >= $this->limitMBIP) {
			$this->error1('countMBIP', Tools_get::convertmb($this->limitMBIP * 1024 * 1024) , Tools_get::convert_time($this->ttl * 60) , Tools_get::convert_time($this->timebw));
		}

		/* check 2 */
		$checkjobs = $this->Checkjobs();
		$heute = $checkjobs[0];
		$lefttime = $checkjobs[1];
		if ($heute >= $this->limitPERIP) {
			$this->error1('limitPERIP', $this->limitPERIP, Tools_get::convert_time($this->ttl_ip * 60) , $lefttime);
		}
		/* /check 2 */
		$job = array(
			'hash' => "mega_".substr(md5($hash) , 0, 10) ,
			'path' => substr(md5(rand()) , 0, 5) ,
			'filename' => urlencode($filename) ,
			'size' => $filesize,
			'msize' => $msize,
			'mtime' => time() ,
			'speed' => 0,
			'url' => urlencode($Original) ,
			'owner' => $this->owner,
			'ip' => $_SERVER['REMOTE_ADDR'],
			'type' => 'direct',
			'proxy' => 0,
			'directlink' => array(
				'url' => urlencode($link) ,
				'cookies' => $this->cookie,
			) ,
		);
		$this->jobs[$hash] = $job;
		$this->save_jobs();
		$tiam = time() . rand(0, 999);
		$gach = explode('/', $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
		$sv_name = "";
		for ($i = 0; $i < count($gach) - 1; $i++) $sv_name.= $gach[$i] . "/";
		if($this->acc[$site]['direct']) $linkdown = $link;
		elseif($this->longurl){
			if(function_exists("apache_get_modules") && in_array('mod_rewrite',@apache_get_modules())) $linkdown = 'http://'.$sv_name.$hosting.'/'.$job['hash'].'/'.urlencode($filename);
			else $linkdown = 'http://'.$sv_name.'index.php/'.$hosting.'/'.$job['hash'].'/'.urlencode($filename);
		}
		else $linkdown = 'http://'.$sv_name.'?file='.$job['hash'];
		// #########Begin short link ############  //    Short link by giaythuytinh176@rapidleech.com
		if (empty($this->zlink) == true && empty($link) == false && empty($this->Googlzip) == false && empty($this->bitly) == true) {
			$datalink = $this->Googlzip($linkdown);
			if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
			else $lik = $linkdown;
		}
		elseif (empty($this->zlink) == true && empty($link) == false && empty($this->Googlzip) == true && empty($this->bitly) == false) {
			$datalink = $this->bitly($linkdown);
			if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
			else $lik = $linkdown;
		}
		elseif (empty($this->zlink) == false && empty($link) == false) {
			if (empty($this->Googlzip) == true && empty($this->bitly) == true) {
				if (empty($this->link_zip) == false) {
					if (empty($this->link_rutgon) == true) {
						$datalink = $this->curl($this->link_zip . $linkdown, '', '', 0);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
					elseif (empty($this->link_rutgon) == false) {
						$apizip = $this->curl($this->link_zip . $linkdown, '', '', 0);
						$apizip2 = $this->curl($this->link_rutgon . $apizip, '', '', 0);
						if (preg_match('%(http:\/\/.++)%U', $apizip2, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
				}
				elseif (empty($this->link_zip) == true) {
					if (empty($this->link_rutgon) == true) {
						$lik = $linkdown;
					}
					elseif (empty($this->link_rutgon) == false) {
						$datalink = $this->curl($this->link_rutgon . $linkdown, '', '', 0);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
				}
			}
			elseif (empty($this->Googlzip) == false && empty($this->bitly) == true) {
				if (empty($this->link_zip) == false) {
					if (empty($this->link_rutgon) == true) {
						$apizip = $this->curl($this->link_zip . $linkdown, '', '', 0);
						$datalink = $this->Googlzip($apizip);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
					elseif (empty($this->link_rutgon) == false) {
						$apizip = $this->curl($this->link_zip . $linkdown, '', '', 0);
						$apizip2 = $this->curl($this->link_rutgon . $apizip, '', '', 0);
						$datalink = $this->Googlzip($apizip2);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
				}
				elseif (empty($this->link_zip) == true) {
					if (empty($this->link_rutgon) == true) {
						$datalink = $this->Googlzip($linkdown);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
					elseif (empty($this->link_rutgon) == false) {
						$apizip = $this->curl($this->link_rutgon . $linkdown, '', '', 0);
						$datalink = $this->Googlzip($apizip);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
				}
			}
			elseif (empty($this->Googlzip) == true && empty($this->bitly) == false) {
				if (empty($this->link_zip) == false) {
					if (empty($this->link_rutgon) == true) {
						$apizip = $this->curl($this->link_zip . $linkdown, '', '', 0);
						$datalink = $this->bitly($apizip);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
					elseif (empty($this->link_rutgon) == false) {
						$apizip = $this->curl($this->link_zip . $linkdown, '', '', 0);
						$apizip2 = $this->curl($this->link_rutgon . $apizip, '', '', 0);
						$datalink = $this->bitly($apizip2);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
				}
				elseif (empty($this->link_zip) == true) {
					if (empty($this->link_rutgon) == true) {
						$datalink = $this->bitly($linkdown);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
					elseif (empty($this->link_rutgon) == false) {
						$apizip = $this->curl($this->link_rutgon . $linkdown, '', '', 0);
						$datalink = $this->bitly($apizip);
						if (preg_match('%(http:\/\/.++)%U', $datalink, $shortlink)) $lik = trim($shortlink[1]);
						else $lik = $linkdown;
					}
				}
			}
		}
		// ########### End short link  ##########
		else $lik = $linkdown;

		if($this->bbcode){
			echo "<input name='176' type='text' size='100' value='[center][b][URL={$lik}]{$this->title} | [color={$this->colorfn}]{$filename}[/color][color={$this->colorfs}] ({$msize}) [/color][/url][/b][/center]' onClick='this.select()'>";
			echo "<br>";
		}
		$dlhtml = "<b><a title='click here to download' href='$lik' style='TEXT-DECORATION: none' target='$tiam'> <font color='#00CC00'>" . $filename . "</font> <font color='#FF66FF'>($msize)</font> ";
		return $dlhtml;
	}

	function datecmp($a, $b)
	{
		return ($a[1] < $b[1]) ? 1 : 0;
	}

	function fulllist()
	{
		$act = "";
		if ($this->act['delete'] == true) {
			$act.= '<option value="del">' . $this->lang['del'] . '</option>';
		}

		if ($this->act['rename'] == true) {
			$act.= '<option value="ren">' . $this->lang['rname'] . '</option>';
		}

		if ($act != "") {
			if ((isset($_POST['checkbox'][0]) && $_POST['checkbox'][0] != null) || isset($_POST['renn']) || isset($_POST['remove'])) {
				echo '<table style="width: 500px; border-collapse: collapse" border="1" align="center"><tr><td><center>';
				switch ($_POST['option']) {
				case 'del':
					$this->deljob();
					break;

				case 'ren':
					$this->renamejob();
					break;
				}

				if (isset($_POST['renn'])) $this->renamejob();
				if (isset($_POST['remove'])) $this->deljob();
				echo "</center></td></tr></table><br/>";
			}
		}
		else echo '</select>';
		$files = array();
		foreach($this->jobs as $job) {
			if ($job['owner'] != $this->owner && $this->privatef == true) continue;
			$files[] = array(
				urldecode($job['url']) ,
				$job['mtime'],
				$job['hash'],
				urldecode($job['filename']) ,
				$job['size'],
				$job['ip'],
				$job['msize'],
				urldecode($job['directlink']['url']) ,
				$job['proxy']
			);
		}

		if (count($files) == 0) {
			echo "<Center>" . $this->lang['notfile'] . "<br/><a href='$this->self'> [" . $this->lang['main'] . "] </a></center>";
			return;
		}

		echo "<script type=\"text/javascript\">function setCheckboxes(act){elts = document.getElementsByName(\"checkbox[]\");var elts_cnt  = (typeof(elts.length) != 'undefined') ? elts.length : 0;if (elts_cnt){ for (var i = 0; i < elts_cnt; i++){elts[i].checked = (act == 1 || act == 0) ? act : (elts[i].checked ? 0 : 1);} }}</script>";
		echo "<center><a href=javascript:setCheckboxes(1)> {$this->lang['checkall']} </a> | <a href=javascript:setCheckboxes(0)> {$this->lang['uncheckall']} </a> | <a href=javascript:setCheckboxes(2)> {$this->lang['invert']} </a></center><br/>";
		echo "<center><form action='$this->self' method='post' name='flist'><select onchange='javascript:void(document.flist.submit());'name='option'>";
		if ($act == "") echo "<option value=\"dis\"> " . $this->lang['acdis'] . " </option>";
		else echo '<option selected="selected">' . $this->lang['ac'] . '</option>' . $act;
		echo '</select>';
		echo '<div style="overflow: auto; height: auto; max-height: 450px; width: 800px;"><table id="table_filelist" class="filelist" align="left" cellpadding="3" cellspacing="1" width="100%"><thead><tr class="flisttblhdr" valign="bottom"><td id="file_list_checkbox_title" class="sorttable_checkbox">&nbsp;</td><td class="sorttable_alpha"><b>' . $this->lang['name'] . '</b></td>'.($this->directdl ? '<td><b>'.$this->lang['direct'].'</b></td>' : '').'<td><b>' . $this->lang['original'] . '</b></td><td><b>' . $this->lang['size'] . '</b></td><td><b>' . $this->lang['date'] . '</b></td><td><b>IP</b></td></tr></thead><tbody>
    ';
		usort($files, array(
			$this,
			'datecmp'
		));
		$data = "";
		foreach($files as $file) {
			$timeago = Tools_get::convert_time(time() - $file[1]) . " " . $this->lang['ago'];
			if (strlen($file[3]) > 80) $file[3] = substr($file[3], 0, 70);
			$hosting = substr(Tools_get::site_hash($file[0]) , 0, 15);
			if($this->longurl){
				if(function_exists("apache_get_modules") && in_array('mod_rewrite',@apache_get_modules())) $linkdown = Tools_get::site_hash($file[0])."/$file[2]/$file[3]";
				else $linkdown = 'index.php/'.Tools_get::site_hash($file[0])."/$file[2]/$file[3]";
			}
			else $linkdown = '?file='.$file[2];
			$data.= "
      <tr class='flistmouseoff' align='center'>
        <td><input name='checkbox[]' value='$file[2]+++$file[3]' type='checkbox'></td>
        ".($this->showlinkdown ? "<td><a href='$linkdown' style='font-weight: bold; color: rgb(0, 0, 0);'>$file[3]" . ($file[8] != 0 ? "<br/>({$file[8]})" : "") . "</a></td>" : "<td>$file[3]</td>" )."
        ".($this->directdl ? "<td><a href='$file[7]' style='color: rgb(0, 0, 0);'>" . $hosting . "</a></td>" : "")."
        <td><a href='$file[0]' style='color: rgb(0, 0, 0);'>" . $hosting . "</a></td>
        <td>" . $file[6] . "</td>
        <td><a href=http://www.google.com/search?q=$file[0] title='" . $this->lang['clickcheck'] . "' target='$file[1]'><font color=#000000>$timeago</font></a></center></td><td title='IP has generated link'>".$file[5]."</td>
      </tr>";
		}

		$this->CheckMBIP();
		echo $data;
		$totalall = Tools_get::convertmb($this->totalMB * 1024 * 1024);
		$MB1IP = Tools_get::convertmb($this->countMBIP * 1024 * 1024);
		$thislimitMBIP = Tools_get::convertmb($this->limitMBIP * 1024 * 1024);
		$timereset = Tools_get::convert_time($this->ttl * 60);
		if($this->config['showdirect'] == true)
		echo "</tbody><tbody><tr class='flisttblftr'><td>&nbsp;</td><td>" . $this->lang['total'] . ":</td><td></td><td></td><td>$totalall</td><td></td><td>&nbsp;</td></tr></tbody></table>
				</div></form><center><b>" . $this->lang['used'] . " $MB1IP/$thislimitMBIP - " . $this->lang['reset'] . " $timereset</b>.</center><br/>";

		else echo "</tbody><tbody><tr class='flisttblftr'><td>&nbsp;</td><td>" . $this->lang['total'] . ":</td><td></td><td>$totalall</td><td></td><td>&nbsp;</td></tr></tbody></table>
				</div></form><center><b>" . $this->lang['used'] . " $MB1IP/$thislimitMBIP - " . $this->lang['reset'] . " $timereset</b>.</center><br/>";
	}

	function deljob()
	{
		if ($this->act['delete'] == false) return;
		if (isset($_POST['checkbox'])) {
			echo "<form action='$this->self' method='post'>";
			for ($i = 0; $i < count($_POST['checkbox']); $i++) {
				$temp = explode("+++", $_POST['checkbox'][$i]);
				$ftd = $temp[0];
				$name = $temp[1];
				echo "<br/><b> $name </b>";
				echo '<input type="hidden" name="ftd[]" value="' . $ftd . '" />';
				echo '<input type="hidden" name="name[]" value="' . $name . '" />';
			}

			echo "<br/><br/><input type='submit' value='" . $this->lang['del'] . "' name='remove'/> &nbsp; <input type='submit' value='" . $this->lang['canl'] . "' name='Cancel'/><br /><br />";
		}

		if (isset($_POST['remove'])) {
			echo "<br />";
			for ($i = 0; $i < count($_POST['ftd']); $i++) {
				$ftd = $_POST['ftd'][$i];
				$name = $_POST['name'][$i];
				$key = "";
				foreach($this->jobs as $url => $job) {
					if ($job['hash'] == $ftd) {
						$key = $url;
						break;
					}
				}

				if ($key) {
					unset($this->jobs[$key]);
					echo "<center>File: <b>$name</b> " . $this->lang['deld'];
				}
				else echo "<center>File: <b>$name</b> " . $this->lang['notfound'];
				echo "</center>";
			}

			echo "<br />";
			$this->save_jobs();
		}

		if (isset($_POST['Cancel'])) {
			$this->fulllist();
		}
	}

	function renamejob()
	{
		if ($this->act['rename'] == false) return;
		if (isset($_POST['checkbox'])) {
			echo "<form action='$this->self' method='post'>";
			for ($i = 0; $i < count($_POST['checkbox']); $i++) {
				$temp = explode("+++", $_POST['checkbox'][$i]);
				$name = $temp[1];
				echo "<br/><b> $name </b>";
				echo '<input type="hidden" name="hash[]" value="' . $temp[0] . '" />';
				echo '<input type="hidden" name="name[]" value="' . $name . '" />';
				echo '<br/>' . $this->lang['nname'] . ': <input type="text" name="nname[]" value="' . $name . '"/ size="70"><br />';
			}

			echo "<br/><input type='submit' value='" . $this->lang['rname'] . "' name='renn'/> &nbsp; <input type='submit' value='" . $this->lang['canl'] . "' name='Cancel'/><br /><br />";
		}

		if (isset($_POST['renn'])) {
			for ($i = 0; $i < count($_POST['name']); $i++) {
				$orname = $_POST['name'][$i];
				$hash = $_POST['hash'][$i];
				$nname = $_POST['nname'][$i];
				$nname = Tools_get::convert_name($nname);
				$nname = str_replace($this->banned, '', $nname);
				if ($nname == "") {
					echo "<br />" . $this->lang['bname'] . "<br /><br />";
					return;
				}
				else {
					echo "<br/>";
					$key = "";
					foreach($this->jobs as $url => $job) {
						if ($job['hash'] == $hash) {
							$key = $url;

							// $hash = $this->create_hash($key,$nname);

							$jobn = array(
								'hash' => $job['hash'],
								'path' => $job['path'],
								'filename' => urlencode($nname) ,
								'size' => $job['size'],
								'msize' => $job['msize'],
								'mtime' => $job['mtime'],
								'speed' => 0,
								'url' => $job['url'],
								'owner' => $job['owner'],
								'ip' => $job['ip'],
								'type' => 'direct',
								'directlink' => array(
									'url' => $job['directlink']['url'],
									'cookies' => $job['directlink']['cookies'],
								) ,
							);
						}
					}

					if ($key) {
						$this->jobs[$key] = $jobn;
						$this->save_jobs();
						echo "File <b>$orname</b> " . $this->lang['rnameto'] . " <b>$nname</b>";
					}
					else echo "File <b>$orname</b> " . $this->lang['notfound'];
					echo "<br/><br />";
				}
			}
		}

		if (isset($_POST['Cancel'])) {
			$this->fulllist();
		}
	}
	function error1($msg, $a = "", $b = "", $c = "", $d = ""){
		if(isset($this->lang[$msg])) $msg = sprintf($this->lang[$msg], $a, $b, $c, $d);
		$msg = sprintf($this->lang["error1"], $msg);
		die($msg);
	}
	function error2($msg, $a = "", $b = "", $c = "", $d = ""){
		if(isset($this->lang[$msg])) $msg = sprintf($this->lang[$msg], $b, $c, $d);
		$msg = sprintf($this->lang["error2"], $msg, $a);
		die($msg);
	}
	function Googlzip($longUrl)
	{
		$GoogleApiKey = $this->googlapikey;   //Get API key from : https://code.google.com/apis/console/
		$postData = array(
			'longUrl' => $longUrl,
			'key' => $GoogleApiKey,
		);
		$curlObj = curl_init();
		curl_setopt($curlObj, CURLOPT_URL, "https://www.googleapis.com/urlshortener/v1/url?key={$GoogleApiKey}");
		curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curlObj, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curlObj, CURLOPT_HEADER, 0);
		curl_setopt($curlObj, CURLOPT_HTTPHEADER, array('Content-type:application/json'));
		curl_setopt($curlObj, CURLOPT_POST, 1);
		curl_setopt($curlObj, CURLOPT_POSTFIELDS, json_encode($postData));
		$response = curl_exec($curlObj);
		$json = json_decode($response, true);
		curl_close($curlObj);
		return $json['id'];
	}
	function bitly($url, $format='txt')
	{
		$login = $this->BitLylogin;
		$apikey = $this->BitLyApi;
		$data = $this->curl("http://api.bit.ly/v3/shorten?login={$login}&apiKey={$apikey}&uri=".urlencode($url)."&format={$format}", "", "");
		return $data;
	}
								// Credit to France10s
	function wrong_proxy($proxy)
	{
		if(strpos($proxy, "|")){
			list($prox, $userpass) = explode("|", $proxy);
			list($ip, $port) = explode(":", $prox);
			list($user, $pass) = explode(":", $userpass);
		}
		else list($ip, $port) = explode(":", $proxy);
		die('<title>You must add this proxy to IDM '.(strpos($proxy, "|") ? 'IP: '.$ip.' Port: '.$port.' User: '.$user.' & Pass: '.$pass.'' : 'IP: '.$ip.' Port: '.$port.'').'</title><center><b><span style="color:#076c4e">You must add this proxy to IDM </span> <span style="color:#30067d">('.(strpos($proxy, "|") ? 'IP: '.$ip.' Port: '.$port.' User: '.$user.' and Pass: '.$pass.'' : 'IP: '.$ip.' Port: '.$port.'').')</span> <br><span style="color:red">PLEASE REMEMBER: IF YOU DO NOT ADD THE PROXY, YOU CAN NOT DOWNLOAD THIS LINK!</span><br><br>  Open IDM > Downloads > Options.<br><img src="http://i.imgur.com/v7FR3HE.png"><br><br>  Proxy/Socks > Choose "Use Proxy" > Add proxy server: <font color=\'red\'>'.$ip.'</font>, port: <font color=\'red\'>'.$port.'</font> '.(strpos($proxy, "|") ? ', username: <font color=\'red\'>'.$user.'</font> and password: <font color=\'red\'>'.$pass.'</font>' : '').' > Choose http > OK.<br>'.(strpos($proxy, "|") ? '<img src="http://i.imgur.com/LUTpGyN.png">' : '<img src="http://i.imgur.com/zExhNVR.png">').'<br><br>  Copy your link > Paste in IDM > OK.<br><img src="http://i.imgur.com/S355c5J.png"><br><br>  It will work > Start Download > Enjoy!<br><img src="http://i.imgur.com/vlh2vZf.png"></b></center>');
	}
}
