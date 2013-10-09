<?PHP
/**
 * 透明代理类：提供GET/POST穿透，隔离COOKIE
 */
class Proxy{

	var $url;
    var $url_segments;
    var $socket;
	var $target;

    var $request_method;
    var $request_headers;
    var $request_referer;
	var $content_type;
    var $content_length;
	var $response_headers;
    var $response_body;
	var $post_body;
	var $enable_follow = true;
	var $response_cookies;
	var $redirect_url;

	function Proxy($target){
		$this->target = $target;
	}

	function request($url){
		$this->request_method = $_SERVER['REQUEST_METHOD'];
		$this->set_url($url);
		$this->open_socket();
        $this->set_request_headers();
        $this->set_response();
		return $this->return_response();
	}

	function set_url($url){

         $this->url = $url;

         if (strpos($this->url, '://') === false){
             $this->url = 'http://' . $this->url;
         }

         if (!$this->parse_url($this->url, $this->url_segments)){
             die('请输入有效URL');
         }

		 if(stripos($url, 'https')!==false){
			die('HIT '.$url);
		}
    }

    function parse_url($url, & $container){
        $temp = @parse_url($url);

        if (!empty($temp)){
            $temp['port']     = isset($temp['port']) ? $temp['port'] : 80;
            $temp['path']     = isset($temp['path']) ? $temp['path'] : '/';
            $temp['query']     = isset($temp['query']) ? '?'.$temp['query'] : '';
            $temp['file']     = substr($temp['path'], strrpos($temp['path'], '/')+1);
            $temp['dir']      = substr($temp['path'], 0, strrpos($temp['path'], '/'));
            $temp['base']     = $temp['scheme'] . '://' . $temp['host'] . ($temp['port'] != 80 ?  ':' . $temp['port'] : '') . $temp['dir'];
            $temp['prev_dir'] = $temp['path'] != '/' ? substr($temp['base'], 0, strrpos($temp['base'], '/')+1) : $temp['base'] . '/';
            $this->url_segments = $temp;
            return true;
        }

        return false;
    }


	function open_socket(){
        $this->socket = @fsockopen($this->url_segments['host'], '80', $err_no, $err_str, 12);

        if ($this->socket === false){
            die('socket open error!');
        }
    }

	function set_content_type(){
        if (preg_match("#content-type:([^\r\n]*)#i", $this->response_headers, $matches) && trim($matches[1]) != ''){
            $content_type_array = explode(';', $matches[1]);
            $this->content_type = strtolower(trim($content_type_array[0]));
        }else{
            $this->content_type = 'text/html';
        }
    }

	function set_content_length(){
        if (preg_match("#content-length:([^\r\n]*)#i", $this->response_headers, $matches) && trim($matches[1]) != ''){
            $this->content_length = trim($matches[1]);
        }else{
            $this->content_length = false;
        }
    }

	function set_request_headers(){

        $headers  = "{$this->request_method} {$this->url_segments['path']}{$this->url_segments['query']} HTTP/1.0\r\n";
        $headers .= "Host: {$this->url_segments['host']}:80\r\n";

        if (isset($_SERVER['HTTP_USER_AGENT'])){
            $headers .= 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'] . "\r\n";
        }
        if (isset($_SERVER['HTTP_ACCEPT'])){
            $headers .= 'Accept: ' . $_SERVER['HTTP_ACCEPT'] . "\r\n";
        }
        else{
            $headers .= "Accept: */*;q=0.1\r\n";
        }
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])){
            $headers .= 'X-Requested-With: ' . $_SERVER['HTTP_X_REQUESTED_WITH'] . "\r\n";
        }
        if ($this->request_referer)
			$headers .= "Referer: {$this->request_referer}\r\n";

        if (($cookies = $this->get_cookies('COOKIE')) != ''){
            $headers .= "Cookie: $cookies\r\n";
        }
        if ($this->request_method == 'POST'){
            $this->set_post_body($_POST);
			$headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$headers .= "Content-Length: " . strlen($this->post_body) . "\r\n\r\n";
			$headers .= $this->post_body;
        }

        $headers .= "\r\n";

        $this->request_headers = $headers;
    }

    function set_post_body($array){
        $array = $this->set_post_vars($array);
		foreach ($array as $key => $value){
			$this->post_body .= !empty($this->post_body) ? '&' : '';
			$this->post_body .= $key . '=' . $value;
		}
    }

    function set_post_vars($array, $parent_key = null){
        $tmp = array();

        foreach ($array as $key => $value){
            $key = isset($parent_key) ? sprintf('%s[%s]', $parent_key, urlencode($key)) : urlencode($key);
            if (is_array($value)){
                $tmp = array_merge($tmp, $this->set_post_vars($value, $key));
            }else{
                $tmp[$key] = urlencode($value);
            }
        }
        return $tmp;
    }

	function set_cookies(){

        if (preg_match_all("#set-cookie:([^\r\n]*)#i", $this->response_headers, $matches)){
            foreach ($matches[1] as $cookie_info){
                preg_match('#^\s*([^=;,\s]*)=?([^;,\s]*)#', $cookie_info, $match)  && list(, $name, $value) = $match;
                preg_match('#;\s*expires\s*=([^;]*)#i', $cookie_info, $match)      && list(, $expires)      = $match;
                preg_match('#;\s*path\s*=\s*([^;,\s]*)#i', $cookie_info, $match)   && list(, $path)         = $match;
                preg_match('#;\s*domain\s*=\s*([^;,\s]*)#i', $cookie_info, $match) && list(, $domain)       = $match;
                preg_match('#;\s*(secure\b)#i', $cookie_info, $match)              && list(, $secure)       = $match;

                $expires = isset($expires) ? strtotime($expires) : false;
                $expires = (is_numeric($expires) && time()-$expires < 0) ? false : $expires;
                $path    = isset($path)    ? $path : $this->url_segments['dir'];
                $domain  = isset($domain)  ? $domain : $this->url_segments['host'];
                $domain  = rtrim($domain, '.');

                setcookie($name, urldecode($value), $expires, $path, $domain);
				$this->response_cookies[] = array('name'=>$name, 'value'=>$value, 'expires'=>$expires, 'path'=>$path, 'domain'=>$domain);
            }

			$this->response_headers = str_replace($matches[0], '', $this->response_headers);
        }
    }

    function get_cookies($type = 'COOKIE'){
        if (!empty($_COOKIE)){
            $cookies = '';

			unset($_COOKIE['jump_session']);//清除proxy cookie
            foreach ($_COOKIE as $cookie_name => $cookie_value){

				$cookies .= $cookies != '' ? '; ' : '';
				$cookies .= "$cookie_name=$cookie_value";
            }
            return $cookies;
        }
    }

    function delete_cookies($hash){
        $cookies = $this->get_cookies('COOKIE');

        foreach ($cookies as $args){
            if ($hash == 'all' || $hash == md5($args[0].$args[1].$args[2].$args[3])){
                setcookie(urlencode("COOKIE;$args[0];$args[1];$args[2]"), '', 1, '', $this->http_host);
            }
        }
    }

	function set_response(){
        fwrite($this->socket, $this->request_headers);

        // Reset response headers and response body.
        $this->response_headers = '';
        $this->response_body = '';

        // Get the response headers first to extract content-type.
        do{
            $line = fgets($this->socket, 4096);
            $this->response_headers .= $line;
        }
        while ($line != "\r\n");

        $this->response_code = next(explode(' ', $this->response_headers));
        $this->set_content_type();
        $this->set_content_length();

        $this->set_cookies();

        if ($this->follow_location()){
            fclose($this->socket);
            $this->start_transfer($this->url);
        }else{
            // Read the HTML response in $this->response_body
            do{
                $data = fread($this->socket, 8192);
                $this->response_body .= $data;
            }
            while (strlen($data) != 0);

            fclose($this->socket);
        }
    }

	function return_response($send_hit = false){

        if ($this->content_type == 'text/css'){
            $this->response_body = $this->proxify_css($this->response_body);
        }

        $headers = explode("\r\n", $this->response_headers);

        if (!empty($this->response_body)){
            $headers[] = 'Content-Length: ' . strlen($this->response_body);
        }

		if ($send_hit){
			$headers[] = 'Hit-local-cache: YES';
		}

        $headers = array_filter($headers);

        foreach ($headers as $header){
            header($header);
        }

		$this->modify_urls();

        return $this->response_body;
    }

	function proxify_css($css){
       preg_match_all('#url\s*\(\s*(([^)]*(\\\))*[^)]*)(\)|$)?#i', $css, $matches, PREG_SET_ORDER);

       for ($i = 0, $count = count($matches); $i < $count; $i++){
           $css = str_replace($matches[$i][0], 'url(' . $this->proxify_css_url($matches[$i][1]) . ')', $css);
       }

       preg_match_all("#@import\s*(?:\"([^\">]*)\"?|'([^'>]*)'?)([^;]*)(;|$)#i", $css, $matches, PREG_SET_ORDER);

       for ($i = 0, $count = count($matches); $i < $count; $i++){
           $delim = '"';
           $url   = $matches[$i][2];

           if (isset($matches[$i][3]))
           {
               $delim = "'";
               $url = $matches[$i][3];
           }

           $css = str_replace($matches[$i][0], '@import ' . $delim . $this->proxify_css_url($matches[$i][1]) . $delim . (isset($matches[$i][4]) ? $matches[$i][4] : ''), $css);
       }

       return $css;
    }

	function proxify_css_url($url){
        $url = trim($url);
        $delim = '';

        if (strpos($url, '"') === 0)
        {
            $delim = '"';
            $url   = trim($url, '"');
        }
        else if (strpos($url, "'") === 0)
        {
            $delim = "'";
            $url   = trim($url, "'");
        }

        $url = preg_replace('#\\\(.)#', '$1', $url);
        $url = trim($url);
        $url = preg_replace('#([\(\),\s\'"\\\])#', '\\$1', $url);

        return $delim . $url . $delim;
    }

	function modify_urls(){
		$this->response_body = str_replace($this->target, $_SERVER["HTTP_HOST"], $this->response_body);
	}


	function follow_location(){

        if (preg_match("#(location|uri|content-location):([^\r\n]*)#i", $this->response_headers, $matches)){
            if (($url = trim($matches[2])) == ''){
                return false;
            }

			if(!$this->enable_follow){
				$this->response_headers = preg_replace("/location.*?\n/i", '', $this->response_headers);
				$this->redirect_url = $matches[2];
				return false;
			}

			if(stripos($matches[2], $this->url_segments['host']) === false){
				return false;//跳转到外站
			}

            $this->url = $url;
            return true;
        }
        return false;
    }

	function start_transfer($url){

        $this->set_url($url);
		$this->open_socket();
        $this->set_request_headers();
        $this->set_response();
    }
}
?>