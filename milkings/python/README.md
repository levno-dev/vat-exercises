# Find the milkings

A sensor above a farm's milk vat reports how many litres are in the vat, roughly every 10 minutes.
The vat fills during each **milking**, then a tanker empties it.

Write `detect_milkings` in `milkings.py`: given a vat's readings, return the milkings.

- A milking is a rise in volume of at least `min_rise_l` litres.
- `start_ts` is the last reading before the volume starts rising; `end_ts` is the first reading at the highest point.

Make the tests in `test_milkings.py` pass, then we'll add more cases. Ask as many questions as you like.

```bash
pip install pytest   # or: uv run --with pytest pytest
pytest
```
