<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/12/25
 * Time: 10:43 AM
 */

namespace EasySwoole\HttpClient;


use EasySwoole\HttpClient\Bean\Response;
use EasySwoole\HttpClient\Bean\Url;
use EasySwoole\HttpClient\Exception\InvalidUrl;
use Swoole\Coroutine\Http\Client;
use Swoole\WebSocket\Frame;

class HttpClient
{
	/**
	 * @var Url
	 */
    protected $url;
    protected $header = [
        "User-Agent" => 'EasySwooleHttpClient/0.1',
        'Accept' => 'text/html,application/xhtml+xml,application/xml',
        'Accept-Encoding' => 'gzip',
        'Pragma' => 'no-cache',
        'Cache-Control' => 'no-cache',
		'Content-Type' => 'application/x-www-form-urlencoded'
    ];
    protected $clientSetting = [];
    protected $httpClient;

    /*
     * 请求包体
     */
    protected $requestData = [];

    /*
     * addFile 方法
     */
    protected $postFiles = [];
    /*
     * addData 方法
     */
    protected $postDataByAddData = [];
    protected $cookies = [];

    protected $httpMethod = 'GET';

    function __construct(?string $url = null)
    {
        if(!empty($url)){
            $this->setUrl($url);
        }
        $this->setTimeout(3);
        $this->setConnectTimeout(5);
    }

    function setUrl(string $url):HttpClient
    {
        $info = parse_url($url);
        $this->url = new Url($info);
        if(empty($this->url->getHost())){
            throw new InvalidUrl("url: {$url} invalid");
        }
        return $this;
    }

	/**
	 * 设置Http请求的包体
	 * @param array $data 键值对
	 * @return $this
	 */
    public function setData(array $data){
    	$this->requestData = $data;
    	return $this;
	}

    public function setTimeout(float $timeout):HttpClient
    {
        $this->clientSetting['timeout'] = $timeout;
        return $this;
    }

    public function setConnectTimeout(float $connectTimeout):HttpClient
    {
        $this->clientSetting['connect_timeout'] = $connectTimeout;
        return $this;
    }

    public function setClientSettings(array $settings):HttpClient
    {
        $this->clientSetting = $settings;
        return $this;
    }

    public function setClientSetting($key,$setting):HttpClient
    {
        $this->clientSetting[$key] = $setting;
        return $this;
    }

    public function setHeaders(array $header):HttpClient
    {
        $this->header = $header;
        return $this;
    }

	/**
	 * 设置http request method
	 * @param string $httpMethod
	 * @return HttpClient
	 */
    public function setHttpMethod(string $httpMethod):HttpClient
	{
    	$this->httpMethod = strtoupper($httpMethod);
    	return $this;
	}

    public function setHeader($key,$value):HttpClient
    {
        $this->header[$key] = $value;
        return $this;
    }

    public function post($data = [],$contentType = null)
    {
        $this->requestData = $data;
        $this->httpMethod = 'POST';
        if($contentType){
            $this->setHeader('Content-Type',$contentType);
        }
        if(is_string($data)){
            $this->setHeader('Content-Length',strlen($data));
        }
    }

    public function postJSON(string $json):HttpClient
    {
        $this->post($json,'text/json');
        return $this;
    }

    public function postXML(string $xml):HttpClient
    {
        $this->post($xml,'text/xml');
        return $this;
    }

    /*
     * 与swoole cient一致
     * string $path, string $name, string $mimeType = null, string $filename = null, int $offset = 0, int $length = 0
     *
     */
    public function addFile(...$args):HttpClient
    {
        $this->postFiles[] = $args;
        return $this;
    }

    /*
     * 与swoole client 一致
     * string $data, string $name, string $mimeType = null, string $filename = null
     */
    public function addData(...$args):HttpClient
    {
        $this->postDataByAddData[] = $args;
        return $this;
    }

    public function addCookies(array $cookies):HttpClient
    {
        $this->cookies = $cookies;
        return $this;
    }

    public function addCookie($key,$value):HttpClient
    {
        $this->cookies[$key] = $value;
        return $this;
    }

    public function exec(?float $timeout = null):Response
    {
        if($timeout !== null){
            $this->setTimeout($timeout);
        }

        $client = $this->createClient();

		if (count($this->postFiles)){
			foreach ($this->postFiles as $file){
				$client->addFile(...$file);
			}
		}
		if (count($this->postDataByAddData)){
			foreach ($this->postDataByAddData as $addDatum){
				$client->addData(...$addDatum);
			}
		}

		$client->setMethod($this->httpMethod);
		$client->setData(http_build_query($this->requestData));

		$client->execute($this->getUri($this->url->getPath(),$this->url->getQuery()));

        $response = new Response((array)$client);
        $client->close();
        return $response;
    }

    public function upgrade(bool $mask = true):bool
    {
        $this->clientSetting['websocket_mask'] = $mask;
        $client = $this->createClient();
        return $client->upgrade($this->getUri($this->url->getPath(),$this->url->getQuery()));
    }

    public function push(Frame $frame):bool
    {
        return $this->httpClient->push($frame);
    }

    public function recv(float $timeout = 1.0):Frame
    {
        return $this->httpClient->recv($timeout);
    }

    public function getClient():Client
    {
        return $this->httpClient;
    }

    private function createClient():Client
    {
        if($this->url instanceof Url){
            $port = $this->url->getPort();
            $ssl = $this->url->getScheme() === 'https';
            if(empty($port)){
                $port = $ssl ? 443 : 80;
            }
            $cli = new Client($this->url->getHost(), $port, $ssl);
            $cli->set($this->clientSetting);
            $cli->setHeaders($this->header);
            if(!empty($this->cookies)){
                $cli->setCookies($this->cookies);
            }
            $this->httpClient = $cli;
            return $this->httpClient;
        }else{
            throw new InvalidUrl("url is empty");
        }
    }

    private function getUri(?string $path, ?string $query): string
    {
        if($path == null){
            $path = '/';
        }
        return !empty($query) ? $path . '?' . $query : $path;
    }
}
