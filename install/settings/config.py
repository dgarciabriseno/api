import sys
import os

if (sys.version_info >= (3, 0)):
    from configparser import ConfigParser
else:
    from ConfigParser import ConfigParser

def get_cross_version_parser():
    config = ConfigParser()
    if (sys.version_info >= (3, 0)):
        config.load_config = config.read_file
    else:
        config.load_config = config.readfp
    return config


def get_config(filepath=None):
    """Load configuration file"""
    config = get_cross_version_parser()

    basedir = os.path.dirname(os.path.realpath(__file__))
    default_userconfig = os.path.join(basedir, 'settings.cfg')

    if filepath is not None and os.path.isfile(filepath):
        config.load_config(open(filepath))
    elif os.path.isfile(default_userconfig):
        config.load_config(open(default_userconfig))
    else:
        config.load_config(open(os.path.join(basedir, 'settings.example.cfg')))

    return config
