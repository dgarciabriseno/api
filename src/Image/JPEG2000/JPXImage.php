<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * JPEG 2000 JPX Image Class Definition
 * Class for working with JPX images
 *
 * = 02/13/2010 =
 * MJ2 Creation has been removed since it is not currently being used.
 * To add support back in the future simply follow the same steps as for JPX
 * generation, but pass kdu_merge the additional sub-command:
 *
 *     -mj2_tracks P:0-@25
 *
 * @category Image
 * @package  Helioviewer
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
class Image_JPEG2000_JPXImage
{
    protected $frames;
    protected $linked;
    protected $outputFile;
    /**
     * Creates a JPXImage instance
     *
     * @param string $outputFile Location where JPX image should be stored
     *
     * @return void
     */
    public function __construct($outputFile)
    {
        $this->outputFile = $outputFile;
    }
    /**
     * Given a set of JP2 images, runs kdu_merge to build a single JPX image from them
     *
     * @param array  $frames   A list of JP2 filepaths
     * @param bool   $linked   If true, then a linked JPX file will be created
     *
     * @return void
     */
    protected function buildJPXImage($frames, $linked)
    {
        $cmd = HV_KDU_MERGE_BIN . " -s /dev/stdin";
        // Input JP2s
        $stdin = '-i ' . implode(',', $frames);
        // Virtual JPX
        if ($linked) {
            $stdin .= ' -links';
        }
        // Output JPX file
        $stdin .= ' -o ' . $this->outputFile;
        // Execute kdu_merge command
        $output = '';
        $return = $this->p2sc_execute($cmd, $stdin, $output);
        if ($return != 0) {
          $msg = sprintf("Error creating JPX file\n" .
                         "COMMAND:\n%s\nARGUMENTS:\n%s\nRETURN VALUE: %d\nOUTPUT:\n%s",
                         $cmd, $stdin, $return, $output);
          if (file_exists($this->outputFile)) {
              unlink($this->outputFile);
          }
          throw new Exception($msg, 14);
        }
    }
    private function p2sc_execute($cmd, $stdin, &$output) {
        $dspec = array(
          0 => array('pipe', 'r'),
          1 => array('pipe', 'w'),
          2 => array('pipe', 'w')
        );

        $proc = proc_open("$cmd 2>&1", $dspec, $pipes);
        if (is_resource($proc)) {
          fwrite($pipes[0], $stdin);
          fclose($pipes[0]);
          $out = stream_get_contents($pipes[1]);
          fclose($pipes[1]);
          fclose($pipes[2]);
          $output = $out;
          return proc_close($proc);
        } else
          return 1;
    }
    /**
     * Prints a JPX image to the screen
     *
     * @return void
     */
    public function displayImage()
    {
        ini_set('memory_limit', '2048M');
        if(file_exists($this->outputFile)){
            header('Content-Type: '  .image_type_to_mime_type(IMAGETYPE_JPX));
		    header('Content-Disposition: attachment; filename="'.basename($this->outputFile).'"');
		    header('Expires: 0');
		    header('Cache-Control: must-revalidate');
		    header('Pragma: public');
		    header("Content-Encoding: none");
		    header('Content-Length: ' . filesize($this->outputFile));
		    @readfile($this->outputFile) or die("");
        }else{
			$filename = basename($this->outputFile);
	        header("Content-Length: 0");
	        header("Content-Type: "   . image_type_to_mime_type(IMAGETYPE_JPX));
	        header("Content-Disposition: attachment; filename=\"$filename\"");
	
	        echo '';
        }
        
    }
}
?>
