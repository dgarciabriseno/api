"""
This script will query HV_FEED_HOST/v2/getStatus/ in order to determine
how old the latest images are.

If certain thresholds specified in this file are met, then a message
will be printed which indicates which image source is behind schedule.
"""

from gen_feed import get_host
import requests

# If AIA seconds behind is greater than this value, then it is a problem.
# AIA should not be more than 2 hours behind since helioviewer.org pulls
# every hour.
AIA_LAG_THRESHOLD_S    =   7200 # 2 hours
LASCO_LAG_THRESHOLD_S  =  57600 # 16 hours
SECCHI_LAG_THRESHOLD_S = 864000 # 10 days
SWAP_LAG_THRESHOLD_S   = 172800 # 3 days

try:
    # thresholds is a file that can be created for specifying the desired lag thresholds.
    # It's not a tracked file and must be manually created. It should simply define
    # chosen variables listed above.
    from thresholds import *
except ImportError:
    pass

print("AIA Lag: ")
print(AIA_LAG_THRESHOLD_S)


def query_status_api():
    host = get_host()
    api_endpoint = host + "/v2/getStatus/"
    resp = requests.get(api_endpoint)
    return resp.json()

def check_for_lag(source: str, threshold_seconds: float, seconds_behind: int):
    if (seconds_behind > threshold_seconds):
        threshold_hours = threshold_seconds / 60 / 60
        hours_behind = seconds_behind / 60 / 60
        print("%s image are behind by %0.2f hours. Alert threshold is %0.2f hours." % (source, hours_behind, threshold_hours))

def check_status(status):
    aia_seconds_behind = status['AIA']['secondsBehind']
    check_for_lag("AIA/HMI", AIA_LAG_THRESHOLD_S, aia_seconds_behind)

    # this covers SOHO
    lasco_seconds_behind = status['LASCO']['secondsBehind']
    check_for_lag("LASCO", LASCO_LAG_THRESHOLD_S, lasco_seconds_behind)

    # this coveres STEREO
    secchi_seconds_behind = status['SECCHI']['secondsBehind']
    check_for_lag("STEREO Source", SECCHI_LAG_THRESHOLD_S, secchi_seconds_behind)
    # SWAP
    swap_seconds_behind = status['SWAP']['secondsBehind']
    check_for_lag("SWAP", SWAP_LAG_THRESHOLD_S, swap_seconds_behind)
    pass

if __name__ == "__main__":
    status_json = query_status_api()
    check_status(status_json)
