import os
from helioviewer.hvpull.servers import DataServer

class GongMagnetogramDataServer(DataServer):
    def __init__(self):
        DataServer.__init__(self, "http://swhv.oma.be/jp2/gong_magnetogram/", "GONG_MAG")

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []

        for date in self.get_dates(start_date, end_date):
            dirs.append(os.path.join(self.uri, date))

        return dirs