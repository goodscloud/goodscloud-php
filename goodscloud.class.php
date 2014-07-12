<?php
class Goodscloud{
  private $gtin = null;
  private $host;
  private $email;
  private $password;
  private $port;
  private $session;

  public function __construct($host, $email, $password){
    date_default_timezone_set('Europe/Berlin');
    $this->host = $host;
    $this->email = $email;
    $this->password = $password;
    $this->port = strpos($this->host, "https") == 0 ? 443 : 80;
    $this->login();
  }

  private function login(){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->host . '/session');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "GC-Email: " . $this->email,
      "GC-Password: " . $this->password,
    ));
    // Set so curl_exec returns the result instead of outputting it.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $this->session = json_decode(curl_exec($ch));
    curl_close($ch);
    if (!isset($this->session) || $this->session->email != $this->email){
      throw new Exception("API credentials incorrect", 1);
    }
  }

  public function get($uri, $params) {
    return $this->signed_request('GET', $uri, $params);
  }

  public function post($uri, $params, $data) {
    return $this->signed_request('POST', $uri, $params, $data);
  }

  private static function http_request_curl($method, $host, $port, $path, $params, $data){
    $ch = curl_init();
    $request_params = '';
    foreach ($params as $k => $v) {
      $request_params .= urlencode($k) .'='. urlencode($v) .'&';
    }
    $request_params = substr($request_params, 0, -1);

    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
    ));

    if ($request_params) {
      curl_setopt($ch, CURLOPT_URL, $host.$path."?".$request_params);
    } else {
      curl_setopt($ch, CURLOPT_URL, $host.$path);
    }

    if ($method == "GET"){
      ;
    } elseif ($method == "POST"){
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    // Set so curl_exec returns the result instead of outputting it.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Get the response and close the channel.
    $json = json_decode(curl_exec($ch));
    curl_close($ch);
    return $json;
  }

  private function signed_request($method, $path, $params, $data=""){
    if (is_array($data)) {
      $data = json_encode($data);
    }
    $expires = date("Y-m-d\TH:i:s\Z", time() + 60); //current time + 60 seconds
    $auth_params = array(
          "key"     => $this->session->auth->app_key,
          "token"   => $this->session->auth->app_token,
          "expires" => $expires
      );
    $params = array_merge($params, $auth_params);
    ksort($params);
    $str_params = "";
    foreach ($params as $key => $value) {
        $str_params.= "&$key=$value";
    }
    $str_params = trim($str_params, "&");
    $sign_str = implode(array(
        $method,
        $path,
        md5($str_params),
        md5($data),
        $this->session->auth->app_token,
        $expires
      ), "\n");

    $sign = trim(base64_encode(hash_hmac("sha1", utf8_encode($sign_str), $this->session->auth->app_secret, true)), "=");
    $params = array_merge($params, array("sign" => $sign));

    return $this::http_request_curl($method, $this->host, $this->port, $path, $params, $data);
  }

}
