import { DynamoDBClient } from '@aws-sdk/client-dynamodb'
import { DynamoDBDocumentClient, GetCommand, PutCommand, QueryCommand } from '@aws-sdk/lib-dynamodb'
import { PublishCommand, SNSClient } from '@aws-sdk/client-sns'
import type { SQSEvent } from 'aws-lambda'

export const ddb = DynamoDBDocumentClient.from(new DynamoDBClient({}))
export const sns = new SNSClient({})

const READINGS_TABLE = process.env.READINGS_TABLE!
const VATS_TABLE = process.env.VATS_TABLE!
const ALERT_TOPIC_ARN = process.env.ALERT_TOPIC_ARN!

const NEARLY_FULL_PCT = 90
const alertedVats = new Set<string>()

interface VatReading {
	vatId: string
	timestamp: number
	distanceMm: number
}

export const handler = async (event: SQSEvent) => {
	event.Records.forEach(async (record) => {
		try {
			await processReading(JSON.parse(record.body))
		} catch (e) {
			console.error(`Failed to process reading: ${e}`)
		}
	})

	return { statusCode: 200 }
}

async function processReading(reading: VatReading) {
	const { vatId, timestamp: ts, distanceMm: distance } = reading

	const { Item: vat } = await ddb.send(new GetCommand({ TableName: VATS_TABLE, Key: { vatId } }))
	const height = vat!.heightM
	const capacity = vat!.capacityL

	const volume = ((height - distance) / height) * capacity
	const pctFull = Math.round((volume / capacity) * 1000) / 10

	const previous = await getLatestReading(vatId)
	let fillRate: number | undefined
	if (previous) {
		fillRate = (volume - previous.volumeL) / (ts - previous.ts) // litres per second
	}

	const day = new Date(ts * 1000).toLocaleDateString('en-CA')
	await ddb.send(
		new PutCommand({
			TableName: READINGS_TABLE,
			Item: { pk: `${vatId}#${day}`, ts, volumeL: volume, pctFull },
		}),
	)

	if (pctFull >= NEARLY_FULL_PCT && !alertedVats.has(vatId)) {
		let message = `Vat ${vatId} is ${pctFull}% full`
		if (fillRate) {
			const hoursLeft = (capacity - volume) / fillRate / 3600
			message += `, about ${hoursLeft.toFixed(1)} hours until full`
		}
		await sns.send(new PublishCommand({ TopicArn: ALERT_TOPIC_ARN, Message: message }))
		alertedVats.add(vatId)
	}
}

async function getLatestReading(vatId: string) {
	const now = Math.floor(Date.now() / 1000)
	const day = new Date(now * 1000).toLocaleDateString('en-CA')
	const { Items } = await ddb.send(
		new QueryCommand({
			TableName: READINGS_TABLE,
			KeyConditionExpression: 'pk = :pk AND ts <= :now',
			ExpressionAttributeValues: { ':pk': `${vatId}#${day}`, ':now': now },
			ScanIndexForward: false,
			Limit: 1,
		}),
	)
	return Items?.[0]
}
