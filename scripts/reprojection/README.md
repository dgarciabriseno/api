# Image Reprojection

This module handles reprojecting helioviewer images to different
observers. The goal is that this module will be able to take an image
from a specific instrument (i.e. SDO) and reproject the image as if it
was taken from a different position (i.e. STEREO).

Usage:
```bash
python3 helio_reproject.py <image_to_reproject (source)> <image_with_location_coordinates>
```

