import json
import os
from decimal import Decimal
from unittest.mock import patch

os.environ["AWS_CONFIG_FILE"] = os.devnull  # ignore the machine's AWS profiles
os.environ["AWS_SHARED_CREDENTIALS_FILE"] = os.devnull
os.environ.setdefault("AWS_DEFAULT_REGION", "ap-southeast-2")
os.environ.setdefault("READINGS_TABLE", "vat-readings")
os.environ.setdefault("VATS_TABLE", "vats")
os.environ.setdefault("ALERT_TOPIC_ARN", "arn:aws:sns:ap-southeast-2:123456789012:vat-alerts")

import vat_level_handler as h

VAT = {"vatId": "v-1", "heightM": Decimal("2.5"), "capacityL": Decimal("20000")}


def make_event(**body):
    return {"Records": [{"body": json.dumps(body)}]}


@patch.object(h, "sns")
@patch.object(h, "readings_table")
@patch.object(h, "vats_table")
def test_alerts_when_vat_is_full(vats_table, readings_table, sns):
    vats_table.get_item.return_value = {"Item": VAT}
    readings_table.query.return_value = {"Items": [{"ts": 1_700_000_000, "volumeL": Decimal("17000")}]}

    result = h.handler(make_event(vatId="v-1", timestamp=1_700_003_600, distanceMm=0), None)

    assert result["statusCode"] == 200
    sns.publish.assert_called()


@patch.object(h, "sns")
@patch.object(h, "readings_table")
@patch.object(h, "vats_table")
def test_no_alert_when_vat_is_half_full(vats_table, readings_table, sns):
    vats_table.get_item.return_value = {"Item": VAT}
    readings_table.query.return_value = {"Items": []}

    h.handler(make_event(vatId="v-1", timestamp=1_700_003_600, distanceMm=1250), None)

    sns.publish.assert_not_called()
