"""
Importing sunpy and the associated libraries is a bottleneck in making a fast API that can quickly extract HEEQ coordinates from a JP2 file.
To overcome this bottleneck, get_heeq is implemented as a miniature client/server application where the server handles the processing, and the client simply requests coordinates
"""

from get_heeq import get_heeq_coordinates_from_jp2_file
from argparse import ArgumentParser
import socket
import os
import pickle

DEFAULT_SOCKET_FILE = "/tmp/heeq_server.sock"
PROGRAM_DESCRIPTION = "HEEQ Server for quickly getting HEEQ coordinates from jp2 files"

# Set arguments to be passed to parser.add_argument here.
# Format is ([positional_args], {keyword_args: value})
PROGRAM_ARGS = [
    (['-s', '--socket'], {'help': 'Path to socket file to use', 'dest': 'socket_file'})
]

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

def enable_server(socket_file):
    with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as s:
        s.bind(socket_file)
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

    os.remove(socket_file)

# All args set will be passed as keyword args to main
def main(socket_file):
    if (socket_file is None):
        print("Socket file not specified, using {}".format(DEFAULT_SOCKET_FILE))
        socket_file = DEFAULT_SOCKET_FILE
    enable_server(socket_file)
    pass

# Reference: https://docs.python.org/3/library/argparse.html
def parse_args():
    parser = ArgumentParser(description=PROGRAM_DESCRIPTION)
    for args in PROGRAM_ARGS:
        parser.add_argument(*args[0], **args[1])
    return parser.parse_args()

if __name__ == "__main__":
    args = parse_args()
    main(**vars(args))
