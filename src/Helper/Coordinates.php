<?php
/**
 * Contains functions for retrieving coordinates from jp2 files
 * via python & sunpy
 * @author   Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

/**
 * Parses the result from get_heeq.py into an array
 * @return HEEQ Coordinates in an array of ['x' => x,'y' => y,'z' => z]
 */
function _extractHeeqCoordsFromResult($cmd_output) {
    try {
        $coordinates_without_parentheses = trim($cmd_output, "()");    // "number, number, number"
        $coordinates = explode(",", $coordinates_without_parentheses); // "[number, number, number]"
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
    $heeq_script = HV_ROOT_DIR . "/../management/positioning/get_heeq_client.py";
    $cmd = HV_PYTHON_PATH . " " . $heeq_script . " -j" . escapeshellcmd($jp2_file) . " 2>&1";
    $result = shell_exec($cmd);
    return _extractHeeqCoordsFromResult($result);
}
