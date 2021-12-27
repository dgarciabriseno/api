# Run from install folder as a module
# python3 -m scripts.find_gaps
# To use this script, specify a data server and time range to analyze.
# You can define a "Gap" by setting

import argparse
import sys
import os
from datetime import datetime
from settings.config import get_config
from helioviewer.hvpull.net.daemon import ImageRetrievalDaemon
from scripts.gaps import GapFinder
from helioviewer import init_logger

EXPECTED_TIME_FORMAT = '%Y-%m-%d %H:%M:%S'

def get_args():
    parser = argparse.ArgumentParser(description='Scans targets for gaps in JPEG 2000 images.')
    parser.add_argument('-d', '--data-servers', dest='servers', required=True,
                        help='Data servers from which data should be scanned', default='lmsal')
    parser.add_argument('-b', '--browse-method', dest='browse_method', default='http',
                        help='Method for locating files on servers (default: http)')
    parser.add_argument('-s', '--start', metavar='date', dest='start', required=True,
                        help='Search for data with observation times later than this date/time (default: 24 hours ago)')
    parser.add_argument('-e', '--end', metavar='date', dest='end',
                        help='Search for data with observation times earlier than this date/time (default: Now')
    parser.add_argument('-c', '--config-file', metavar='file', dest='config',
                        help='Full path to hvpull user defined general configuration file')
    parser.add_argument('-l', '--log-path', metavar='log', dest='log',
                        help='Filepath to use for logging events. Defaults to HVPull working directory.')

    # Parse arguments
    args = parser.parse_args()

    # Append browser to browse_method
    args.browse_method += "browser"

    # Parse servers
    args.servers = args.servers.split(",")

    if (args.end is None):
        args.end = _get_default_end_time()

    return args

def validate_args(args, servers, browsers):
    """Validate arguments"""
    for server in args.servers:
        if server not in servers:
            print ("Invalid data server specified. Valid server choices include:")
            for i in servers.keys():
                print (i)
            sys.exit()
    if args.browse_method not in browsers:
        print ("Invalid browse method specified. Valid browse methods include:")
        for i in browsers.keys():
            print (i)
        sys.exit()

def _get_default_end_time():
    """
    Returns an string representing right now.
    """
    now = datetime.now()
    return now.strftime(EXPECTED_TIME_FORMAT)

def date_to_datetime(date):
    format = EXPECTED_TIME_FORMAT
    return datetime.strptime(date, format)

def get_image_list(retriever, args):
    starttime = date_to_datetime(args.start)
    endtime = date_to_datetime(args.end)
    return retriever.query_server(retriever.browsers[0], starttime, endtime)

def configure_logger(logpath, conf):
    # Configure loggings'
    if logpath is not None:
        logfile = os.path.abspath(logpath)
    else:
        logfile = os.path.join(conf.get('directories', 'working_dir'),
                               "log/gap_finder.log")
    init_logger(logfile)

def main():
    # Get args and configuration
    args = get_args()
    validate_args(args, ImageRetrievalDaemon.get_servers(),
                  ImageRetrievalDaemon.get_browsers())
    conf = get_config()

    configure_logger(args.log, conf)

    # Initialize daemon
    daemon = ImageRetrievalDaemon(args.servers, args.browse_method, "urllib", conf)

    # Get list of all files
    image_list = get_image_list(daemon, args)

    # Find any gaps on the given server
    gap_finder = GapFinder(image_list, daemon.servers[0], conf)
    gap_finder.scan_for_gaps()


if __name__ == "__main__":
    main()
