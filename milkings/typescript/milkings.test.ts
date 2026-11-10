import { expect, it } from 'vitest'
import { detectMilkings, type Reading } from './milkings'

const T0 = 1_700_000_000
const TEN_MIN = 600

/** Readings ten minutes apart, starting at T0. */
const series = (...volumes: number[]): Reading[] =>
	volumes.map((volumeL, i) => ({ ts: T0 + i * TEN_MIN, volumeL }))

const ts = (i: number) => T0 + i * TEN_MIN

it('finds nothing in no readings', () => {
	expect(detectMilkings([])).toEqual([])
})

it('finds no milking while the vat is level', () => {
	expect(detectMilkings(series(3000, 3000, 3000, 3000, 3000))).toEqual([])
})

it('finds no milking while the vat is emptied', () => {
	expect(detectMilkings(series(3000, 3000, 2000, 0, 0))).toEqual([])
})

it('finds a single milking', () => {
	const readings = series(800, 0, 0, 1000, 2500, 3000, 3000, 3000, 3000)

	expect(detectMilkings(readings)).toEqual([{ startTs: ts(2), endTs: ts(5), volumeL: 3000 }])
})
