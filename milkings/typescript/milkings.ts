export interface Reading {
	ts: number // epoch seconds
	volumeL: number // litres of milk in the vat
}

export interface Milking {
	startTs: number // last reading before the volume starts rising
	endTs: number // first reading at the highest point
	volumeL: number // litres added
}

/** Find the milkings in a vat's readings. */
export function detectMilkings(readings: Reading[], minRiseL = 500): Milking[] {
	throw new Error('Not implemented')
}
