"""LMSAL DataServer definition"""
import os
from helioviewer.hvpull.servers import DataServer

class LMSALDataServer(DataServer):
    """LMSAL Datasource definition"""
    def __init__(self):
        """Defines the root directory of where the data is kept at LMSAL."""
        DataServer.__init__(self, "http://sdowww.lmsal.com/sdomedia/hv_jp2kwrite/v0.8/jp2/", "LMSAL")#http://www.lmsal.com/sdomedia/hv_jp2kwrite/v0.8/jp2/
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
            groups.append(','.join(["lmsal","AIA", str(meas)]))
        # HMI
        for meas in self.hmi_measurements:
            groups.append(','.join(["lmsal","HMI", meas]))
        return groups

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []

        for date in self.get_dates(start_date, end_date):
            # AIA
            for meas in self.aia_wavelengths:
                dirs.append(os.path.join(self.uri, "AIA", date, str(meas)))

            # HMI
            for meas in self.hmi_measurements:
                dirs.append(os.path.join(self.uri, "HMI", date, meas))

        return dirs
