<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Statistics Class definition
 *
 * @category Database
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project/
 */

class Database_Statistics {

    private $_dbConnection;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct() {
        include_once HV_ROOT_DIR.'/../src/Database/DbConnection.php';
        $this->_dbConnection = new Database_DbConnection();
    }

    /**
     * Add a new entry to the `statistics` table
     *
     * param $action string The API action to log
     *
     * @return boolean
     */
    public function log($action) {
        $sql = sprintf(
                  "INSERT INTO statistics "
                . "SET "
                .     "id "        . " = NULL, "
                .     "timestamp " . " = NULL, "
                .     "action "    . " = '%s';",
                $this->_dbConnection->link->real_escape_string($action)
               );
        try {
            $result = $this->_dbConnection->query($sql);
        }
        catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Get latest usage statistics as JSON
     *
     * @param  string  Time resolution
     *
     * @return str  JSON
     */
    public function getUsageStatistics($resolution) {
        require_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';

        // Determine time intervals to query
        $interval = $this->_getQueryIntervals($resolution);

        // Array to keep track of counts for each action
        $counts = array(
            "buildMovie"           		=> array(),
            "getClosestData"       		=> array(),
            "getClosestImage"      		=> array(),
            "getJPX"               		=> array(),
            "getJPXClosestToMidPoint" 	=> array(),
            "takeScreenshot"       		=> array(),
            "uploadMovieToYouTube" 		=> array(),
            "embed"                		=> array()
        );

        // Summary array
        $summary = array(
            "buildMovie"           		=> 0,
            "getClosestData"       		=> 0,
            "getClosestImage"      		=> 0,
            "getJPX"               		=> 0,
            "getJPXClosestToMidPoint"   => 0,
            "takeScreenshot"       		=> 0,
            "uploadMovieToYouTube" 		=> 0,
            "embed"                		=> 0
        );

        // Format to use for displaying dates
        $dateFormat = $this->_getDateFormat($resolution);

        // Start date
        $date = $interval['startDate'];

        // Query each time interval
        for ($i = 0; $i < $interval["numSteps"]; $i++) {

            // Format date for array index
            $dateIndex = $date->format($dateFormat);

            // MySQL-formatted date string
            $dateStart = toMySQLDateString($date);

            // Move to end date for the current interval
            $date->add($interval['timestep']);

            // Fill with zeros to begin with
            foreach ($counts as $action => $arr) {
                array_push($counts[$action], array($dateIndex => 0));
            }
            $dateEnd = toMySQLDateString($date);

            $sql = sprintf(
                      "SELECT action, COUNT(id) AS count "
                    . "FROM statistics "
                    . "WHERE "
                    .     "timestamp BETWEEN '%s' AND '%s' "
                    . "GROUP BY action;",
                    $this->_dbConnection->link->real_escape_string($dateStart),
                    $this->_dbConnection->link->real_escape_string($dateEnd)
                   );
            try {
                $result = $this->_dbConnection->query($sql);
            }
            catch (Exception $e) {
                return false;
            }

            // Append counts for each API action during that interval
            // to the appropriate array
            while ($count = $result->fetch_array(MYSQLI_ASSOC)) {
                $num = (int)$count['count'];

                $counts[$count['action']][$i][$dateIndex] = $num;
                $summary[$count['action']] += $num;
            }
        }

        // Include summary info
        $counts['summary'] = $summary;

        return json_encode($counts);
    }

    /**
     * Return date format string for the specified time resolution
     *
     * @param  string  $resolution  Time resolution string
     *
     * @return string  Date format string
     */
    public function getDataCoverageTimeline($resolution, $endDate, $interval,
        $stepSize, $steps) {

        require_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';

        $sql = 'SELECT id, name, description FROM datasources ORDER BY description';
        $result = $this->_dbConnection->query($sql);

        $output = array();

        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $sourceId = $row['id'];

            $output['sourceId'.$sourceId] = new stdClass;
            $output['sourceId'.$sourceId]->sourceId = $sourceId;
            $output['sourceId'.$sourceId]->label = $row['description'];
            $output['sourceId'.$sourceId]->data = array();
        }

        // Format to use for displaying dates
        switch($resolution) {
        case "5m":
        case "15m":
        case "30m":
            $dateFormat = "Y-m-d H:i";
            break;
        case "1h":
            $dateFormat = "Y-m-d H:i";
            break;
        case "1D":
            $dateFormat = "Y-m-d";
            break;
        case "14D":
        case "1W":
            $dateFormat = "Y-m-d";
            break;
        case "30D":
        case "1M":
        case "3M":
        case "6M":
            $dateFormat = "M Y";
            break;
        case "1Y":
            $dateFormat = "Y";
            break;
        default:
            $dateFormat = "Y-m-d H:i e";
        }


        // Start date
        $date = $endDate->sub($interval);

        // Query each time interval
        for ($i = 0; $i < $steps; $i++) {
            $dateIndex = $date->format($dateFormat); // Format date for array index
            $dateStart = toMySQLDateString($date);   // MySQL-formatted date string

            // Move to end date for the current interval
            $date->add($stepSize);

            // Fill with zeros to begin with
            foreach ($output as $sourceId => $arr) {
                array_push($output[$sourceId]->data, array($dateIndex => 0));
            }
            $dateEnd = toMySQLDateString($date);

            $sql = "SELECT sourceId, SUM(count) as count FROM data_coverage_30_min " .
                   "WHERE date BETWEEN '$dateStart' AND '$dateEnd' GROUP BY sourceId;";
            //echo "\n<br />";

            $result = $this->_dbConnection->query($sql);

            // And append counts for each sourceId during that interval to the relevant array
            while ($count = $result->fetch_array(MYSQLI_ASSOC)) {
                $num = (int) $count['count'];
                $output['sourceId'.$count['sourceId']]->data[$i][$dateIndex] = $num;
            }
        }

        return json_encode($output);
    }

    /**
     * Gets latest datasource coverage and return as JSON
     */
    public function getDataCoverage($layers, $resolution, $startDate, $endDate) {

        require_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';
		
		$distance = $endDate->getTimestamp() - $startDate->getTimestamp();
		$interval = new DateInterval('PT'.$distance.'S');
		
		$startDate->modify('-'.$distance.' seconds');
		$endDate->modify('+'.$distance.' seconds');
		
		$dateStart = toMySQLDateString($startDate);
		$dateEnd = toMySQLDateString($endDate);
		
		$startTimestamp = $startDate->getTimestamp();
		$endTimestamp = $endDate->getTimestamp();
		
		$dateStartISO = str_replace("Z", "", toISOString($startDate));
		$dateEndISO = str_replace("Z", "", toISOString($endDate));
		
        $sources = array();
		$events = array();
			
		if(!$layers){
			return json_encode(array());
		}
		
		$layersArray = array();
		$layersKeys = array();
		$layersCount = 0;
		foreach($layers->toArray() as $layer){
			$sourceId = $layer['sourceId'];
			
            $sources[$layersCount] = new stdClass;
            $sources[$layersCount]->sourceId = $sourceId;
            $sources[$layersCount]->name = (isset($layer['uiLabels'][0]['name']) ? $layer['uiLabels'][0]['name'] : '').' '
            								.(isset($layer['uiLabels'][1]['name']) ? $layer['uiLabels'][1]['name'] : '').' '
            								.(isset($layer['uiLabels'][2]['name']) ? $layer['uiLabels'][2]['name'] : '').' '
            								.(isset($layer['uiLabels'][3]['name']) ? $layer['uiLabels'][3]['name'] : '').' '
            								.(isset($layer['uiLabels'][4]['name']) ? $layer['uiLabels'][4]['name'] : '');
            $sources[$layersCount]->data = array();
            
	        //$sources[$layersCount]->data[] = array($startDate->getTimestamp()*1000, null);
            
            $layersArray[] = $sourceId;
            $layersKeys[$sourceId] = $layersCount;
            $layersCount++;
        }
		
		$layersString = implode(' OR sourceId = ', $layersArray);
		
		switch ($resolution) {
	        case 'm':
	            $sql = 'SELECT date AS time,
				       COUNT(*) AS count,
				       sourceId
				FROM data
				WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY sourceId, time
				ORDER BY time;';
				
	            break;
	        case '5m':
	            $sql = 'SELECT 
	            		FROM_UNIXTIME(floor((UNIX_TIMESTAMP(date) / 300)) * 300) as time,
						COUNT(*) AS count,
						sourceId
				FROM data
				WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY sourceId, time
				ORDER BY time;';
				
				$beginInterval = new DateTime();
				$endInterval = new DateTime();
				$beginInterval->setTimestamp(floor($startTimestamp / 300) * 300);
				$endInterval->setTimestamp(floor($endTimestamp / 300) * 300);
				
				$interval = DateInterval::createFromDateString('5 minutes');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;
	        case '15m':
	            $sql = 'SELECT 
	            		FROM_UNIXTIME(floor((UNIX_TIMESTAMP(date) / 900)) * 900) as time,
						COUNT(*) AS count,
						sourceId
				FROM data
				WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY sourceId, time
				ORDER BY time;';
				
				$beginInterval = new DateTime();
				$endInterval = new DateTime();
				$beginInterval->setTimestamp(floor($startTimestamp / 900) * 900);
				$endInterval->setTimestamp(floor($endTimestamp / 900) * 900);
				
				$interval = DateInterval::createFromDateString('15 minutes');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;
	        case '30m':
	            $sql = 'SELECT 
	            		FROM_UNIXTIME(floor((UNIX_TIMESTAMP(date) / 1800)) * 1800) as time,
						COUNT(*) AS count,
						sourceId
				FROM data
				WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY sourceId, time
				ORDER BY time;';
				
				$beginInterval = new DateTime();
				$endInterval = new DateTime();
				$beginInterval->setTimestamp(floor($startTimestamp / 1800) * 1800);
				$endInterval->setTimestamp(floor($endTimestamp / 1800) * 1800);
				
				$interval = DateInterval::createFromDateString('30 minutes');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;         
	        case 'h':
	            $sql = 'SELECT DATE_FORMAT(date, "%Y-%m-%d %H:00:00") AS time,
				       COUNT(*) AS count,
				       sourceId
				FROM data
				WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY sourceId, time
				ORDER BY time;';
				
				$beginInterval = new DateTime(date('Y-m-d H:00:00', $startTimestamp));
				$endInterval = new DateTime(date('Y-m-d H:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 hour');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;
	        case 'D':
	        	$sql = 'SELECT DATE(date) AS time,
				       SUM(count) AS count,
				       sourceId
				FROM data_coverage_30_min
				WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY sourceId, DATE(time)
				ORDER BY DATE(time);';
				
				$beginInterval = new DateTime(date('Y-m-d 00:00:00', $startTimestamp));
				$endInterval = new DateTime(date('Y-m-d 00:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 day');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;
	        case 'W':
	        	$weekTimestamp = 7 * 24 * 60 * 60;
	            $sql = 'SELECT 
	            		FROM_UNIXTIME(floor((UNIX_TIMESTAMP(date) / '.$weekTimestamp.')) * '.$weekTimestamp.') as time,
						COUNT(*) AS count,
						sourceId
				FROM data_coverage_30_min
				WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY sourceId, time
				ORDER BY time;';
				
				$beginInterval = new DateTime();
				$endInterval = new DateTime();
				$beginInterval->setTimestamp(floor($startTimestamp / $weekTimestamp) * $weekTimestamp);
				$endInterval->setTimestamp(floor($endTimestamp / $weekTimestamp) * $weekTimestamp);
				
				$interval = DateInterval::createFromDateString('1 week');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;     
	        case 'M':
	        	$sql = 'SELECT DATE(DATE_FORMAT(date, "%Y-%m-01")) AS time,
				       SUM(count) AS count,
				       sourceId
				FROM data_coverage_30_min
				WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY sourceId, DATE(DATE_FORMAT(date, "%Y-%m-01"))
				ORDER BY DATE(DATE_FORMAT(date, "%Y-%m-01"));';
				
				$beginInterval = new DateTime(date('Y-m-01 00:00:00', $startTimestamp));
				$endInterval = new DateTime(date('Y-m-01 00:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 month');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;
	        case 'Y':
	            $sql = 'SELECT DATE(DATE_FORMAT(date, "%Y-01-01")) AS time,
				       SUM(count) AS count,
				       sourceId
				FROM data_coverage_30_min
				WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY sourceId, DATE(DATE_FORMAT(date, "%Y-01-01"))
				ORDER BY DATE(DATE_FORMAT(date, "%Y-01-01"));';
				
				$beginInterval = new DateTime(date('Y-01-01 00:00:00', $startTimestamp));
				$endInterval = new DateTime(date('Y-01-01 00:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 year');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;
	        default:
	            $msg = 'Invalid resolution specified. Valid options include: ' . implode(', ', $validRes);
	            throw new Exception($msg, 25);
	    }
		
		//build 0 data array
		if($resolution != 'm'){
			$emptyData = array();
			foreach ( $period as $dt ){
				$emptyData[ $dt->getTimestamp() ] = 0;
			}
		}
		
		//Procceed SQL Data
		$result = $this->_dbConnection->query($sql);
		$dbData = array();
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $num = (int) $row['count'];
            $sourceId = $row['sourceId'];
            $key = $layersKeys[$sourceId];
            if($resolution == 'm'){
	            $sources[$key]->data[] = array( (strtotime($row['time'])* 1000) + $key , $key+1);
            }else{
	            $dbData[$key][strtotime($row['time'])] = $num;
            }
        }
        
        //Fill 0 values rows
        if($resolution != 'm'){
	        foreach($layersKeys as $sourceId=>$key){
		        foreach($emptyData as $timestamp=>$count){
			        if(isset($dbData[$key]) && isset($dbData[$key][ $timestamp ])){
				        $count = $dbData[$key][ $timestamp ];
			        }
			        $sources[$key]->data[] = array($timestamp*1000, $count);
		        }
	        }
        }
        //foreach($sources as $sourceId=>$row){
	    //    $sources[$sourceId]->data[] = array($endDate->getTimestamp()*1000, null);
        //}
        
        return json_encode($sources);
		
    }

    /**
     * Gets latest datasource coverage and return as JSON
     */
    public function getDataCoverageEvents($events, $resolution, $startDate, $endDate) {

        require_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';
		
		$distance = $endDate->getTimestamp() - $startDate->getTimestamp();
		$interval = new DateInterval('PT'.$distance.'S');
		
		$startDate->modify('-'.$distance.' seconds');
		$endDate->modify('+'.$distance.' seconds');
		
		$dateStart = toMySQLDateString($startDate);
		$dateEnd = toMySQLDateString($endDate);
		
		$startTimestamp = $startDate->getTimestamp();
		$endTimestamp = $endDate->getTimestamp();
		
		$dateStartISO = str_replace("Z", "", toISOString($startDate));
		$dateEndISO = str_replace("Z", "", toISOString($endDate));
		
        $sources = array();
			
		if(!$events){
			return json_encode(array());
		}
		
		$eventTypes = array();
		$layersString = '';
		foreach($events->toArray() as $layer){
			if(!empty($layersString)){
				$layersString .= ' OR ';
			}
			
			if(!isset($eventTypes[$layer['event_type']])){
				$layersString .= 'event_type = "'.$layer['event_type'].'"';
				$eventTypes[$layer['event_type']] = 1;
			}
        }
        
		
		switch ($resolution) {
	        case 'm':
	            $sql = 'SELECT 
	            		*
				FROM events
				WHERE ('.$layersString.') AND (event_endtime >= "'.$dateStart.'" AND event_starttime <= "'.$dateEnd.'")
				ORDER BY event_starttime;';
				
				$beginInterval = new DateTime();
				$endInterval = new DateTime();
				$beginInterval->setTimestamp($startTimestamp);
				$endInterval->setTimestamp($endTimestamp);
				
	            break;
	        case '5m':
	            $sql = 'SELECT 
	            		*
				FROM events
				WHERE ('.$layersString.') AND (event_endtime >= "'.$dateStart.'" AND event_starttime <= "'.$dateEnd.'")
				ORDER BY event_starttime;';
				
				$beginInterval = new DateTime();
				$endInterval = new DateTime();
				$beginInterval->setTimestamp(floor($startTimestamp / 300) * 300);
				$endInterval->setTimestamp(floor($endTimestamp / 300) * 300);
				
				$interval = DateInterval::createFromDateString('5 minutes');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				$periodSeconds = 300000;
				
	            break;
	        case '15m':
	            $sql = 'SELECT 
	            		*
				FROM events
				WHERE ('.$layersString.') AND (event_endtime >= "'.$dateStart.'" AND event_starttime <= "'.$dateEnd.'")
				ORDER BY event_starttime;';
				
				$beginInterval = new DateTime();
				$endInterval = new DateTime();
				$beginInterval->setTimestamp(floor($startTimestamp / 900) * 900);
				$endInterval->setTimestamp(floor($endTimestamp / 900) * 900);
				
				$interval = DateInterval::createFromDateString('15 minutes');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				$periodSeconds = 900000;
				
	            break;
	        case '30m':
	            $sql = 'SELECT date AS time,
				       count,
				       event_type
				FROM events_coverage_30_min
				WHERE ('.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY event_type, time
				ORDER BY time;';
	            /*$sql = 'SELECT 
	            		event_endtime, 
	            		event_starttime,
				       event_type,
				       frm_name
				FROM events
				WHERE ('.$layersString.') AND (event_endtime >= "'.$dateStart.'" AND event_starttime <= "'.$dateEnd.'")
				ORDER BY event_starttime;';*/
				
				$beginInterval = new DateTime();
				$endInterval = new DateTime();
				$beginInterval->setTimestamp(floor($startTimestamp / 1800) * 1800);
				$endInterval->setTimestamp(floor($endTimestamp / 1800) * 1800);
				
				$interval = DateInterval::createFromDateString('30 minutes');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				$periodSeconds = 1800000;
				
	            break;         
	        case 'h':
	            $sql = 'SELECT FROM_UNIXTIME(floor((UNIX_TIMESTAMP(date) / 3600)) * 3600) AS time,
				       count,
				       event_type
				FROM events_coverage_30_min
				WHERE ('.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY event_type, time
				ORDER BY time;';
	            /*$sql = 'SELECT 
	            		event_endtime, 
	            		event_starttime,
						event_type,
						frm_name
				FROM events
				WHERE ('.$layersString.') AND (event_endtime >= "'.$dateStart.'" AND event_starttime <= "'.$dateEnd.'")
				ORDER BY event_starttime;';*/
				
				$beginInterval = new DateTime(date('Y-m-d H:00:00', $startTimestamp));
				$endInterval = new DateTime(date('Y-m-d H:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 hour');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				$periodSeconds = 3600000;
				
	            break;
	        case 'D':
	        	$sql = 'SELECT DATE(date) AS time,
				       SUM(count) AS count,
				       event_type
				FROM events_coverage_30_min
				WHERE ('.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY event_type, DATE(time)
				ORDER BY DATE(time);';
				
				$beginInterval = new DateTime(date('Y-m-d 00:00:00', $startTimestamp));
				$endInterval = new DateTime(date('Y-m-d 00:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 day');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;
	        case 'W':
	        	$weekTimestamp = 7 * 24 * 60 * 60;
	            $sql = 'SELECT 
	            		FROM_UNIXTIME(floor((UNIX_TIMESTAMP(date) / '.$weekTimestamp.')) * '.$weekTimestamp.') as time,
						COUNT(*) AS count,
				       event_type
				FROM events_coverage_30_min
				WHERE ('.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY event_type, time
				ORDER BY time;';
				
				$beginInterval = new DateTime();
				$endInterval = new DateTime();
				$beginInterval->setTimestamp(floor($startTimestamp / $weekTimestamp) * $weekTimestamp);
				$endInterval->setTimestamp(floor($endTimestamp / $weekTimestamp) * $weekTimestamp);
				
				$interval = DateInterval::createFromDateString('1 week');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;     
	        case 'M':
	        	$sql = 'SELECT DATE(DATE_FORMAT(date, "%Y-%m-01")) AS time,
				       SUM(count) AS count,
				       event_type
				FROM events_coverage_30_min
				WHERE ('.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY event_type, DATE(DATE_FORMAT(date, "%Y-%m-01"))
				ORDER BY DATE(DATE_FORMAT(date, "%Y-%m-01"));';
				
				$beginInterval = new DateTime(date('Y-m-01 00:00:00', $startTimestamp));
				$endInterval = new DateTime(date('Y-m-01 00:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 month');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;
	        case 'Y':
	            $sql = 'SELECT DATE(DATE_FORMAT(date, "%Y-01-01")) AS time,
				       SUM(count) AS count,
				       event_type
				FROM events_coverage_30_min
				WHERE ('.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY event_type, DATE(DATE_FORMAT(date, "%Y-01-01"))
				ORDER BY DATE(DATE_FORMAT(date, "%Y-01-01"));';
				
				$beginInterval = new DateTime(date('Y-01-01 00:00:00', $startTimestamp));
				$endInterval = new DateTime(date('Y-01-01 00:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 year');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;
	        default:
	            $msg = 'Invalid resolution specified. Valid options include: ' . implode(', ', $validRes);
	            throw new Exception($msg, 25);
	    }
		
		//build 0 data array
		if($resolution != 'm'){
			$emptyData = array();
			foreach ( $period as $dt ){
				$emptyData[ ($dt->getTimestamp() * 1000) ] = 0;
			}
		}
		
		//Procceed SQL Data
		$result = $this->_dbConnection->query($sql);
		$dbData = array();
		$eventsKeys = array();
		$i = 1;
		$uniqueIds = array();
		$j = 0;
		
		$eventsKeys = array(
			'AR' => 0,
			'CE' => 1,
			'CME' => 2,
			'CD' => 3,
			'CH' => 4,
			'CW' => 5,
			'FI' => 6,
			'FE' => 7,
			'FA' => 8,
			'FL' => 9,
			'LP' => 10,
			'OS' => 11,
			'SS' => 12,
			'EF' => 13,
			'CJ' => 14,
			'PG' => 15,
			'OT' => 16,
			'NR' => 17,
			'SG' => 18,
			'SP' => 19,
			'CR' => 20,
			'CC' => 21,
			'ER' => 22,
			'TO' => 23,
			'HY' => 24,
			'BO' => 25,
			'EE' => 26,
			'PB' => 27,
			'PT' => 28,
			'UNK' => 29
		);
		
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			//Event Name
			$key = $row['event_type'];
			
				
			//Add event name to keys array and assign order
			//if(!isset($eventsKeys[$key])){
			//	$eventsKeys[$key] = count($eventsKeys);
			//}
			
			$eventKey = $eventsKeys[$key];

			if(!isset($sources[$eventKey]) ){
				$sources[$eventKey] = array(
					'data' => array(),
					'event_type' => $row['event_type'],
					'res' => $resolution
				);
			}
			if(!isset($dbData[$eventKey])){
				$dbData[$eventKey] = array();
			}
			
			//Build data array
			if($resolution == 'm'){
				$timeStart = (strtotime($row['event_starttime'])* 1000);
				$timeEnd = (strtotime($row['event_endtime'])* 1000);
				if(($startTimestamp * 1000) > $timeStart){
					$timeStart = ($beginInterval->getTimestamp() * 1000);
				}
				if(($endTimestamp * 1000) < $timeEnd){
					$timeEnd = ($endInterval->getTimestamp() * 1000);
				}
				
				if(!empty($row['frm_specificid']) && isset($uniqueIds[$row['frm_specificid']])){
					$id = $uniqueIds[$row['frm_specificid']];//if($id == 24){echo $id.' '.$row['frm_specificid'].' ';print_r($row);}
					if($sources[$eventKey]['data'][$id]['x'] > $timeStart){
						$sources[$eventKey]['data'][$id]['x'] = $timeStart;
					}
					if($sources[$eventKey]['data'][$id]['x2'] < $timeEnd){
						$sources[$eventKey]['data'][$id]['x2'] = $timeEnd;
					}
				}else{
					$sources[$eventKey]['data'][$j] = array(
						'x' => $timeStart,
						'x2' => $timeEnd,
						'y' => $j,//(($eventKey * 10) + $i),//$eventKey
						'kb_archivid' => $row['kb_archivid'],
						'hv_labels_formatted' => json_decode($row['hv_labels_formatted']),
						'event_type' => $row['event_type'],
						'frm_name' => $row['frm_name'],
						'frm_specificid' => $row['frm_specificid'],
						'event_peaktime' => $row['event_peaktime'],
						'event_starttime' => $row['event_starttime'],
						'event_endtime' => $row['event_endtime']
					);
					$uniqueIds[$row['frm_specificid']] = $j;
					$j++;
				}	
			}else if(
				$resolution == '5m' ||
				$resolution == '15m'
			){
				foreach($emptyData as $timestamp => $d){
					$start = (strtotime($row['event_starttime'])* 1000);
					$end = (strtotime($row['event_endtime'])* 1000);
					
					if(!isset($dbData[$eventKey][$timestamp])){
						$dbData[$eventKey][$timestamp] = 0;
					}
					
					if(
						$start <= ($timestamp + $periodSeconds) && 
						$end >= $timestamp
					){
						$dbData[$eventKey][$timestamp]++;
					}
				}
			}else{
				$timestamp = (strtotime($row['time'])* 1000);
				$dbData[$eventKey][$timestamp] = (int)$row['count'];
			}
			$i++;
		}

        //Fill 0 values rows
        if($resolution != 'm'){
	        foreach($dbData as $key=>$row){
		        foreach($emptyData as $timestamp=>$count){
			        if(isset($dbData[$key]) && isset($dbData[$key][ $timestamp ])){
				        $count = $dbData[$key][ $timestamp ];
			        }
			        $sources[$key]['data'][] = array($timestamp, (int)$count);
		        }
	        }
        }else{
	        ksort($sources);
	        $i = 0;
	        //foreach($sources as $k=>$series){
		    //    $sources[$k]['data'] = array();
		    //    foreach($series['data'] as $v){
			//        $v['y'] = $i;
			//        $sources[$k]['data'][] = $v;
			//        $i++;
		    //    }
	        //}
	        
			$levels = array();
	        foreach($sources as $k=>$series){
		        //loop over all the events
		        //$i = count($levels);
		        //$levels = array();
		        $data = array();
				foreach($series['data'] as $dk => $event){
				    //was this event placed in a level already?
				    $placed = false;
				    //loop through each level checking only the last event
				    foreach($levels as $row=>$events){
				        //we only need to check the last event if they are already sorted
				        $last = end($events);
				        //does the current event start after the end time of the last event in this level
				        if($event['x'] >= $last['x2']){
				            //add to this level and break out of the inner loop
				            $event['y'] = $row;
				            $levels[$row][] = $event;
				            $data[] = $event;
				            $placed = true;
				            break;
				        }
				    }
				    //if not placed in another level, add a new level
				    if(!$placed){
				        $levels[$i] = array($event);
				        $event['y'] = $i;
				        $data[] = $event;
				        $i++;
				    }
				}
				$sources[$k]['data'] = $data;
	        }
	        
	        //To avoid Highcharts error we need to create at least one empty row
	        if(count($sources) < 1){
		        foreach($eventTypes as $e=>$k){
			        $sources[] = array(
						'data' => array(
							array(($beginInterval->getTimestamp() * 1000),($beginInterval->getTimestamp() * 1000),0),
							array(($endInterval->getTimestamp() * 1000),($endInterval->getTimestamp() * 1000),0),
						),
						'event_type' => $e,
						'res' => $resolution
					);
		        }
	        }
	        
        }
        
        $sources = array_values($sources);
        return json_encode($sources);
		
    }

    /**
     * Update data source coverage data for the last 7 Days
     * (or specified time period).
     */
    public function updateDataCoverage($period=null, $t=0) {

        if ( gettype($period) == 'string' &&
             preg_match('/^([0-9]+)([mhDMY])$/', $period, $matches) === 1 ) {

            $magnitude   = $matches[1];
            $period_abbr = $matches[2];
        }
        else {
            $magnitude   =  7;
            $period_abbr = 'D';
        }

        switch ($period_abbr) {
        case 'm':
            $interval = 'INTERVAL '.$magnitude.' MINUTE';
            $eventsInterval = '-'.$magnitude.' minute';
            break;
        case 'h':
            $interval = 'INTERVAL '.$magnitude.' HOUR';
            $eventsInterval = '-'.$magnitude.' hour';
            break;
        case 'D':
            $interval = 'INTERVAL '.$magnitude.' DAY';
            $eventsInterval = '-'.$magnitude.' day';
            break;
        case 'M':
            $interval = 'INTERVAL '.$magnitude.' MONTH';
            $eventsInterval = '-'.$magnitude.' month';
            break;
        case 'Y':
            $interval = 'INTERVAL '.$magnitude.' YEAR';
            $eventsInterval = '-'.$magnitude.' year';
            break;
        default:
            $interval = 'INTERVAL 7 DAY';
            $eventsInterval = '-7 day';
        }
		
		// Update Image Data coverage
        $sql = 'REPLACE INTO ' .
                    'data_coverage_30_min ' .
                '(date, sourceId, count) ' .
                'SELECT ' .
                    'SQL_BIG_RESULT SQL_BUFFER_RESULT SQL_NO_CACHE ' .
                    'CONCAT( ' .
                        'DATE_FORMAT(date, "%Y-%m-%d %H:"), '    .
                        'LPAD((MINUTE(date) DIV 30)*30, 2, "0"), ' .
                        '":00") AS "bin", ' .
                    'sourceId, ' .
                    'COUNT(id) ' .
                'FROM ' .
                    'data ' .
                'WHERE ' .
                    'date >= DATE_SUB(NOW(),'.$interval.') ' .
                'GROUP BY ' .
                    'bin, ' .
                    'sourceId;';
        $result = $this->_dbConnection->query($sql);
        
		// Update Events Data coverage
		$endDate 	= new DateTime(date("Y-m-d H:00:00",time()));
		$startDate 	= new DateTime(date("Y-m-d 00:00:00",time()));
		$startDate 	= $startDate->modify($eventsInterval);
		
		while ($startDate <= $endDate) {
			$chankStartDate = $startDate;
			$startDateStr = $chankStartDate->format('Y-m-d H:i:s');
			$startDate = $startDate->modify('+30 MINUTE');  
			$endDateStr = $startDate->format('Y-m-d H:i:s');

			$sql = 'REPLACE INTO events_coverage_30_min (date, event_type, count)
				SELECT 
					SQL_BIG_RESULT SQL_BUFFER_RESULT SQL_NO_CACHE
						"'.$startDateStr.'" AS bin, 
						event_type,
			            COUNT(id)
			        FROM events
			        WHERE (event_endtime >= "'.$startDateStr.'" AND event_starttime <= "'.$endDateStr.'") 
			        GROUP BY bin, event_type;';

            $result = $this->_dbConnection->query($sql);      
		}
		
        $output = array(
            'result'     => $result,
            'interval'     => $interval
        );

        return json_encode($output);
    }

    /**
     * Determines date format to use for the x-axis of the requested resolution
     */
    private function _getDateFormat($resolution) {
        switch ($resolution) {
            case "hourly":
                return "ga";  // 4pm
                break;
            case "daily":
                return "D";   // Tues
                break;
            case "weekly":
                return "M j"; // Feb 3
                break;
            case "monthly":
                return "M y"; // Feb 09
                break;
            case "yearly":
                return "Y";   // 2009
                break;
        }
    }

    /**
     * Determine time inveral specification for statistics query
     *
     * @param  string  $resolution  Time resolution string
     *
     * @return array   Array specifying a time interval
     */
    private function _getQueryIntervals($resolution) {

        date_default_timezone_set('UTC');

        // Variables
        $date     = new DateTime();
        $timestep = null;
        $numSteps = null;

        // For hourly resolution, keep the hours value, otherwise set to zero
        $hour = ($resolution == "hourly") ? (int) $date->format("H") : 0;

        // Round end time to nearest hour or day to begin with (may round other units later)
        $date->setTime($hour, 0, 0);

        // Hourly
        if ($resolution == "hourly") {
            $timestep = new DateInterval("PT1H");
            $numSteps = 24;

            $date->add($timestep);

            // Subtract 24 hours
            $date->sub(new DateInterval("P1D"));
        }

        // Daily
        else if ($resolution == "daily") {
            $timestep = new DateInterval("P1D");
            $numSteps = 28;

            $date->add($timestep);

            // Subtract 4 weeks
            $date->sub(new DateInterval("P4W"));
        }

        // Weekly
        else if ($resolution == "weekly") {
            $timestep = new DateInterval("P1W");
            $numSteps = 26;

            $date->add(new DateInterval("P1D"));

            // Subtract 25 weeks
            $date->sub(new DateInterval("P25W"));
        }

        // Monthly
        else if ($resolution == "monthly") {
            $timestep = new DateInterval("P1M");
            $numSteps = 24;

            $date->modify('first day of next month');
            $date->sub(new DateInterval("P24M"));
        }

        // Yearly
        else if ($resolution == "yearly") {
            $timestep = new DateInterval("P1Y");
            $numSteps = 8;

            $year = (int) $date->format("Y");
            $date->setDate($year - $numSteps + 1, 1, 1);
        }

        // Array to store time intervals
        $intervals = array(
            "startDate" => $date,
            "timestep"  => $timestep,
            "numSteps"  => $numSteps
        );

        return $intervals;
    }
}
?>
