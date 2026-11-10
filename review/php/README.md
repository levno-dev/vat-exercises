# Add nearly-full vat alerts

Farmers have asked for a heads-up before their vat overflows. This adds a Lambda that reads vat level
readings from the `vat-readings` SQS queue, saves each reading's volume, and texts the farmer when the
vat passes 90% full, with an estimate of how long until it is full.

## What changed

- New handler `src/VatLevelHandler.php`, triggered by SQS (batch size 10). PHP 8.3 on Bref (function runtime).
- Each message body is one reading from the vat sensor:
  `{"vatId": "v-123", "timestamp": 1700000000, "distanceMm": 420}`
  (`distanceMm` is the distance from the sensor at the top of the vat down to the milk.)
- Vat dimensions come from the existing `vats` table (`heightM`, `capacityL`).
- Readings are stored in the `vat-readings` table, partitioned by vat and day.

Unit tests added and passing.

---

**Your task:** a teammate has asked you to review this change before it ships. Talk us through the
comments you would leave, and which ones would stop you approving it.
