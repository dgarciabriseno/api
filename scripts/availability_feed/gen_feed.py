"""
Utility for updating the availability RSS Feed located at
api/docroot/status.xml. Though you can technically create the
status.xml file anywhere you'd like.

The feedgen plugin itself doesn't support loading RSS feeds
from an XML file, so the solution to maintaining a feed is
to keep the feed file stored as a python pickle object. This
way it can be easily reloaded and updated in order to update
the existing XML feed.

Since helioviewer may be forked and run on different servers,
you may specify the host that will be linked in the RSS Feed
by setting the environment variable:
HV_FEED_HOST="Your host"
For Example (don't add the / at the end):
HV_FEED_HOST="https://api.helioviewer.org"
"""

import argparse
import pickle
import os

from feedgen.feed import FeedGenerator
from feedgen.util import formatRFC2822
from pathlib import Path
from datetime import datetime, timezone

PICKLE_FEED="feed.pickle"

def parse_args():
    parser = argparse.ArgumentParser(description="Create or update a simple RSS feed")
    parser.add_argument("feed", type=str, help="Path to the rss.xml file to create or update")
    parser.add_argument("title", type=str, help="Title for the new feed entry.")
    parser.add_argument("description", type=str, help="Description of what occurred")
    return parser.parse_args()

def get_feed() -> FeedGenerator:
    """
    Reads the existing feed's pickle or creates a new feed
    """
    # If the pickle file exists, then load it and return it.
    if Path(PICKLE_FEED).exists():
        with open(PICKLE_FEED, "rb") as fp:
            return pickle.load(fp)
    # Otherwise create an return a whole new feed.
    else:
        return create_new_feed()

def get_host() -> str:
    """
    Since helioviewer may run on different hosts, this
    gets the host that the current service is running on via
    an environment variable
    """
    if "HV_FEED_HOST" in os.environ:
        return os.environ["HV_FEED_HOST"]
    else:
        return "https://api.helioviewer.org"

def create_new_feed() -> FeedGenerator:
    """
    Creates and returns a new FeedGenerator object with some default
    metadata
    """
    feed = FeedGenerator()
    feed.title("Helioviewer Availability Service")
    feed.author({"name": "The Helioviewer Team"})
    feed.link(href=get_host() + "/status.xml")
    feed.description("Reports on helioviewer's latest availability status")
    feed.language('en')
    return feed

def save_feed(feed: FeedGenerator):
    """
    Saves the given feed generator to a pickle file
    """
    with open(PICKLE_FEED, "wb") as fp:
        pickle.dump(feed, fp)

def add_service_notice(feed: FeedGenerator, title: str, description: str):
    """
    Adds a service entry to the given feed.
    """
    print("Adding a feed entry")
    entry = feed.add_entry()
    entry.title(title)
    entry.description(description)
    entry.pubDate(formatRFC2822(datetime.now(timezone.utc)))

def main(rss_xml_file: str, title: str, description: str):
    feed = get_feed()
    add_service_notice(feed, title, description)
    feed.rss_file(rss_xml_file)
    save_feed(feed)

if __name__ == "__main__":
    args = parse_args()
    main(args.feed, args.title, args.description)
    pass
