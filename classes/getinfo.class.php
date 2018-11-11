<?php
class getinfo
{
	function config()
	{
		$this->self = 'http://' . $_SERVER['HTTP_HOST'] . preg_replace('/\?.*$/', '', isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF']);
		$this->Deny = true;
		$this->admin = false;
		$this->fileinfo_dir = "data";
		$this->filecookie = "/cookie.dat";
		$this->fileconfig = "/config.dat";
		$this->fileaccount = "/account.dat";
		$this->fileinfo_ext = "vng";
		$this->banned = explode(' ', '.htaccess .htpasswd .php .php3 .php4 .php5 .phtml .asp .aspx .cgi .pl');
		$this->unit = 512;
		$this->UserAgent = 'Mozilla/5.0 (Windows NT 5.1; rv:12.0) Gecko/20100101 Firefox/27.0.1';
		$this->config = $this->load_json($this->fileconfig);
		include ("config.php");
		if(count($this->config) == 0) {
			$this->config = $config;
			$_GET['id'] = 'admin';
			$this->Deny = false;
			$this->admin = true;
		}
		else{
			foreach($config as $key=>$val){
				if (!isset($this->config[$key])) $this->config[$key] = $val;
			}
			if ($this->config['secure'] == false) $this->Deny = false;
			$password = explode(", ", $this->config['password']);
			$password[] = $this->config['admin'];
			foreach($password as $login_vng) if (isset($_COOKIE["secureid"]) && $_COOKIE["secureid"] == md5($login_vng)) {
				$this->Deny = false;
				break;
			}
		}
		$this->set_config();
		if (!file_exists($this->fileinfo_dir)) {
			mkdir($this->fileinfo_dir) or die("<CENTER><font color=red size=4>Could not create folder! Try to chmod the folder \"<B>$this->fileinfo_dir</B>\" to 777</font></CENTER>");
			@chmod($this->fileinfo_dir, 0777);
		}
		if (!file_exists($this->fileinfo_dir . "/files")) {
			mkdir($this->fileinfo_dir . "/files") or die("<CENTER><font color=red size=4>Could not create folder! Try to chmod the folder \"<B>$this->fileinfo_dir/files</B>\" to 777</font></CENTER>");
			@chmod($this->fileinfo_dir . "/files", 0777);
		}
		if (!file_exists($this->fileinfo_dir . "/index.php")) {
			$clog = fopen($this->fileinfo_dir . "/index.php", "a") or die("<CENTER><font color=red size=4>Could not create folder! Try to chmod the folder \"<B>$this->fileinfo_dir</B>\" to 777</font></CENTER>");
			fwrite($clog, '<meta HTTP-EQUIV="Refresh" CONTENT="0; URL=http://' . $homepage . '">');
			fclose($clog);
			@chmod($this->fileinfo_dir . "/index.php", 0666);
		}
		if (!file_exists($this->fileinfo_dir . "/files/index.php")) {
			$clog = fopen($this->fileinfo_dir . "/files/index.php", "a") or die("<CENTER><font color=red size=4>Could not create folder! Try to chmod the folder \"<B>$this->fileinfo_dir/files</B>\" to 777</font></CENTER>");
			fwrite($clog, '<meta HTTP-EQUIV="Refresh" CONTENT="0; URL=http://' . $homepage . '">');
			fclose($clog);
			@chmod($this->fileinfo_dir . "/files/index.php", 0666);
		}
	}
	function set_config(){
		include("lang/{$this->config['language']}.php");
		$this->lang = $lang;
		$this->Secure = $this->config['secure'];
		$this->skin = $this->config['skin'];
		$this->download_prefix = $this->config['download_prefix'];
		$this->download_suffix = $this->config['download_suffix'];
		$this->limitMBIP = $this->config['limitMBIP'];
		$this->ttl = $this->config['ttl'];
		$this->limitPERIP = $this->config['limitPERIP'];
		$this->ttl_ip = $this->config['ttl_ip'];
		$this->max_jobs_per_ip = $this->config['max_jobs_per_ip'];
		$this->max_jobs = $this->config['max_jobs'];
		$this->max_load = $this->config['max_load'];
		$this->max_size_default = $this->config['max_size_default'];
		$this->file_size_limit = $this->config['file_size_limit'];
		$this->zlink = $this->config['ziplink'];
		$this->link_zip = $this->config['apiadf'];
		$this->link_rutgon = $this->config['apirutgon'];
		$this->Googlzip = $this->config['Googlzip'];
		$this->googlapikey = $this->config['googleapikey'];
		$this->bitly = $this->config['bitly'];
		$this->BitLylogin = $this->config['BitLylogin'];
		$this->BitLyApi = $this->config['BitLyApi'];
		$this->badword = explode(", ", $this->config['badword']);
		$this->act = array('rename' => $this->config['rename'], 'delete' => $this->config['delete']);
		$this->listfile = $this->config['listfile'];
		$this->showlinkdown = $this->config['showlinkdown'];
		$this->checkacc = $this->config['checkacc'];
		$this->privatef = $this->config['privatefile'];
		$this->privateip = $this->config['privateip'];
		$this->redirdl = $this->config['redirectdl'];
		$this->check3x = $this->config['checklinksex'];
		$this->colorfn = $this->config['colorfilename'];
		$this->colorfs = $this->config['colorfilesize'];
		$this->title = $this->config['title'];
		$this->directdl = $this->config['showdirect'];
		$this->longurl = $this->config['longurl'];
		$this->display_error = $this->config['display_error'];
		$this->proxy = false;
		$this->prox = $_POST['proxy'];
		$this->bbcode = $this->config['bbcode'];
	}
	function isadmin(){
		return (isset($_COOKIE['secureid']) && $_COOKIE['secureid'] == md5($this->config['admin']) ? true : $this->admin);
	}
	function getversion(){
		$version = $this->cut_str($this->curl("https://github.com/giaythuytinh176/vinaget-script", "", ""), '<span class="num text-emphasized">','</span>');
		return intval($version);
	}
	function notice($id="notice")
	{
		if($id=="notice") return sprintf($this->lang['notice'], Tools_get::convert_time($this->ttl * 60) , $this->limitPERIP, Tools_get::convert_time($this->ttl_ip * 60));
		else {
			$this->CheckMBIP();
			$MB1IP = Tools_get::convertmb($this->countMBIP * 1024 * 1024);
			$thislimitMBIP = Tools_get::convertmb($this->limitMBIP * 1024 * 1024);
			$maxsize = Tools_get::convertmb($this->max_size_other_host * 1024 * 1024);
			if($id=="yourip") return $this->lang['yourip'];
			if($id=="yourjob") return $this->lang['yourjob'];
			if($id=="userjobs") return' '.$this->lookup_ip($_SERVER['REMOTE_ADDR']).' (max '.$this->max_jobs_per_ip.') ';
			if($id=="youused") return sprintf($this->lang['youused']);
			if($id=="used") return' '.$MB1IP.' (max '.$thislimitMBIP.') ';
			if($id=="sizelimit") return $this->lang['sizelimit'];
			if($id=="maxsize") return $maxsize;
			if($id=="totjob") return $this->lang['totjob'];
			if($id=="totjobs") return' '.count($this->jobs).' (max '.$this->max_jobs.') ';
			if($id=="serverload") return $this->lang['serverload'];
			if($id=="maxload") return' '.$this->get_load().' (max '.$this->max_load.') ';
			if($id=="uonline") return $this->lang['uonline'];
			if($id=="useronline") return Tools_get::useronline();
		}
	}
	function load_jobs()
	{
		if (isset($this->jobs)) return;
		$dir = opendir($this->fileinfo_dir . "/files/");
		$this->lists = array();
		while ($file = readdir($dir)) {
			if (substr($file, -strlen($this->fileinfo_ext) - 1) == "." . $this->fileinfo_ext) {
				$this->lists[] = $this->fileinfo_dir . "/files/" . $file;
			}
		}
		closedir($dir);
		$this->jobs = array();
		if (count($this->lists)) {
			sort($this->lists);
			foreach($this->lists as $file) {
				$contentsfile = @file_get_contents($file);
				$jobs_data = @json_decode($contentsfile, true);
				if (is_array($jobs_data)) {
					$this->jobs = array_merge($this->jobs, $jobs_data);
				}
			}
		}
	}
	function save_jobs()
	{
		if (!isset($this->jobs) || is_array($this->jobs) == false) return;
		// ## clean jobs ###
		$oldest = time() - $this->ttl * 60;
		$delete = array();
		foreach($this->jobs as $key => $job) {
			if ($job['mtime'] < $oldest) {
				$delete[] = $key;
			}
		}
		foreach($delete as $key) {
			unset($this->jobs[$key]);
		}
		// ## clean jobs ###
		$namedata = $timeload = explode(" ", microtime());
		$namedata = $namedata[1] * 1000 + round($namedata[0] * 1000);
		$this->fileinfo = $this->fileinfo_dir . "/files/" . $namedata . "." . $this->fileinfo_ext;
		$tmp = @json_encode($this->jobs);
		$fh = fopen($this->fileinfo, 'w') or die('<CENTER><font color=red size=3>Could not open file ! Try to chmod the folder "<B>' . $this->fileinfo_dir . "/files/" . '</B>" to 777</font></CENTER>');
		fwrite($fh, $tmp);
		fclose($fh);
		@chmod($this->fileinfo, 0666);
		if (count($this->lists)) foreach($this->lists as $file) if (file_exists($file)) @unlink($file);
		return true;
	}
	function load_json($file)
	{
		$hash = substr($file, 1);
		$this->json[$hash] = @file_get_contents($this->fileinfo_dir . $file);
		$data = @json_decode($this->json[$hash], true);
		if (!is_array($data)) {
			$data = array();
			$this->json[$hash] = 'default';
		}
		return $data;
	}
	function save_json($file, $data)
	{
		$tmp = json_encode($data);
		$hash = substr($file, 1);
		if ($tmp !== $this->json[$hash]) {
			$this->json[$hash] = $tmp;
			$fh = fopen($this->fileinfo_dir . $file, 'w') or die('<CENTER><font color=red size=3>Could not open file ! Try to chmod the folder "<B>' . $this->fileinfo_dir . '</B>" to 777</font></CENTER>');
			fwrite($fh, $this->json[$hash]) or die('<CENTER><font color=red size=3>Could not write file ! Try to chmod the folder "<B>' . $this->fileinfo_dir . '</B>" to 777</font></CENTER>');
			fclose($fh);
			@chmod($this->fileinfo_dir . $file, 0666);
			return true;
		}
	}
	function load_cookies()
	{
		if (isset($this->cookies)) return;
		$this->cookies = $this->load_json($this->filecookie);
	}
	function get_cookie($site)
	{
		$cookie = "";
		if (isset($this->cookies) && count($this->cookies) > 0) {
			foreach($this->cookies as $ckey => $cookies) {
				if ($ckey === $site) {
					$cookie = $cookies['cookie'];
					break;
				}
			}
		}
		return $cookie;
	}
	function save_cookies($site, $cookie)
	{
		if (!isset($this->cookies)) return;
		if ($site) {
			$cookies = array(
				'cookie' => $cookie,
				'time' => time() ,
			);
			$this->cookies[$site] = $cookies;
		}
		$this->save_json($this->filecookie, $this->cookies);
	}
	function load_account(){
		if (isset($this->acc)) return;
		$this->acc = $this->load_json($this->fileaccount);
		foreach($this->list_host as $site => $host) {
			if(!$host['alias']){
				if(empty($this->acc[$site]['proxy'])) $this->acc[$site]['proxy'] = "";
				if(empty($this->acc[$site]['direct'])) $this->acc[$site]['direct'] = false;
				if(empty($this->acc[$site]['max_size'])) $this->acc[$site]['max_size'] = $this->max_size_default;
				if(empty($this->acc[$site]['accounts'])) $this->acc[$site]['accounts'] = array();
			}
		}
	}
	function save_account($service, $acc){
		foreach ($this->acc[$service]['accounts'] as $value) if ($acc == $value) return false;
		if(empty($this->acc[$service])) $this->acc[$service]['max_size'] = $this->max_size_default;
		$this->acc[$_POST['type']]['accounts'][] = $_POST['account'];
		$this->save_json($this->fileaccount, $this->acc);
	}
	function get_account($service)
	{
		$acc = '';
		if (isset($this->acc[$service])) {
			$service = $this->acc[$service];
			$this->max_size = $service['max_size'];
			if (count($service['accounts']) > 0) $acc = $service['accounts'][rand(0, count($service['accounts']) - 1) ];
		}
		return $acc;
	}
	function lookup_job($hash)
	{
		$this->load_jobs();
		foreach($this->jobs as $key => $job) {
			if ($job['hash'] === $hash) return $job;
		}
		return false;
	}
	function get_load($i = 0)
	{
		$load = array(
			'0',
			'0',
			'0'
		);
		if (@file_exists('/proc/loadavg')) {
			if ($fh = @fopen('/proc/loadavg', 'r')) {
				$data = @fread($fh, 15);
				@fclose($fh);
				$load = explode(' ', $data);
			}
		}
		else {
			if ($serverstats = @exec('uptime')) {
				if (preg_match('/(?:averages)?\: ([0-9\.]+),?[\s]+([0-9\.]+),?[\s]+([0-9\.]+)/', $serverstats, $matches)) {
					$load = array(
						$matches[1],
						$matches[2],
						$matches[3]
					);
				}
			}
		}
		return $i == - 1 ? $load : $load[$i];
	}
	function lookup_ip($ip)
	{
		$this->load_jobs();
		$cnt = 0;
		foreach($this->jobs as $job) {
			if ($job['ip'] === $ip) $cnt++;
		}
		return $cnt;
	}
	function Checkjobs()
	{
		$ip = $_SERVER['REMOTE_ADDR'];
		$heute = 0;
		$lasttime = time();
		$altr = $lasttime - $this->ttl_ip * 60;
		foreach($this->jobs as $job) {
			if ($job['ip'] === $ip && $job['mtime'] > $altr) {
				$heute++;
				if ($job['mtime'] < $lasttime) $lasttime = $job['mtime'];
			}
		}
		$lefttime = $this->ttl_ip * 60 - time() + $lasttime;
		$lefttime = Tools_get::convert_time($lefttime);
		return array(
			$heute,
			$lefttime
		);
	}
}
