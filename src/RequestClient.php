<?php

namespace xlerr\httpca;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Yii;
use yii\base\Object;

/**
 * @method bool get(string | UriInterface $uri, array $options = [])
 * @method bool head(string | UriInterface $uri, array $options = [])
 * @method bool put(string | UriInterface $uri, array $options = [])
 * @method bool post(string | UriInterface $uri, array $options = [])
 * @method bool patch(string | UriInterface $uri, array $options = [])
 * @method bool delete(string | UriInterface $uri, array $options = [])
 * @method bool getAsync(string | UriInterface $uri, array $options = [])
 * @method bool headAsync(string | UriInterface $uri, array $options = [])
 * @method bool putAsync(string | UriInterface $uri, array $options = [])
 * @method bool postAsync(string | UriInterface $uri, array $options = [])
 * @method bool patchAsync(string | UriInterface $uri, array $options = [])
 * @method bool deleteAsync(string | UriInterface $uri, array $options = [])
 */
class RequestClient extends Object
{
    const FAILURE = 1;
    const SUCCESS = 0;

    public $baseUri;

    /**
     * @var \GuzzleHttp\ClientInterface|\GuzzleHttp\Client
     */
    public $client;

    /**
     * @var array
     */
    public $clientOptions = [
        RequestOptions::HTTP_ERRORS => false,
    ];

    /**
     * @var array
     *      [
     *           'code' => self::SUCCESS, // self::FAILURE
     *           'message' => null,
     *           'data' => null
     *      ]
     */
    private $response;

    /**
     * @internal
     */
    public function init()
    {
        parent::init();

        if (null === $this->client) {
            if (null !== $this->baseUri) {
                $this->baseUri = rtrim($this->baseUri, '/') . '/';
            }

            $options = array_merge($this->clientOptions, [
                'handler'  => $this->getHandlerStack(),
                'base_uri' => $this->baseUri,
            ]);

            $this->client = new Client($options);
        }
    }

    /**
     * @return \GuzzleHttp\HandlerStack
     */
    protected function getHandlerStack()
    {
        $stack = HandlerStack::create();

        if (YII_DEBUG) {
            // 记录请求信息
            $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
                Yii::trace([
                    'url'     => (string)$request->getUri(),
                    'method'  => $request->getMethod(),
                    'headers' => $request->getHeaders(),
                    'body'    => (string)$request->getBody(),
                ], __CLASS__ . ':requestMiddleware');

                return $request;
            }));

            // 记录响应信息
            $stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
                Yii::trace([
                    'statusCode' => $response->getStatusCode(),
                    'headers'    => $response->getHeaders(),
                    'body'       => (string)$response->getBody(),
                ], __CLASS__ . ':responseMiddleware');

                return $response;
            }));
        }

        return $stack;
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return array
     */
    protected function handleResponse(ResponseInterface $response)
    {
        $content = (string)$response->getBody();

        $data = (array)json_decode($content, true) + [
                'code'    => self::FAILURE,
                'message' => '返回值格式错误<br/>' . $content,
                'data'    => null,
            ];

        // 将`code`转换为`self::SUCCESS|self::FAILURE`
        if ($data['code'] !== self::SUCCESS) {
            $data['code'] = self::FAILURE;
        }

        return $data;
    }

    public function reset()
    {
        $this->response = null;
    }

    /**
     * @return bool
     */
    public function getHasError()
    {
        return $this->getCode() !== self::SUCCESS;
    }

    /**
     * @return int self::SUCCESS|self::FAILURE
     */
    public function getCode(): int
    {
        return $this->response['code'] ?? self::FAILURE;
    }

    /**
     * @return mixed|null
     */
    public function getError()
    {
        return $this->response['message'] ?? null;
    }

    /**
     * @return mixed|null
     */
    public function getData()
    {
        return $this->response['data'] ?? null;
    }

    /**
     * @return array
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param string $name
     * @param array  $params
     *
     * @return bool true:success, false:failure
     */
    public function __call($name, $params)
    {
        $this->reset();

        $response = call_user_func_array([$this->client, $name], $params);

        $this->response = $this->handleResponse($response);

        return !$this->getHasError();
    }
}
