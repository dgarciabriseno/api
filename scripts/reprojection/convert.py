from astropy.io import fits
import numpy as np
import glymur
import lxml.etree as ET
import os

def header_to_xml(header):
    """
    Converts fits metadata into an XML Tree that can be inserted into
    a JP2 file.
    
    header is the header as defined in astropy.io.fits
    """
    fits = ET.Element("fits")
    
    already_added = set()
    for key in header:
        # Some headers span multiple lines and get duplicated as keys
        # header.get will appropriately return all data, so if we see
        # a key again, we can assume it was already added to the xml tree.
        if (key in already_added):
            continue

        # Add to the set so we don't duplicate entries
        already_added.add(key)
        
        el = ET.Element(key)
        data = header.get(key)
        if type(data) == bool:
            data = "1" if data else "0"
        else:
            data = str(data)

        el.text = data
        fits.append(el)

    return fits

def generate_jp2_meta_xml(header):
    """
    Generates the JP2 XML box to be used in helioviewer JP2 images.
    
    header - fits header as provided by astropy.io.fits
    """
    fits_xml = header_to_xml(header)
    meta = ET.Element("meta")
    meta.append(fits_xml)
    # TODO add <helioviewer/> section
    tree = ET.ElementTree(meta)
    return tree

def get_jp2_file_name(fits_file):
    """
    Generates jp2 file name based on the given fits file
    (simply replaces .fits with .jp2
    """
    return fits_file.replace(".fits", ".jp2").replace(".fts", ".jp2")

def get_tmp_jp2_file_name(filename):
    return filename + ".tmp.jp2"

def gen_jp2(header, data, filename):
    """
    Generates a jp2 file given an astropy.io.fits header object and an np.uint8 array
    containing the image data.
    The resulting image is saved to filename
    """
    # Create an initial jp2 file with the given data
    tmpname = get_tmp_jp2_file_name(filename)
    jp2 = glymur.Jp2k(tmpname, data)

    # Append the XML data to the header information
    meta_boxes = jp2.box
    fits_box = glymur.jp2box.XMLBox(xml=generate_jp2_meta_xml(header))
    target_index = len(meta_boxes) - 1
    meta_boxes.insert(target_index, fits_box)

    # Rewrite the jp2 file with the xml data in the header
    jp2.wrap(filename, boxes=meta_boxes)

    # Remove the initial temporary file
    os.remove(tmpname)
    

def fits_to_jp2(fits_file):
    """
    Convert a fits file to a jp2 with the appropriate header info.
    """
    # Read fits data
    with fits.open(fits_file) as hdu:
        header = hdu[0].header
        data = hdu[0].data
    # Convert image data to a generic format
    img = np.uint8(data)

    # Create the new jp2 file.
    jp2filename = get_jp2_file_name(fits_file)
    return gen_jp2(header, img, jp2filename)

