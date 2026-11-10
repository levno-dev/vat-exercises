import { beforeEach, expect, it, vi } from 'vitest'
import { GetCommand, QueryCommand } from '@aws-sdk/lib-dynamodb'
import type { SQSEvent } from 'aws-lambda'
import { ddb, handler, sns } from './vat-level-handler'

const VAT = { vatId: 'v-1', heightM: 2.5, capacityL: 20000 }

const makeEvent = (body: object) =>
	({ Records: [{ body: JSON.stringify(body) }] } as unknown as SQSEvent)

function mockDb(previous?: object) {
	vi.spyOn(ddb, 'send').mockImplementation((async (command: unknown) => {
		if (command instanceof GetCommand) return { Item: VAT }
		if (command instanceof QueryCommand) return { Items: previous ? [previous] : [] }
		return {}
	}) as never)
}

beforeEach(() => {
	vi.restoreAllMocks()
})

it('alerts when the vat is full', async () => {
	mockDb({ ts: 1_700_000_000, volumeL: 17000 })
	const publish = vi.spyOn(sns, 'send').mockResolvedValue({} as never)

	const result = await handler(makeEvent({ vatId: 'v-1', timestamp: 1_700_003_600, distanceMm: 0 }))
	await new Promise((resolve) => setTimeout(resolve, 10)) // let the handler finish

	expect(result.statusCode).toBe(200)
	expect(publish).toHaveBeenCalled()
})

it('does not alert when the vat is half full', async () => {
	mockDb()
	const publish = vi.spyOn(sns, 'send').mockResolvedValue({} as never)

	await handler(makeEvent({ vatId: 'v-1', timestamp: 1_700_003_600, distanceMm: 1250 }))

	expect(publish).not.toHaveBeenCalled()
})
