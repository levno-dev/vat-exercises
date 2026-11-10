from dataclasses import dataclass


@dataclass(frozen=True)
class Reading:
    ts: int  # epoch seconds
    volume_l: float  # litres of milk in the vat


@dataclass(frozen=True)
class Milking:
    start_ts: int  # last reading before the volume starts rising
    end_ts: int  # first reading at the highest point
    volume_l: float  # litres added


def detect_milkings(readings: list[Reading], min_rise_l: float = 500) -> list[Milking]:
    """Find the milkings in a vat's readings."""
    raise NotImplementedError
