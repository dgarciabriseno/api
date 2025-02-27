<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

include HV_ROOT_DIR . "/../src/Image/JPEG2000/HelioviewerJPXImage.php";

final class HelioviewerJPXImageTest extends TestCase
{
    const TEST_JPX_NAME = "test_SDO_AIA_304_F2021-06-01T00.01.00Z_T2021-06-01T00.01.30Z.jpx";
    const TEST_JSON_NAME = "test_SDO_AIA_304_F2021-06-01T00.01.00Z_T2021-06-01T00.01.30Z.json";
    const TEST_JPX_FILE = __DIR__ . "/test_files/" . self::TEST_JPX_NAME;
    const TEST_JSON_FILE = __DIR__ . "/test_files/" . self::TEST_JSON_NAME;
    const MOVIE_DIR = HV_JP2_DIR . '/movies/';

    /**
     * Test for JPX caching conditions.
     *
     * No New Frames test:
     * - Perform a JPX movie request on a known existing JPX file.
     * - The JPX class will check the database and see that there are no new frames
     * - Verify that a new JPX file is not created by checking file timestamp.
     */
    public function testCacheConditions_noNewFrames() {
        // Copy test collateral into movies directory
        $this->_setupTestFiles();
        // Store jpx file timestamp to verify it has not changed after performing
        // the JPX request.
        $original_timestamp = filemtime(self::MOVIE_DIR . self::TEST_JPX_NAME);
        // Wait one second to allow system timestamp to increment.
        sleep(1);
        // Create JPX instance that aligns with the test file.
        $jpx = new Image_JPEG2000_HelioviewerJPXImage(
                    13, // AIA 304 source
                    // Known time range for the test file.
                    "2021-06-01 00:01:00",
                    "2021-06-01 00:01:30",
                    60,
                    false,
                    self::TEST_JPX_NAME);
        // Verify that a new JPX was not generated by comparing new timestamp to
        // the original timestamp
        $new_timestamp = filemtime(self::MOVIE_DIR . self::TEST_JPX_NAME);
        $this->assertTrue($original_timestamp == $new_timestamp, "Expected JPX file to remain unchanged. Timestamp indicates file was modified.");

        // Remove test files.
        $this->_cleanupTestFiles();
    }

    /**
     * Test for JPX caching conditions.
     *
     * New Frames test:
     * - Perform a JPX movie request on a known existing JPX file.
     * - Use the same file name, but a different end-time to mimic new data being added.
     * - Verify that a new JPX file is created by verifying the file timestamp is more recent.
     */
    public function testCacheConditions_NewFrames() {
        // Copy test collateral into movies directory
        $this->_setupTestFiles();
        // Store jpx file timestamp to verify it is changed after the new JPX is
        // created.
        $original_timestamp = filemtime(self::MOVIE_DIR . self::TEST_JPX_NAME);
        // Wait one second to allow system timestamp to increment.
        sleep(1);
        // Create JPX instance that aligns with the test file.
        $jpx = new Image_JPEG2000_HelioviewerJPXImage(
                    13, // AIA 304 source
                    // Known time range for the test file.
                    "2021-06-01 00:01:00",
                    "2021-06-01 00:03:00",
                    60,
                    false,
                    self::TEST_JPX_NAME);
        // Verify that a new JPX was generated by comparing new timestamp to
        // the original timestamp
        $new_timestamp = filemtime(self::MOVIE_DIR . self::TEST_JPX_NAME);
        $this->assertTrue($original_timestamp < $new_timestamp, "Expected new JPX to be created, timestamp for jpx file on-disk remains unchanged.");

        // Remove test files.
        $this->_cleanupTestFiles();
    }

    private function _setupTestFiles() {
        if (!is_dir(self::MOVIE_DIR)) {
            mkdir(self::MOVIE_DIR);
        }
        copy(self::TEST_JPX_FILE, self::MOVIE_DIR . self::TEST_JPX_NAME);
        copy(self::TEST_JSON_FILE, self::MOVIE_DIR . self::TEST_JSON_NAME);
    }

    private function _cleanupTestFiles() {
        unlink(self::MOVIE_DIR . self::TEST_JPX_NAME);
        unlink(self::MOVIE_DIR . self::TEST_JSON_NAME);
    }
}
