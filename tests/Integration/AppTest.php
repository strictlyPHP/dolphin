<?php

declare(strict_types=1);
namespace StrictlyPHP\Tests\Dolphin\Integration;

use DI\ContainerBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use StrictlyPHP\Dolphin\App;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestLogger;

class AppTest extends TestCase
{
    protected App $app;

    public function setUp(): void
    {
        /** @var callable $router */
        $router = require __DIR__ . '/../Fixtures/routes.php';
        $this->app = new App(
            $router(),
            (new ContainerBuilder())->build()
        );
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
                'Content-Type' => 'application/json',
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
        self::assertSame('Internal Server Error', json_decode($response['body'], true)['error']);
    }

    public function testItLogsErrorWhenExceptionIsThrownWithLogger(): void
    {
        $logger = new TestLogger();
        $router = new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                throw new \RuntimeException('Test exception');
            }
        };
        $app = new App(
            $router,
            (new ContainerBuilder())->build(),
            $logger
        );

        $event = [
            "http" => [
                "path" => "/test",
                "body" => "",
                "isBase64Encoded" => false,
                "queryString" => "",
                "method" => "GET",
                "headers" => [],
            ],
        ];
        $context = new class() {
            public string $apiHost = "https://example.com";
        };

        $response = $app->run($event, $context);

        self::assertSame(500, $response['statusCode']);
        $body = json_decode($response['body'], true);
        self::assertSame('Internal Server Error', $body['error']);
        self::assertArrayNotHasKey('message', $body);
        self::assertArrayNotHasKey('trace', $body);
        self::assertSame('application/json', $response['headers']['Content-Type']);

        $logs = $logger->getLogs();
        self::assertCount(1, $logs);
        self::assertSame('error', $logs[0]['level']);
        self::assertSame('Test exception', $logs[0]['message']);
        self::assertIsString($logs[0]['context']['trace']);
    }

    public function testItExposesErrorDetailInDebugMode(): void
    {
        $logger = new TestLogger();
        $router = new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                throw new \RuntimeException('Debug visible error');
            }
        };
        $app = new App(
            $router,
            (new ContainerBuilder())->build(),
            $logger,
            true
        );

        $event = [
            "http" => [
                "path" => "/test",
                "body" => "",
                "isBase64Encoded" => false,
                "queryString" => "",
                "method" => "GET",
                "headers" => [],
            ],
        ];
        $context = new class() {
            public string $apiHost = "https://example.com";
        };

        $response = $app->run($event, $context);

        self::assertSame(500, $response['statusCode']);
        $body = json_decode($response['body'], true);
        self::assertSame('Internal Server Error', $body['error']);
        self::assertSame('Debug visible error', $body['message']);
        self::assertIsString($body['trace']);
    }

    public function testExceptionHandlerIsCalledAndResponseReturned(): void
    {
        $router = new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                throw new \RuntimeException('Handled exception');
            }
        };
        $app = new App(
            $router,
            (new ContainerBuilder())->build(),
            null,
            false,
            function (\Throwable $e): array {
                return [
                    'statusCode' => 503,
                    'body' => json_encode([
                        'error' => 'Custom: ' . $e->getMessage(),
                    ]),
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ];
            }
        );

        $event = [
            "http" => [
                "path" => "/test",
                "body" => "",
                "isBase64Encoded" => false,
                "queryString" => "",
                "method" => "GET",
                "headers" => [],
            ],
        ];
        $context = new class() {
            public string $apiHost = "https://example.com";
        };

        $response = $app->run($event, $context);

        self::assertSame(503, $response['statusCode']);
        $body = json_decode($response['body'], true);
        self::assertSame('Custom: Handled exception', $body['error']);
    }

    public function testExceptionHandlerReturningNullSuppressesLoggingButUsesDefaultResponse(): void
    {
        $logger = new TestLogger();
        $captured = null;
        $router = new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                throw new \RuntimeException('Captured exception');
            }
        };
        $app = new App(
            $router,
            (new ContainerBuilder())->build(),
            $logger,
            false,
            function (\Throwable $e) use (&$captured): ?array {
                $captured = $e;
                return null;
            }
        );

        $event = [
            "http" => [
                "path" => "/test",
                "body" => "",
                "isBase64Encoded" => false,
                "queryString" => "",
                "method" => "GET",
                "headers" => [],
            ],
        ];
        $context = new class() {
            public string $apiHost = "https://example.com";
        };

        $response = $app->run($event, $context);

        self::assertSame(500, $response['statusCode']);
        $body = json_decode($response['body'], true);
        self::assertSame('Internal Server Error', $body['error']);
        self::assertEmpty($logger->getLogs());
        self::assertInstanceOf(\RuntimeException::class, $captured);
        self::assertSame('Captured exception', $captured->getMessage());
    }

    public function testExceptionHandlerThrowingReturnsDefaultResponseAndLogs(): void
    {
        $logger = new TestLogger();
        $router = new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                throw new \RuntimeException('Original error');
            }
        };
        $app = new App(
            $router,
            (new ContainerBuilder())->build(),
            $logger,
            false,
            function (\Throwable $e): ?array {
                throw new \RuntimeException('Handler blew up');
            }
        );

        $event = [
            "http" => [
                "path" => "/test",
                "body" => "",
                "isBase64Encoded" => false,
                "queryString" => "",
                "method" => "GET",
                "headers" => [],
            ],
        ];
        $context = new class() {
            public string $apiHost = "https://example.com";
        };

        $response = $app->run($event, $context);

        self::assertSame(500, $response['statusCode']);
        $body = json_decode($response['body'], true);
        self::assertSame('Internal Server Error', $body['error']);

        $logs = $logger->getLogs();
        self::assertCount(1, $logs);
        self::assertSame('error', $logs[0]['level']);
        self::assertSame('Exception handler failed', $logs[0]['message']);
        self::assertInstanceOf(\RuntimeException::class, $logs[0]['context']['exception']);
        self::assertSame('Handler blew up', $logs[0]['context']['exception']->getMessage());
        self::assertInstanceOf(\RuntimeException::class, $logs[0]['context']['previous_exception']);
        self::assertSame('Original error', $logs[0]['context']['previous_exception']->getMessage());
    }
}
