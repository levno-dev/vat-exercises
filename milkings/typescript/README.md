# Find the milkings

A sensor above a farm's milk vat reports how many litres are in the vat, roughly every 10 minutes.
The vat fills during each **milking**, then a tanker empties it.

Write `detectMilkings` in `milkings.ts`: given a vat's readings, return the milkings.

- A milking is a rise in volume of at least `minRiseL` litres.
- `startTs` is the last reading before the volume starts rising; `endTs` is the first reading at the highest point.

Make the tests in `milkings.test.ts` pass, then we'll add more cases. Ask as many questions as you like.

```bash
npm install
npm test
```
