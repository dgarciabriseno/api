<?php
/**
 * Image_ImageType_SJIImage class definition
 *
 * @category Image
 * @package  Helioviewer
 * @author   Daniel Garcia Briseno <daniel.garciabriseno@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
require_once HV_ROOT_DIR.'/../src/Image/HelioviewerImage.php';

class Image_ImageType_SJIImage extends Image_HelioviewerImage {
    /**
     * Creates a new SJIImage
     *
     * @param string $jp2      Source JP2 image
     * @param string $filepath Location to output the file to
     * @param array  $roi      Top-left and bottom-right pixel coordinates on the image
     * @param array  $uiLabels Datasource label hierarchy
     * @param int    $offsetX  Offset of the sun center from the image center
     * @param int    $offsetY  Offset of the sun center from the iamge center
     * @param array  $options  Optional parameters
     *
     * @return void
     */
    public function __construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options) {
        $measurement = $uiLabels[2]['name'];

        $colorTable = HV_ROOT_DIR
                    . '/resources/images/color-tables/'
                    . 'SJI_'.$measurement.'.png';
        $this->setColorTable($colorTable);

        parent::__construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options);
    }

    /**
     * Gets a string that will be displayed in the image's watermark
     *
     * @return string watermark name
     */
    public function getWaterMarkName() {
        $measurement = $this->uiLabels[2]['name'];
        $watermark = 'IRIS SJI '.$measurement." Å\n";
        return $watermark;
    }
}
?>