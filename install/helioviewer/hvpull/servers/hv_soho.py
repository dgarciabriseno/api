"""HelioViewer SOHO Cache"""
from helioviewer.hvpull.servers import DataServer
import datetime
import os

class HVSOHODataServer(DataServer):
    def __init__(self):
        """
        This source pulls directly from helioviewer.org. It is meant to be used
        for mirrors rather than the main server itself.
        """
        DataServer.__init__(self, "https://helioviewer.org/jp2/", "SOHO")
        self.pause = datetime.timedelta(minutes=30)
        self.eit_wavelengths = [171, 195, 284, 304]
        self.lasco_detectors = ["C2", "C3"]

    def compute_groups(self):
        groups = []
        # EIT
        for meas in self.eit_wavelengths:
            groups.append(",".join(["helioviewer", "EIT", str(meas)]))

        # LASCO
        for detector in self.lasco_detectors:
            groups.append(','.join(["helioviewer", "LASCO-"+detector, "white-light"]))
        return groups

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []

        for date in self.get_dates(start_date, end_date):
            # EIT
            for meas in self.eit_wavelengths:
                dirs.append(os.path.join(self.uri, "EIT", date, str(meas)))

            # LASCO
            for detector in self.lasco_detectors:
                dirs.append(os.path.join(self.uri, "LASCO-"+detector, date, "white-light"))

        return dirs

    def get_starttime(self):
        """Default start time to use when retrieving data"""
        return datetime.datetime.utcnow() - datetime.timedelta(days=3)
