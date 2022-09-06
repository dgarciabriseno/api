<?php
/**
 * Contains functions for retrieving coordinates from jp2 files
 * via python & sunpy
 * @author   Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

/**
 * Helper function to implement PHP 8's str_starts_with
 */
function _str_starts_with($haystack, $needle) {
    return strpos($haystack, $needle) === 0;
}

/**
 * Parses the output of get_heeq.py to extract the HEEQ Coords line
 * @param[in] cmd_output The output from get_heeq.py
 * @return The HEEQ line or empty string if not found
 */
function _getHeeqLine($cmd_output) {
    $lines = explode("\n", $cmd_output);
    foreach ($lines as $line) {
        if (_str_starts_with($line, "HEEQ Coords")) {
            return $line;
        }
    }
    return "";
}

/**
 * Parses the result from get_heeq.py into an array
 * @return HEEQ Coordinates in an array of ['x' => x,'y' => y,'z' => z]
 */
function _extractHeeqCoordsFromResult($cmd_output) {
    try {
        $coordinate_string = _getHeeqLine($cmd_output);               // "HEEQ Coordinates (km): (number, number, number)"
        $coordinate_array = trim(explode(':', $coordinate_string)[1]);    // "(number, number, number)
        $coordinates_without_parentheses = trim($coordinate_array, "()"); // "number, number, number"
        $coordinates = explode(",", $coordinates_without_parentheses);    // "[number, number, number]"
        return array (
            'x' => floatval($coordinates[0]),
            'y' => floatval($coordinates[1]),
            'z' => floatval($coordinates[2])
        );
    } catch (Exception $e) {
        throw new Exception("Failed to get coordinates from jp2 file: " . $e->getMessage(), 255);
    }
}

/**
 * Gets HEEQ coordinates of the observer found in the given jp2 file
 * @param[in] jp2_file Path to jp2 image
 * @return HEEQ Coordinates in an array of [x,y,z]
 */
function Coordinates_FromJp2File($jp2_file) {
    $heeq_script = HV_ROOT_DIR . "/../management/positioning/get_heeq.py";
    // Sunpy does a lot of work in the home directory, when running on a server
    // the home directory is not writeable, so override home to /tmp so sunpy
    // can create temporary files as needed.
    $cmd = "HOME=/tmp " . HV_PYTHON_PATH . " -i " . $heeq_script . " " . escapeshellcmd($jp2_file) . " 2>&1";
    $result = shell_exec($cmd);
    return _extractHeeqCoordsFromResult($result);
}
