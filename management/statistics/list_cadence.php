<?php declare(strict_types=1);
/**
 * This module is used to compute statistics about the image cadence for each
 * instrument.
 */

// Handle cli args (there are none, but all scripts must support -h and --help)
if ($argc > 1) {
    $name = $argv[0];
    echo "Usage:\n";
    echo "  php $name\n";
    echo "\n";
    echo "This command analyzes the image dates in the database and prints\n";
    echo "statistics about the time between images for each data source\n";
    exit(0);
}


// Include helioviewer configuration.
require_once __DIR__ . "/../config.php";
// Include database instance.
require_once HV_ROOT_DIR.'/../src/Database/DbConnection.php';
// Connect to the helioviewer database.
$db = new Database_DbConnection();

// Get all the data sources available
$query = $db->query(
    "SELECT id, name FROM datasources"
);
// Iterate over each data source
while ($source = $query->fetch_object()) {
    compute_statistics(intval($source->id), $source->name);
    echo "--------------------------------------------\n";
}

/**
 * Returns the given number of seconds in a more readable format.
 * Seconds are printed as days, hours, minutes, or seconds depending on the
 * number of seconds given.
 * @param int|float $seconds Number of seconds.
 */
function humanReadableSeconds(int|float $seconds): string {
    if ($seconds <= 60) {
        return ceil($seconds) . " seconds";
    } else if ($seconds <= 119) {
        // Since it's flooring values, any number under 2 minutes (120 seconds)
        // should come up as "1 minute ago" rather than "1 minutes ago"
        return "1 minute";
    } else if ($seconds <= 3600) {
        return floor($seconds / 60) . " minutes";
    } else if ($seconds <= 7199) {
        // Same as above, any number under 2 hours (7200 seconds)
        // should come up as "1 hour ago" rather than "1 hours ago"
        return "1 hour";
    } else if ($seconds <= 86400) {
        return floor($seconds / 3600) . " hours";
    } else if ($seconds <= 172799) {
        // Same as above, any number under 2 days (172800 seconds)
        // should come up as "1 day ago" rather than "1 days ago"
        return "1 day";
    } else {
        return floor($seconds / 86400) . " days";
    }
}

/**
 * Computes the statistics for the given data source and prints the result
 * to stdout.
 * @param int $source Data source id.
 * @param string $name Name of data source, for printing.
 */
function compute_statistics(int $source, string $name) {
    global $db;
    // Get the dates for all of our images for this source
    $query = $db->query(
        "SELECT date FROM data WHERE sourceId=$source ORDER BY date ASC"
    );

    // Store the number of rows to compute the mean.
    $num_rows = $query->num_rows;

    // If we have no data for this data source, then exit here
    if ($num_rows < 2) {
        echo "    - Not enough data for $name\n";
        return;
    }


    // Stores minimum time between images
    $min = null;
    // Stores maximum tim between images
    $max = null;
    // Stores average time between images
    $mean = 0;
    // Stores the sum of all the time deltas to compute the mean
    $sum = 0;

    $prevDate = null;
    while ($row = $query->fetch_object()) {
        $date = new DateTimeImmutable($row->date);
        // $prevDate will be null on the first iteration
        if (!is_null($prevDate)) {
            // Get difference between both dates
            $dt = $date->getTimestamp() - $prevDate->getTimestamp();
            // Compute min
            if (is_null($min) || ($dt < $min)) {
                $min = $dt;
            }
            // Compute max
            if (is_null($max) || ($dt > $max)) {
                $max = $dt;
            }
            // Add to the sum so we can compute the mean at the end
            $sum += $dt;
        }

        // Assign prevDate for the next iteration.
        $prevDate = $date;
    }

    // Compute the mean.
    // There are $num_rows-1 time deltas.
    $mean = $sum / ($num_rows-1);

    // Convert results to human readable numbers
    $min = humanReadableSeconds($min);
    $max = humanReadableSeconds($max);
    $mean = humanReadableSeconds($mean);

    // Print out the results
    echo "Results for source $source: $name\n";
    echo "   Minimum cadence: $min\n";
    echo "   Maximum cadence: $max\n";
    echo "      Mean cadence: $mean\n";
}
