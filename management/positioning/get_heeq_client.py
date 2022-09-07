import time
import socket
import pickle
import os
from argparse import ArgumentParser

PROGRAM_DESCRIPTION = "Client program for getting HEEQ coordinates from the HEEQ Server"
# Set arguments to be passed to parser.add_argument here.
# Format is ([positional_args], {keyword_args: value})
PROGRAM_ARGS = [
    (["-s", "--socket"], {'help': 'Path to server socket (Required with -j or -k)', 'dest': 'socket_file', 'type': str}),
    (["-j", "--jp2"], {'help': 'JP2 File to get coordinates from ', 'type': str}),
    (["-k", "--kill"], {'action': 'store_true', 'dest': 'kill', 'help': 'Kills the HEEQ server'}),
    (["-t", "--time"], {'action': 'store_true', 'dest': 'enable_time', 'help': 'Print how long it takes to get results from the server'})
]

def kill_server(socket_file):
    """
    Sends the kill command to the HEEQ server, for debug purposes generally.
    """
    with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as s:
        s.connect(socket_file)
        s.sendall(b'kill')

def print_coordinates(jp2_file: str, socket_file: str, enable_timer: bool):
    """
    Requests HEEQ coordinates for the given jp2_file from the server
    """
    start = time.time()
    with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as s:
        s.connect(socket_file)
        s.sendall(bytearray(jp2_file, 'utf-8'))
        result = s.recv(1024)
        data = pickle.loads(result)
        end = time.time()
        if (enable_timer):
            print("It took %f seconds to get results" % (end - start))
        print(data)

# All args set will be passed as keyword args to main
def main(jp2, kill, enable_time, socket_file):
    if (kill):
        if (socket_file is None):
            print("You must provide the path to the server socket")
            exit(1)
        kill_server(socket_file)
    elif (jp2 is not None):
        if (socket_file is None):
            print("You must provide the path to the server socket")
            exit(1)
        print_coordinates(jp2, socket_file, enable_time)
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
