<?php

declare(strict_types=1);
namespace StrictlyPHP\Tests\Dolphin\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use StrictlyPHP\Dolphin\App;

class AppTest extends TestCase
{
    protected App $app;

    public function setUp(): void
    {
        /** @var callable $router */
        $router = require __DIR__ . '/../Fixtures/routes.php';
        $this->app = new App($router());
    }

    public function testItRuns(): void
    {
        $event = [
            "http" => [
                "path" => "/test",
                "body" => "{\n \"username\":\"foo\",\n \"email\": \"roo@bar.com\",\n \"name\": {\n \"givenName\": \"Foo\",\n \"familyName\": \"Bar\"\n }\n}",
                "isBase64Encoded" => false,
                "queryString" => "",
                "method" => "POST",
                "headers" => [
                    "accept" => "*/*",
                    "x-request-id" => "235429a822a186a68641e0d5a8328036",
                    "user-agent" => "PostmanRuntime/7.37.3",
                    "x-forwarded-proto" => "https",
                    "host" => "ccontroller",
                    "cf-ray" => "8e8aa8e98a19947c-LHR",
                    "cdn-loop" => "cloudflare; loops=1",
                    "cf-visitor" => "{\"scheme\":\"https\"}",
                    "cf-connecting-ip" => "82.132.231.236",
                    "content-type" => "application/json",
                    "cookie" => "__cf_bm=S8BA7ZJm2xD6S0vv5QZtnG.syKunLwUJLLROhX2uKYg-1732631806-1.0.1.1-77Pqalzl7_KshctkVpRLB3rQ8iSN8v7uAAFQ5fZo2V7PokxuQ1065YI1DRW5VdWAk.jhL7TADyjizBJQ3bhxhw",
                    "postman-token" => "db0bfc6b-83d5-4130-96de-2dccf3a02623",
                    "cf-ipcountry" => "GB",
                    "accept-encoding" => "gzip, br",
                    "x-forwarded-for" => "82.132.231.236",
                ],
            ],
        ];
        $context = new class() {
            public string $functionName;

            public string $functionVersion;

            public string $activationId;

            public string $requestId;

            public int $deadline;

            public string $apiHost;

            public string $apiKey;

            public string $namespace;

            public function __construct()
            {
                $this->functionName = "/fn-1f96b927-f60e-4b49-9b80-0ec6d721d62c/main/user";
                $this->functionVersion = "0.0.3";
                $this->activationId = "f8fc1a3e97414f22bc1a3e97416f2274";
                $this->requestId = "9dd09c223c154f19568a4430675e4b7b";
                $this->deadline = 1732629285984;
                $this->apiHost = "https://faas-lon1-917a94a7.doserverless.co";
                $this->apiKey = "";
                $this->namespace = "fn-1f96b927-f60e-4b49-9b80-0ec6d721d62c";
            }

            public function getRemainingTimeInMillis(): int
            {
                return 1000;
            }
        };
        $response = $this->app->run($event, $context);

        $expectedResponse = [
            'statusCode' => 200,
            'body' => '{"requestBody":"{\n \"username\":\"foo\",\n \"email\": \"roo@bar.com\",\n \"name\": {\n \"givenName\": \"Foo\",\n \"familyName\": \"Bar\"\n }\n}"}',
            'headers' => [
                'Content-Type' => ['application/json'],
            ],
        ];
        self::assertSame($expectedResponse, $response);
        self::assertInstanceOf(RequestHandlerInterface::class, $this->app->getRouter());
    }

    public function testItHandlesNotFound(): void
    {
        $event = [
            "http" => [
                "path" => "/not-found",
                "body" => "",
                "isBase64Encoded" => false,
                "queryString" => "",
                "method" => "GET",
                "headers" => [],
            ],
        ];
        $context = new class() {
            public string $functionName;

            public string $functionVersion;

            public string $activationId;

            public string $requestId;

            public int $deadline;

            public string $apiHost;

            public string $apiKey;

            public string $namespace;

            public function __construct()
            {
                $this->functionName = "/fn-1f96b927-f60e-4b49-9b80-0ec6d721d62c/main/user";
                $this->functionVersion = "0.0.3";
                $this->activationId = "f8fc1a3e97414f22bc1a3e97416f2274";
                $this->requestId = "9dd09c223c154f19568a4430675e4b7b";
                $this->deadline = 1732629285984;
                $this->apiHost = "https://faas-lon1-917a94a7.doserverless.co";
                $this->apiKey = "";
                $this->namespace = "fn-1f96b927-f60e-4b49-9b80-0ec6d721d62c";
            }

            public function getRemainingTimeInMillis(): int
            {
                return 1000;
            }
        };
        $response = $this->app->run($event, $context);

        self::assertSame(500, $response['statusCode']);
        self::assertSame('Not Found', json_decode($response['body'], true)['error']);
    }
}
