import numpy as np
from matplotlib.colors import LinearSegmentedColormap

def plaintext(name, table):
    """
    Writes output in the format
    ```
    0 0 0
    1 1 1
    2 2 2
    ...
    255 255 255
    ```
    """
    with open(name, "w") as fp:
        for row in table:
            r, g, b, a = row
            fp.write("%d %d %d\n" % (r, g, b))

def javascript(name, table):
    """
    Writes output in the format:
    ```
    [[0,0,0], [1,1,1], ..., [255,255,255]]
    ```
    """
    with open(name, "w") as fp:
        fp.write("[")
        entries = []
        for row in table:
            r, g, b, a = row
            entries.append("[%d,%d,%d]" % (r, g, b))
        fp.write(", ".join(entries))
        fp.write("]")


def gen_rgb(cmap: LinearSegmentedColormap, name: str, output_fn = plaintext):
    """
    Creates a text-based colormap using the given colormap
    args:
        - cmap The color map to use to generate the color table
        - name Name of the color table file
    """
    table = np.fromfunction(lambda i, j: cmap(i) * 255, (256,1), dtype=int)
    table = table.reshape((256, 4))
    output_fn(name, table)

if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(
                    prog='Gen RGB',
                    description='Generates text-based RGB color tables for further processing')
    parser.add_argument('name', help="Name of the color table to be created")
    parser.add_argument('cm', help="Name of the color map variable in sunpy.visualization.colormaps.cm")
    parser.add_argument('--js', help="Create output as javascript array", action="store_true")
    args = parser.parse_args()
    import sunpy.visualization.colormaps.cm as cm
    cmap = cm.__dict__[args.cm]
    outputter = plaintext
    if (args.js):
        outputter = javascript
    gen_rgb(cmap, args.name, outputter)
