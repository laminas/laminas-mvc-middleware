<?php

declare(strict_types=1);

namespace LaminasTest\Mvc\Middleware;

use Brick\VarExporter\VarExporter;
use Laminas\Diactoros\Response;
use Laminas\Mvc\Middleware\PipeSpec;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_values;

/**
 * @covers \Laminas\Mvc\Middleware\PipeSpec
 */
class PipeSpecTest extends TestCase
{
    public function testAcceptsSpreadAndRetainsOrder(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                return new Response();
            }
        };
        $spec       = new PipeSpec('container_key_string', $middleware, 'another_key');
        self::assertSame(
            ['container_key_string', $middleware, 'another_key'],
            array_values($spec->getSpec())
        );
    }

    public function testCanBeExportedAndReimported(): void
    {
        $middleware = static function (): ResponseInterface {
            return new Response();
        };
        $spec       = new PipeSpec('container_key_string', $middleware, 'another_key');
        $export     = VarExporter::export(
            $spec,
            VarExporter::ADD_RETURN | VarExporter::CLOSURE_SNAPSHOT_USES
        );
        /** @var PipeSpec $restoredPipeSpec */
        $restoredPipeSpec = eval($export);
        self::assertInstanceOf(PipeSpec::class, $restoredPipeSpec);
        /** @var list<mixed> $restoredSpec */
        $restoredSpec = $restoredPipeSpec->getSpec();
        self::assertEquals(
            ['container_key_string', $middleware, 'another_key'],
            array_values($restoredSpec)
        );
    }

    public function testFailsOnEmptyExportedState(): void
    {
        $this->expectExceptionMessage('Failed to restore state. Config cache is invalid');
        PipeSpec::__set_state([]);
    }
}
