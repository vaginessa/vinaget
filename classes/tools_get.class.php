<?php

class Tools_get
{
    public function useronline()
    {
        $data = @file_get_contents($this->fileinfo_dir . "/online.dat");
        $online = @json_decode($data, true);
        if (!is_array($online)) {
            $online = array();
            $data = 'vng';
        }

        $online[$_SERVER['REMOTE_ADDR']] = time();

        // ## clean jobs ###

        $oldest = time() - 45;
        foreach ($online as $ip => $time) {
            if ($time < $oldest) {
                unset($online[$ip]);
            }

        }

        // ## clean jobs ###

        /*-------------- save --------------*/
        $tmp = json_encode($online);
        if ($tmp !== $data) {
            $data = $tmp;
            $fh = fopen($this->fileinfo_dir . "/online.dat", 'w') or die('<CENTER><font color=red size=3>Could not open file ! Try to chmod the folder "<B>' . $this->fileinfo_dir . '</B>" to 777</font></CENTER>');
            fwrite($fh, $data) or die('<CENTER><font color=red size=3>Could not write file ! Try to chmod the folder "<B>' . $this->fileinfo_dir . '</B>" to 777</font></CENTER>');
            fclose($fh);
            @chmod($this->fileinfo_dir . "/online.dat", 0666);
        }

        /*-------------- /save --------------*/
        return count($online);
    }

    public function size_name($link, $cookie)
    {
        if (!$link || !stristr($link, 'http')) {
            return;
        }

        $link = str_replace(" ", "%20", $link);
        $port = 80;
        $schema = parse_url(trim($link));
        $host = $schema['host'];
        $scheme = "http://";
        if (empty($schema['path'])) {
            return;
        }

        $gach = explode("/", $link);
        list($path1, $path) = explode($gach[2], $link);
        if (isset($schema['port'])) {
            $port = $schema['port'];
        } elseif ($schema['scheme'] == 'https') {
            $scheme = "ssl://";
            $port = 443;
        }

        if ($scheme != "ssl://") {
            $scheme = "";
        }
        $errno = 0;
        $errstr = "";
        $hosts = $scheme . $host . ':' . $port;
        if ($this->proxy != 0) {
            if (strpos($this->proxy, "|")) {
                list($ip, $user) = explode("|", $this->proxy);
                $auth = base64_encode($user);
            } else {
                $ip = $this->proxy;
            }

            $data = "GET {$link} HTTP/1.1\r\n";
            if (isset($auth)) {
                $data .= "Proxy-Authorization: Basic $auth\r\n";
            }

            $fp = @stream_socket_client("tcp://{$ip}", $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        } else {
            $data = "GET {$path} HTTP/1.1\r\n";
            $fp = @stream_socket_client($hosts, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        }

        $data .= "User-Agent: " . $this->UserAgent . "\r\n";
        $data .= "Host: {$host}\r\n";
        $data .= "Referer: {$this->url}\r\n";
        $data .= $cookie ? "Cookie: $cookie\r\n" : '';
        $data .= "Connection: Close\r\n\r\n";

        if (!$fp) {
            return -1;
        }

        fputs($fp, $data);
        fflush($fp);

        $header = "";
        do {
            if (!$header) {
                $header .= fgets($fp, 8192);
                if (!stristr($header, "HTTP/1")) {
                    break;
                }

            } else {
                $header .= fgets($fp, 8192);
            }

        } while (strpos($header, "\r\n\r\n") === false);

        if (stristr($header, "TTP/1.0 200 OK") || stristr($header, "TTP/1.1 200 OK") || stristr($header, "TTP/1.1 206")) {
            $filesize = trim($this->cut_str($header, "Content-Length:", "\n"));
        } else {
            $filesize = -1;
        }

        if (!is_numeric($filesize)) {
            $filesize = -1;
        }

        $filename = "";

        if (stristr($header, "filename")) {
            if (preg_match("/; filename=(.*?);/", $header, $match)) {
                $filename = trim($match[1]);
            } else {
                $filename = trim($this->cut_str($header, "filename", "\n"));
            }

        } else {
            $filename = substr(strrchr($link, '/'), 1);
        }

        $filename = self::convert_name($filename);
        return array(
            $filesize,
            $filename,
        );
    }

    public function site_hash($url)
    {
        if (strpos($url, "4shared.com")) {
            $site = "4S";
        } elseif (strpos($url, "asfile.com")) {
            $site = "AS";
        } elseif (strpos($url, "bitshare.com")) {
            $site = "BS";
        } elseif (strpos($url, "depositfiles.com") || strpos($url, "dfiles.eu")) {
            $site = "DF";
        } elseif (strpos($url, "extabit.com")) {
            $site = "EB";
        } elseif (strpos($url, "filefactory.com")) {
            $site = "FF";
        } elseif (strpos($url, "filepost.com")) {
            $site = "FP";
        } elseif (strpos($url, "hotfile.com")) {
            $site = "HF";
        } elseif (strpos($url, "lumfile.com")) {
            $site = "LF";
        } elseif (strpos($url, "mediafire.com")) {
            $site = "MF";
        } elseif (strpos($url, "megashares.com")) {
            $site = "MS";
        } elseif (strpos($url, "netload.in")) {
            $site = "NL";
        } elseif (strpos($url, "rapidgator.net")) {
            $site = "RG";
        } elseif (strpos($url, "ryushare.com")) {
            $site = "RY";
        } elseif (strpos($url, "turbobit.net")) {
            $site = "TB";
        } elseif (strpos($url, "uploaded.to") || strpos($url, "ul.to") || strpos($url, "uploaded.net")) {
            $site = "UT";
        } elseif (strpos($url, "uploading.com")) {
            $site = "UP";
        } elseif (strpos($url, "1fichier.com")) {
            $site = "1F";
        } elseif (strpos($url, "rapidshare.com")) {
            $site = "RS";
        } elseif (strpos($url, "fshare.vn")) {
            $site = "FshareVN";
        } elseif (strpos($url, "up.4share.vn") || strpos($url, "4share.vn")) {
            $site = "4ShareVN";
        } elseif (strpos($url, "share.vnn.vn")) {
            $site = "share.vnn.vn";
        } elseif (strpos($url, "upfile.vn")) {
            $site = "UpfileVN";
        } elseif (strpos($url, "mega.co.nz") || strpos($url, "mega.nz")) {
            $site = "MEGA";
        } else {
            $schema = parse_url($url);
            $site = preg_replace("/(www\.|\.com|\.net|\.biz|\.info|\.org|\.us|\.vn|\.jp|\.fr|\.in|\.to)/", "", $schema['host']);
        }

        return $site;
    }

    public function convert($filesize)
    {
        $filesize = str_replace(",", ".", $filesize);
        if (preg_match('/^([0-9]{1,4}+(\.[0-9]{1,2})?)/', $filesize, $value)) {
            if (stristr($filesize, "TB")) {
                $value = $value[1] * 1024 * 1024 * 1024 * 1024;
            } elseif (stristr($filesize, "GB")) {
                $value = $value[1] * 1024 * 1024 * 1024;
            } elseif (stristr($filesize, "MB")) {
                $value = $value[1] * 1024 * 1024;
            } elseif (stristr($filesize, "KB")) {
                $value = $value[1] * 1024;
            } else {
                $value = $value[1];
            }

        } else {
            $value = 0;
        }

        return $value;
    }

    public function convertmb($filesize)
    {
        if (!is_numeric($filesize)) {
            return $filesize;
        }

        $soam = false;
        if ($filesize < 0) {
            $filesize = abs($filesize);
            $soam = true;
        }

        if ($filesize >= 1024 * 1024 * 1024 * 1024) {
            $value = ($soam ? "-" : "") . round($filesize / (1024 * 1024 * 1024 * 1024), 2) . " TB";
        } elseif ($filesize >= 1024 * 1024 * 1024) {
            $value = ($soam ? "-" : "") . round($filesize / (1024 * 1024 * 1024), 2) . " GB";
        } elseif ($filesize >= 1024 * 1024) {
            $value = ($soam ? "-" : "") . round($filesize / (1024 * 1024), 2) . " MB";
        } elseif ($filesize >= 1024) {
            $value = ($soam ? "-" : "") . round($filesize / (1024), 2) . " KB";
        } else {
            $value = ($soam ? "-" : "") . $filesize . " Bytes";
        }

        return $value;
    }

    public function uft8html2utf8($s)
    {
        if (!function_exists('uft8html2utf8_callback')) {
            function uft8html2utf8_callback($t)
            {
                $dec = $t[1];
                if ($dec < 128) {
                    $utf = chr($dec);
                } else if ($dec < 2048) {
                    $utf = chr(192 + (($dec - ($dec % 64)) / 64));
                    $utf .= chr(128 + ($dec % 64));
                } else {
                    $utf = chr(224 + (($dec - ($dec % 4096)) / 4096));
                    $utf .= chr(128 + ((($dec % 4096) - ($dec % 64)) / 64));
                    $utf .= chr(128 + ($dec % 64));
                }

                return $utf;
            }
        }

        return preg_replace_callback('|&#([0-9]{1,});|', 'uft8html2utf8_callback', $s);
    }

    public function convert_name($filename)
    {
        $filename = urldecode($filename);
        $filename = Tools_get::uft8html2utf8($filename);
        $filename = preg_replace("/(\]|\[|\@|\"\;\?\=|\"|=|\*|UTF-8|\')/U", "", $filename);
        $filename = preg_replace("/(HTTP|http|WWW|www|\.html|\.htm)/i", "", $filename);
        $filename = str_replace($this->banned, '.xxx', $filename);
        if (empty($filename) == true) {
            $filename = substr(md5(time() . $url), 0, 10);
        }

        return $filename;
    }

    public function convert_time($time)
    {
        if ($time >= 86400) {
            $time = round($time / (60 * 24 * 60), 1) . " " . $this->lang['days'];
        } elseif (86400 > $time && $time >= 3600) {
            $time = round($time / (60 * 60), 1) . " " . $this->lang['hours'];
        } elseif (3600 > $time && $time >= 60) {
            $time = round($time / 60, 1) . " " . $this->lang['mins'];
        } else {
            $time = $time . " " . $this->lang['sec'];
        }

        return $time;
    }
}
