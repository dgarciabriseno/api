"""JSOC DataServer definition"""
import os
import datetime
from helioviewer.hvpull.servers import DataServer

class JSOCDataServer(DataServer):
    """JSOC Datasource definition"""
    def __init__(self):
        """Defines the root directory of where the data is kept at LMSAL."""
        DataServer.__init__(self, "http://jsoc.stanford.edu/data/", "JSOC")
        self.pause = datetime.timedelta(minutes=0)
        self.aia_wavelengths = [4500, 304, 171, 1600, 193, 211, 335, 131, 94, 1700]
        self.hmi_measurements = ["continuum", "magnetogram"]

    def compute_groups(self):
        """
        Returns a comma separated list of identifiers that can be used
        to organize image sources
        """
        groups = []
        # AIA
        for meas in self.aia_wavelengths:
            groups.append(','.join(["jsoc","AIA", str(meas)]))
        # HMI
        for meas in self.hmi_measurements:
            groups.append(','.join(["jsoc","HMI", meas]))
        return groups


    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []

        for date in self.get_dates(start_date, end_date):
            # AIA
            for meas in self.aia_wavelengths:
                dirs.append(os.path.join(self.uri, "aia", "images", date, str(meas)))

            # HMI
            for meas in self.hmi_measurements:
                dirs.append(os.path.join(self.uri, "hmi", "images", date, meas))

        return dirs
