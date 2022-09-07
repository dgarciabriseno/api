# Positioning
This directory contains scripts related to gathering observer positions
from fits metadata

# Listing
## get\_heeq.py
Uses sunpy to extract the observer position from a jp2 file.
Returns those coordinates in the HEEQ coordinate system, (X, Y, Z) coordinates with the sun at the origin (0, 0, 0).

## get\_heeq\_server/client.py
These scripts make up the HEEQ application which speedily extracts HEEQ coordinates from jp2 files.
This client/server pair takes advantage of having an ongoing running python process which can quickly parse jp2 files.
The greatest advantage is this allows it to skip re-importing all the heavy libraries used in computing the coordinate system.
On the current productio server, this reduces the time it takes to parse jp2 files.
Using `get_heeq.py` directly takes roughly 2 seconds. Whereas using this client/server pair takes roughly 0.3 seconds per request.

### Setting up HEEQ Server/Client
Make sure that Helioviewer's `Config.ini` contains `python_path` and `heeq_socket`.
`python_path` is the path to the python binary, this could be a virtual environment or simply the name of the python command itself.
`heeq_socket` is the path to your HEEQ Server Socket. You can specify the socket to be created using the `-s` option on `get_heeq_server.py`
Make sure the `heeq_socket` has permissions to be accessed by the apache webserver, then the `getObserverPosition` endpoint will be available.

