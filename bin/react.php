#!/usr/bin/env php
<?php

require __DIR__ . '/../app/autoload.php';

$kernel = new AppKernel('prod', false);

/**
 * @param React\Http\Request  $request
 * @param React\Http\Response $response
 */
$callback = function ($request, $response) use ($kernel) {
    $method = $request->getMethod();
    $headers = $request->getHeaders();
    $query = $request->getQuery();
    $content = null;
    $post = array();
    if (in_array(strtoupper($method), array('POST', 'PUT', 'DELETE', 'PATCH')) &&
        isset($headers['Content-Type']) &&
        (0 === strpos($headers['Content-Type'], 'application/x-www-form-urlencoded'))
    ) {
        parse_str($content, $post);
    }
    $sfRequest = new Symfony\Component\HttpFoundation\Request(
        $query,
        $post,
        array(),
        array(), // To get the cookies, we'll need to parse the headers
        array(),
        array(), // Server is partially filled a few lines below
        $content
    );
    $sfRequest->setMethod($method);
    $sfRequest->headers->replace($headers);
    $sfRequest->server->set('REQUEST_URI', $request->getPath());
    if (isset($headers['Host'])) {
        $sfRequest->server->set('SERVER_NAME', explode(':', $headers['Host'])[0]);
    }
    $sfResponse = $kernel->handle($sfRequest);

    $response->writeHead(
        $sfResponse->getStatusCode(),
        $sfResponse->headers->all()
    );
    $response->end($sfResponse->getContent());
    $kernel->terminate($request, $response);
};

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server($loop);
$http = new React\Http\Server($socket);

$http->on('request', $callback);
$socket->listen(1337);
$loop->run();