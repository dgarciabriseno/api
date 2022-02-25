import sys
import os
import pandas
import glymur
import numpy as np
from utils.pandas_db import get_db_connection
from pathlib import Path
from time import time

def GenerateImageCorrectionFunction(db_connection, img_file_path, sourceId):
    """
    This is the main function for this module.
    It will:
    - query the database for images of [dataSource]
    - Get the average brightness for a selected image each day
    - Create a function of the curve that matches the given brightness data
    - Return the curve which can be used to create a brightness scaling factor
    for images.

    args:
        - db_connection is the sqlalchemy database connection
        - img_file_path is the path to all image files on disk.
    """
    # Query images so that a table with 1 image per day is returned
    dataset = get_one_image_per_day_dataset(db_connection, sourceId)
    # Iterate over each database row for the image file
    calculate_average_pixel_values(dataset, img_file_path)
    # TODO: Using the data stored, generate a curve function that lines up with the data
    # Note: best plotting mechanism I've found is: .plot(rot=70, figsize=(4, 4)).get_figure().savefig("plot2.pdf")
    return dataset

def calculate_average_pixel_values(dataset, file_path):
    # Note: For some reason os.path.join is not working with file_path and row.filename.
    #       So I am using the regular + instead. This is fine as long as it's running
    #       on a unix system...
    dataset['color'] = dataset.apply(lambda row: compute_average_color(file_path + row.filename), axis=1)

def compute_average_color(filename):
    """
    Reads a JP2K image and computes the average pixel value
    """
    print("Checking file {} at time {}".format(filename, time()))
    jp2 = glymur.Jp2k(filename)
    loaded_image = jp2[:]
    return np.sum(loaded_image)

def get_one_image_per_day_dataset(db_connection, sourceId):
    return pandas.read_sql("""
    SELECT CONCAT(filepath, '/', filename) as filename,
           DATE(date) as dt
    FROM data
    WHERE sourceId = {}
    GROUP BY dt
    """.format(sourceId), db_connection)

def print_usage():
    cmd = os.path.basename(sys.argv[0])
    print("Usage: {} path_to_jp2_images sourceId".format(cmd))

def arg_check():
    """
    Quick arg check, no need for argparse here
    """
    if len(sys.argv) < 3:
        print("Error: Bad Usage")
        print_usage()
        sys.exit()

def main():
    arg_check()
    connection = get_db_connection()
    img_file_path = sys.argv[1]
    sourceId = sys.argv[2]
    return GenerateImageCorrectionFunction(connection, img_file_path, sourceId)

def interactive_main():
    connection = get_db_connection()
    image_path = input("Path to jp2 images: ")
    sourceId = input("Source ID to query: ")
    return GenerateImageCorrectionFunction(connection, image_path, sourceId)


if __name__ == "__main__":
    main()
