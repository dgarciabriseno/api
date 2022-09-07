"""
Importing sunpy and the associated libraries is a bottleneck in making a fast API that can quickly extract HEEQ coordinates from a JP2 file.
To overcome this bottleneck, get_heeq is implemented as a miniature client/server application where the server handles the processing, and the client simply requests coordinates
"""

from get_heeq import get_heeq_coordinates_from_jp2_file
import socket
import os
import pickle

SOCKET_FILE = "/tmp/heeq_server.sock"

def attempt_to_get_coordinates_from_file(jp2_file: str):
    """
    Attempts to get coordinates from a jp2 file.
    Since this is a server application is should never crash.
    With that in mind, if there's any problem getting coordinates,
    then return the exception that occurred instead of actually crashing.
    """
    try:
        results = get_heeq_coordinates_from_jp2_file(jp2_file)
        return results
    except Exception as e:
        return str(e)

def enable_server():
    with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as s:
        s.bind(SOCKET_FILE)
        s.listen(2000)
        running = True
        while running:
            conn, addr = s.accept()
            with conn:
                while True:
                    data = conn.recv(1024)
                    jp2_file = data.decode('utf-8')
                    if (jp2_file == "kill"):
                        # Exit plan to gracefully kill the server
                        # without leaving the socket hanging
                        running = False
                    else:
                        result = attempt_to_get_coordinates_from_file(jp2_file)
                        conn.sendall(pickle.dumps(result))
                    break

    os.remove(SOCKET_FILE)

if __name__ == "__main__":
    enable_server()
