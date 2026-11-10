<?php

declare(strict_types=1);

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\MockHandler;
use Aws\Result;
use Aws\Sns\SnsClient;
use PHPUnit\Framework\TestCase;

final class VatLevelHandlerTest extends TestCase
{
    private const VAT = ['vatId' => 'v-1', 'heightM' => 2.5, 'capacityL' => 20000];

    private MockHandler $dynamoMock;
    private MockHandler $snsMock;
    private VatLevelHandler $handler;

    protected function setUp(): void
    {
        putenv('READINGS_TABLE=vat-readings');
        putenv('VATS_TABLE=vats');
        putenv('ALERT_TOPIC_ARN=arn:aws:sns:ap-southeast-2:123456789012:vat-alerts');

        $this->dynamoMock = new MockHandler();
        $this->snsMock = new MockHandler();
        $config = ['region' => 'ap-southeast-2', 'version' => 'latest', 'credentials' => false];

        $this->handler = new VatLevelHandler(
            new DynamoDbClient($config + ['handler' => $this->dynamoMock]),
            new SnsClient($config + ['handler' => $this->snsMock]),
        );
    }

    public function testAlertsWhenVatIsFull(): void
    {
        $this->mockDb(['ts' => 1_700_000_000, 'volumeL' => 17000]);
        $this->snsMock->append(new Result([]));

        $result = ($this->handler)($this->makeEvent(['vatId' => 'v-1', 'timestamp' => 1_700_003_600, 'distanceMm' => 0]));

        $this->assertSame(200, $result['statusCode']);
        $this->assertCount(0, $this->snsMock); // publish used up the queued result
    }

    public function testNoAlertWhenVatIsHalfFull(): void
    {
        $this->mockDb(null);
        $this->snsMock->append(new Result([]));

        ($this->handler)($this->makeEvent(['vatId' => 'v-1', 'timestamp' => 1_700_003_600, 'distanceMm' => 1250]));

        $this->assertCount(1, $this->snsMock); // publish was never called
    }

    private function mockDb(?array $previous): void
    {
        $marshaler = new Marshaler();
        $this->dynamoMock->append(
            new Result(['Item' => $marshaler->marshalItem(self::VAT)]),
            new Result(['Items' => $previous ? [$marshaler->marshalItem($previous)] : []]),
            new Result([]),
        );
    }

    private function makeEvent(array $body): array
    {
        return ['Records' => [['body' => json_encode($body)]]];
    }
}
