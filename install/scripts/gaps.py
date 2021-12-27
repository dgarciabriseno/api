from configparser import NoSectionError
import re

from datetime import datetime, timedelta
from helioviewer.hvpull.servers.lmsal2 import LMSALDataServer2

class Gap:
    """
    Container for gap information
    """
    def __init__(self, start, end):
        self.start = start
        self.end = end

class GapDefinition:
    """
    Parses a gap/timedelta definition in the form
    "days:hours:minutes:seconds"

    example: 05:01:00:05
            represents a timedelta of 5 days, 1 hour, and 5 seconds
    """
    def __init__(self, gap):
        self._parse_gap(gap)

    def _parse_gap(self, gap):
        gap_split = gap.split(':')
        days = int(gap_split[0])
        hours = int(gap_split[1])
        minutes = int(gap_split[2])
        seconds = int(gap_split[3])
        self._dt = timedelta(days=days, hours=hours, minutes=minutes, seconds=seconds)

    def get_dt(self) -> timedelta:
        return self._dt


class GapFinder:
    """
    Given a list of file names, the server definition and configuration file,
    this class will scan the file list to detect any gaps in the timestamps
    between data files.

    You can define what is considered a gap by specifying the gap's timedelta
    in the configuration file.
    """
    def __init__(self, file_list, server_def, conf):
        self._server = server_def
        self._files = file_list
        self._conf = conf
        self._build_lists()
        self._sort_lists()

    def _extract_datetime(self, image_file_name):
        match = re.match(r".*(\d\d\d\d_\d+_\d+__\d+_\d+_\d+)", image_file_name)
        timestamp = match.group(1)
        return datetime.strptime(timestamp, r"%Y_%m_%d__%H_%M_%S")

    def _contains_all(self, needles, haystack):
        """
        Returns true if all elements of needles are found in the haystack
        """
        found = True
        for item in needles:
            found &= item in haystack
        return found

    def _get_group(self, groups, image_name):
        """
        Gets the group that the image falls into
        """
        for group in groups:
            if self._contains_all(group.split(','), image_name):
                return group

    def _build_lists(self):
        groups = self._server.compute_groups()
        # Create buckets to dump image list into
        self._lists = {}
        for group in groups:
            self._lists[group] = []
        # Go through the image list and sort them into buckets
        for image in self._files:
            group = self._get_group(groups, image)
            self._lists[group].append(image)

    def _sort_list(self, unsorted):
        unsorted.sort(key=self._extract_datetime)
        pass

    def _sort_lists(self):
        for group in self._lists:
            self._sort_list(self._lists[group])

    def _log_gaps(self, gaps, group):
        for gap in gaps:
            print("[%s]: %s to %s" % (group, gap.start, gap.end))

    def _scan_list_for_gaps(self, image_list, gap_dt):
        gaps = []
        count = len(image_list)
        if count > 0:
            last_index = count - 1
            last_timestamp = self._extract_datetime(image_list[0])
            for index in range(1, count):
                timestamp = self._extract_datetime(image_list[index])
                # If this is the last image
                if index == last_index:
                    # compare the timestamp to the end time
                    # TODO
                    pass
                else:
                    # Otherwise, compare this timestamp to the last timestamp
                    delta = timestamp - last_timestamp
                    # If the delta is greater than the threshold, flag it.
                    if delta > gap_dt:
                        gaps.append(Gap(last_timestamp, timestamp))
                # Update last_timestamp for next iteration
                last_timestamp = timestamp
        return gaps

    def _get_gap_dt(self, group):
        try:
            gap_def = self._conf.get(group, "gap")
        except NoSectionError:
            gap_def = self._conf.get("gaps", "default_gap")
        gap = GapDefinition(gap_def)
        return gap.get_dt()

    def scan_for_gaps(self):
        for group in self._lists:
            dt = self._get_gap_dt(group)
            gaps = self._scan_list_for_gaps(self._lists[group], dt)
            self._log_gaps(gaps, group)
