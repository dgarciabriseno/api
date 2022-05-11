#!/usr/bin/env python3

"""
This module implements image reprojection using sunpy and astropy for
use with reprojecting helioviewer images to different positions.

This program works by reading the coordinates from SOURCE_IMAGE and
reprojecting it to the coordinates read from DEST_IMAGE. The result is
a jp2 file that is SOURCE_IMAGE as seen from the coordinates of
DEST_IMAGE.

Usage: helio_reproject <source_image> <dest_image>
"""

import argparse
import sys
import pathlib
import sunpy.map
import astropy.units as u
import convert

def fits_to_jp2(fits_file_name):
    """
    Uses something to convert fits files to jp2 images
    """
    pass

def verify_file_exists(file_name):
    path = pathlib.Path(file_name)
    if not path.is_file():
        sys.exit("%s is not a file. Exiting" % file_name)

def parse_args():
    parser = argparse.ArgumentParser(description="Reprojects images from the coordinates of one jp2 image to another.")
    parser.add_argument('source', type=str, help="The path to the image file to be reprojected")
    parser.add_argument('dest', type=str, help="The path to the image containing the target coordinates")
    
    args = parser.parse_args()

    # These will exit the program if either source or destination don't exist.
    verify_file_exists(args.source)
    verify_file_exists(args.dest)

    return args

def reproject_image(source_image: str, dest_image: str):
    """
    Reprojects SOURCE_IMAGE to the coordinates stored in DEST_IMAGE.
    The result is being able to view SOURCE_IMAGE as see from the
    coordinates in DEST_IMAGE.
    """

    print("source map is %s" % source_image)
    source_map = sunpy.map.Map(source_image)
    print("dest map is %s" % dest_image)
    dest_map = sunpy.map.Map(dest_image)
    
    out_shape = (512, 512)
    source_map = source_map.resample(out_shape * u.pix)
    dest_map = dest_map.resample(out_shape * u.pix)

    print("Creating header with wavelength {}".format(source_map.wavelength))
    out_header = sunpy.map.make_fitswcs_header(
        out_shape,
        dest_map.reference_coordinate.replicate(rsun=source_map.reference_coordinate.rsun),
        scale=u.Quantity(dest_map.scale),
        instrument="EUVI",
        observatory="AIA Observer",
        wavelength=source_map.wavelength
    )

    reprojected_img = source_map.reproject_to(out_header)
    return reprojected_img

if __name__ == "__main__":
    args = parse_args()
    img = reproject_image(args.source, args.dest)
    img.save("reprojected.fits", overwrite=True)
    convert.fits_to_jp2("reprojected.fits")
