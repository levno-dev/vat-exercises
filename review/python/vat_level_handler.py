import json
import logging
import os
import time
from datetime import datetime

import boto3
from boto3.dynamodb.conditions import Key

logger = logging.getLogger()
logger.setLevel(logging.INFO)

dynamodb = boto3.resource("dynamodb")
sns = boto3.client("sns")

readings_table = dynamodb.Table(os.environ["READINGS_TABLE"])
vats_table = dynamodb.Table(os.environ["VATS_TABLE"])
ALERT_TOPIC_ARN = os.environ["ALERT_TOPIC_ARN"]

NEARLY_FULL_PCT = 90


def handler(event, context):
    for record in event["Records"]:
        try:
            process_reading(json.loads(record["body"]))
        except Exception as e:
            logger.error(f"Failed to process reading: {e}")

    return {"statusCode": 200}


def process_reading(reading, alerted_vats=[]):
    vat_id = reading["vatId"]
    ts = reading["timestamp"]
    distance = reading["distanceMm"]

    vat = vats_table.get_item(Key={"vatId": vat_id})["Item"]
    height = vat["heightM"]
    capacity = vat["capacityL"]

    volume = (height - distance) / height * capacity
    pct_full = round(volume / capacity * 100, 1)

    previous = get_latest_reading(vat_id)
    fill_rate = None
    if previous:
        fill_rate = (volume - previous["volumeL"]) / (ts - previous["ts"])  # litres per second

    day = datetime.fromtimestamp(ts).strftime("%Y-%m-%d")
    readings_table.put_item(
        Item={
            "pk": f"{vat_id}#{day}",
            "ts": ts,
            "volumeL": volume,
            "pctFull": pct_full,
        }
    )

    if pct_full >= NEARLY_FULL_PCT and vat_id not in alerted_vats:
        message = f"Vat {vat_id} is {pct_full}% full"
        if fill_rate:
            hours_left = (capacity - volume) / fill_rate / 3600
            message += f", about {hours_left:.1f} hours until full"
        sns.publish(TopicArn=ALERT_TOPIC_ARN, Message=message)
        alerted_vats.append(vat_id)


def get_latest_reading(vat_id):
    now = int(time.time())
    day = datetime.fromtimestamp(now).strftime("%Y-%m-%d")
    response = readings_table.query(
        KeyConditionExpression=Key("pk").eq(f"{vat_id}#{day}") & Key("ts").lte(now),
        ScanIndexForward=False,
        Limit=1,
    )
    items = response["Items"]
    return items[0] if items else None
