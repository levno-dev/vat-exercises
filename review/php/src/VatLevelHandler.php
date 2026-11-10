<?php

declare(strict_types=1);

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Aws\Sns\SnsClient;

final class VatLevelHandler
{
    private const NEARLY_FULL_PCT = 90;

    private static array $alertedVats = [];

    private Marshaler $marshaler;

    public function __construct(
        private DynamoDbClient $dynamo,
        private SnsClient $sns,
    ) {
        $this->marshaler = new Marshaler();
    }

    public function __invoke(array $event): array
    {
        foreach ($event['Records'] as $record) {
            try {
                $this->processReading(json_decode($record['body'], true));
            } catch (\Throwable $e) {
                error_log("Failed to process reading: {$e->getMessage()}");
            }
        }

        return ['statusCode' => 200];
    }

    private function processReading(array $reading): void
    {
        $vatId = $reading['vatId'];
        $ts = $reading['timestamp'];
        $distance = $reading['distanceMm'];

        $result = $this->dynamo->getItem([
            'TableName' => getenv('VATS_TABLE'),
            'Key' => $this->marshaler->marshalItem(['vatId' => $vatId]),
        ]);
        $vat = $this->marshaler->unmarshalItem($result['Item']);
        $height = $vat['heightM'];
        $capacity = $vat['capacityL'];

        $volume = ($height - $distance) / $height * $capacity;
        $pctFull = round($volume / $capacity * 100, 1);

        $previous = $this->getLatestReading($vatId);
        $fillRate = null;
        if ($previous) {
            $fillRate = ($volume - $previous['volumeL']) / ($ts - $previous['ts']); // litres per second
        }

        $day = date('Y-m-d', $ts);
        $this->dynamo->putItem([
            'TableName' => getenv('READINGS_TABLE'),
            'Item' => $this->marshaler->marshalItem([
                'pk' => "{$vatId}#{$day}",
                'ts' => $ts,
                'volumeL' => $volume,
                'pctFull' => $pctFull,
            ]),
        ]);

        if ($pctFull >= self::NEARLY_FULL_PCT && !in_array($vatId, self::$alertedVats, true)) {
            $message = "Vat {$vatId} is {$pctFull}% full";
            if ($fillRate) {
                $hoursLeft = ($capacity - $volume) / $fillRate / 3600;
                $message .= sprintf(', about %.1f hours until full', $hoursLeft);
            }
            $this->sns->publish(['TopicArn' => getenv('ALERT_TOPIC_ARN'), 'Message' => $message]);
            self::$alertedVats[] = $vatId;
        }
    }

    private function getLatestReading(string $vatId): ?array
    {
        $now = time();
        $day = date('Y-m-d', $now);
        $result = $this->dynamo->query([
            'TableName' => getenv('READINGS_TABLE'),
            'KeyConditionExpression' => 'pk = :pk AND ts <= :now',
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':pk' => "{$vatId}#{$day}",
                ':now' => $now,
            ]),
            'ScanIndexForward' => false,
            'Limit' => 1,
        ]);
        $items = $result['Items'];
        return $items ? $this->marshaler->unmarshalItem($items[0]) : null;
    }
}
