from pathlib import Path
from ..jp2parser import JP2parser

TEST_DIR = Path(__file__).parent / "data"

def test_map_property():
    parser = JP2parser(TEST_DIR / "iris_sample.jp2")
    assert parser.map.meta["CRPIX1"] == 471.5

def test_rotation_updates_properties():
    jp2 = JP2parser(TEST_DIR / "iris_sample.jp2")
    before_dimensions = jp2.map.data.shape
    jp2.applyRotation()
    after_dimensions = jp2.map.data.shape
    assert before_dimensions != after_dimensions
    # NAXIS 1 -> image width
    assert jp2.map.meta["NAXIS1"] == jp2.map.data.shape[1]
    # NAXIS 2 -> image height
    assert jp2.map.meta["NAXIS2"] == jp2.map.data.shape[0]