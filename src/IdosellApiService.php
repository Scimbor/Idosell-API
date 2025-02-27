<?php

namespace Api\Idosell;

use Exception;

use Api\Idosell\Request;
use Api\Idosell\Response;

class IdosellApiService
{
    const DEFAULT_RESULTS_PAGE_LIMIT = 100;

    private $request;
    private $url;
    private $params;
    private $method;
    private $connection;
    public $results;
    
    public function checkService()
    {
        return true;
    }

    public function __construct(string $connection = '')
    {
        $this->connection = new Connection($connection);
    }

    public static function connection($connection = '')
    {
        return new self($connection);
    }

    public function request(string $url)
    {
        $this->request = new Request($this->connection->getConfig());
        $this->url = $url;

        return $this;
    }

    public function __call($method, $args)
    {
        $this->params = ($args[0] ?? []);
        $this->method = $method;

        $this->results = $this->request->doRequest($method, $this->url, $this->params);

        if (empty($this->results)) {
            return $this->results;
        }

        // If endpoint has not pagination
        if (isset($this->results->type) && $this->results->type == Response::RESPONSE_SINGLE_TYPE) {
            return $this->results;
        }

        // Sometimest API gates have params limits property but not return in response
        if ((isset($this->results->resultsNumberPage) && isset($this->results->resultsNumberAll)) || (isset($this->params['params']['resultsPage']) || isset($this->params['params']['results_page']))) {
            $this->params['params']['resultsPage'] = $this->results->resultsPage + 1;
            $this->params['params']['results_page'] = $this->results->resultsPage + 1;
        }

        return $this;
    }

    public function each(callable $callback)
    {
        collect($this->results->results)->each(function($item) use (&$callback) {
            $callback($item);
        });

        if ($this->params['params']['resultsPage'] == $this->results->resultsNumberPage) {
            return;
        }

        $this->results = $this->request->doRequest($this->method, $this->url, $this->params);
        $this->each($callback);
    }
}
