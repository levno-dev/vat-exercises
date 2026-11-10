from milkings import Milking, Reading, detect_milkings

T0 = 1_700_000_000
TEN_MIN = 600


def series(*volumes: float) -> list[Reading]:
    """Readings ten minutes apart, starting at T0."""
    return [Reading(T0 + i * TEN_MIN, v) for i, v in enumerate(volumes)]


def ts(i: int) -> int:
    return T0 + i * TEN_MIN


def test_no_readings():
    assert detect_milkings([]) == []


def test_level_vat_has_no_milking():
    assert detect_milkings(series(3000, 3000, 3000, 3000, 3000)) == []


def test_emptying_vat_has_no_milking():
    assert detect_milkings(series(3000, 3000, 2000, 0, 0)) == []


def test_single_milking():
    readings = series(800, 0, 0, 1000, 2500, 3000, 3000, 3000, 3000)

    assert detect_milkings(readings) == [Milking(start_ts=ts(2), end_ts=ts(5), volume_l=3000)]
