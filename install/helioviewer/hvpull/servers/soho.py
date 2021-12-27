"""SOHO DataServer"""
from helioviewer.hvpull.servers import DataServer
import datetime
import os

class SOHODataServer(DataServer):
    def __init__(self):
        """This assumes that SOHO jp2 files are calculated locally.  They are
        then copied over to a directory on the main Helioviewer server, from
        which it can be picked up by the ingestion services.  Note that
        a full path is required to specify the location of the data."""
        DataServer.__init__(self, "/home/ireland/incoming/soho_incoming/v0.8/jp2", "SOHO")
        self.pause = datetime.timedelta(minutes=30)
        self.eit_wavelengths = [171, 195, 284, 304]
        self.lasco_detectors = ["C2", "C3"]

    def compute_groups(self):
        groups = []
        # EIT
        for meas in self.eit_wavelengths:
            groups.append(",".join(["soho", "EIT", str(meas)]))

        # LASCO
        for detector in self.lasco_detectors:
            groups.append(",".join(["soho", "LASCO-"+detector, "white-light"]))
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
