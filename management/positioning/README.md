# Positioning
This directory contains scripts related to gathering observer positions
from fits metadata

# Listing
## get\_heeq.py
Uses sunpy to extract the observer position from a jp2 file.
Returns those coordinates in the HEEQ coordinate system, (X, Y, Z) coordinates with the sun at the origin (0, 0, 0).

