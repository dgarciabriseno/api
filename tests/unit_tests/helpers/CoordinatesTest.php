<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Helper/Coordinates.php';

final class CoordinatesTest extends TestCase
{
    public function testCoordinates_FromJp2File(): void
    {
        $jp2_file = __DIR__ . "/test.jp2";
        $expected_result = array('x' => 146789725.6055787, 'y' => -36054.23337454303, 'z' => -9255292.184451517);
        $result = Coordinates_FromJp2File($jp2_file);
        $this->assertEquals($expected_result, $result);
    }
}
