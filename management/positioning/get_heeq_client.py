import time
import socket
import pickle
import os
from argparse import ArgumentParser

SOCKET_FILE = "/tmp/heeq_server.sock"
PROGRAM_DESCRIPTION = "Client program for getting HEEQ coordinates from the HEEQ Server"
# Set arguments to be passed to parser.add_argument here.
# Format is ([positional_args], {keyword_args: value})
PROGRAM_ARGS = [
    (["-j", "--jp2"], {'help': 'JP2 File to get coordinates from', 'type': str}),
    (["-k", "--kill"], {'action': 'store_true', 'dest': 'kill', 'help': 'Kills the HEEQ server'}),
    (["-t", "--time"], {'action': 'store_true', 'dest': 'enable_time', 'help': 'Print how long it takes to get results from the server'})
]

def kill_server():
    """
    Sends the kill command to the HEEQ server, for debug purposes generally.
    """
    with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as s:
        s.connect(SOCKET_FILE)
        s.sendall(b'kill')

def print_coordinates(jp2_file: str, enable_timer: bool):
    """
    Requests HEEQ coordinates for the given jp2_file from the server
    """
    start = time.time()
    with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as s:
        s.connect(SOCKET_FILE)
        s.sendall(bytearray(jp2_file, 'utf-8'))
        result = s.recv(1024)
        data = pickle.loads(result)
        end = time.time()
        if (enable_timer):
            print("It took %f seconds to get results" % (end - start))
        print(data)

# All args set will be passed as keyword args to main
def main(jp2, kill, enable_time):
    if (kill):
        kill_server()
    elif (jp2 is not None):
        print_coordinates(jp2, enable_time)
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
