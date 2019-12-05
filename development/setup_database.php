<?php
	if(!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']))
		die("Not allowed");

	// These constants select what this script does. Modify them
	// before calling this script
	$ACTION_DELETE_EXISTING_TABLES = false;
	$ACTION_CREATE_TABLES = false;
	$ACTION_GENERATE_DATA = false;

	// Try to disable as much buffering as possible, to maximize the
	// chance of the browser showing the output incrementally while
	// the script is runinng. See
	// https://www.php.net/manual/en/function.flush.php#87807
	apache_setenv('no-gzip', 1);
	ini_set('zlib.output_compression', 0);
	ini_set('implicit_flush', 1);
	for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
	ob_implicit_flush(1);

	// Show any PHP errors in the browser
	ini_set('display_errors', true);
	error_reporting(E_ALL | E_NOTICE);

	// Disable time limit
	ini_set('max_execution_time', 0);

	require_once('../connect.php');

	$db = Connection();

	if ($ACTION_DELETE_EXISTING_TABLES) {
		$TABLES = [
			'flora',
			'flora_observaties',
			'sensors_health',
			'sensors_measurement',
			'sensors_message',
			'sensors_station',
			'slam_measurement',
		];
		foreach ($TABLES as $table)
			$db->query("DROP TABLE IF EXISTS `$table`");
		print("<p>Deleted existing tables</p>");
	}

	if ($ACTION_CREATE_TABLES) {
		$queries = file_get_contents('db.schema');
		if ($db->multi_query($queries) === false)
			die(__LINE__ . ": " . $db->error);
		// Iterate results for all executed queries, otherwise subsequent queries fail
		while($db->more_results()) {
			if ($db->next_result() === false)
				die(__LINE__ . ": " . $db->error);
			$db->use_result();
		}
		print("<p>Created tables</p>");
	}

	if ($ACTION_GENERATE_DATA) {
		$NUMBER_OF_STATIONS = 10;
		$MEASUREMENT_PERIOD = 15*60; // seconds
		$FIRST_MEASUREMENT = strtotime("-1 month");
		$LAST_MEASUREMENT = strtotime("now");
		$MAX_FIRMWARE_VERSION = 3;
		if (true) {
			// Amersfoort
			$POSITION_CENTER = [52.1557, 5.3889];
			$POSITION_RADIUS = 0.06; // degrees
		} else {
			// Bergen
			$POSITION_CENTER = [60.3692, 5.3493];
			$POSITION_RADIUS = 0.1; // degrees
		}

		$count = $NUMBER_OF_STATIONS * ($LAST_MEASUREMENT - $FIRST_MEASUREMENT) / $MEASUREMENT_PERIOD;
		print("<p>Generating around $count measurements... This might take some time.</p>");

		$measurement_query = $db->prepare(
		"INSERT INTO sensors_measurement SET
			station_id = ?,
			message_id = ?,
			timestamp = FROM_UNIXTIME(?),
			latitude = ?,
			longitude = ?,
			temperature = ?,
			humidity = ?,
			battery = ?,
			supply = ?,
			firmware_version = ?,
			lux = ?,
			pm2_5 = ?,
			pm10 = ?
		");
		if ($measurement_query === false)
			die(__LINE__ . ": " . $db->error);

		$station_query = $db->prepare(
		"INSERT INTO sensors_station SET
			id = ?,
			last_measurement = ?,
			last_timestamp = FROM_UNIXTIME(?)
		");
		if ($station_query === false)
			die(__LINE__ . ": " . $db->error);

		for ($station_id = 1; $station_id <= $NUMBER_OF_STATIONS; $station_id++) {
			$max_start_offset = ($LAST_MEASUREMENT - $FIRST_MEASUREMENT) / $NUMBER_OF_STATIONS * ($station_id - 1);
			$timestamp = $FIRST_MEASUREMENT + rand(0, $max_start_offset);
			$last_measurement_id = false;
			$last_timestamp = false;
			while ($timestamp <= $LAST_MEASUREMENT) {
				// TODO: Generate a fake TTN message JSON for sensors_message
				$message_id = 0;
				$latitude = $POSITION_CENTER[0] + rand(0, $POSITION_RADIUS * 1E6) / 1E6;
				$longitude = $POSITION_CENTER[1] + rand(0, $POSITION_RADIUS * 1E6) / 1E6;
				// TODO: Vary measurements?
				$temperature = 20;
				$humidity = 50;
				$battery = null;
				$supply = 3.3;
				$firmware_version = rand(0, $MAX_FIRMWARE_VERSION);
				if ($firmware_version == 0)
					$firmware_version = null;
				$lux = null;
				$pm2_5 = null;
				$pm10 = null;

				// Bind variables. Ignore the return
				// value, since that seems to always be
				// false (unlike documented). On errors,
				// a PHP error is raised.
				$measurement_query->bind_param(
					"iiiddddddiddd",
					$station_id,
					$message_id,
					$timestamp,
					$latitude,
					$longitude,
					$temperature,
					$humidity,
					$battery,
					$supply,
					$firmware_version,
					$lux,
					$pm2_5,
					$pm10
				);

				if ($measurement_query->execute() === false)
					die(__LINE__ . ": " . $measurement_query->error);

				$last_measurement_id = $db->insert_id;
				$last_timestamp = $timestamp;
				$timestamp += $MEASUREMENT_PERIOD;
			}

			if ($last_measurement_id) {
				$station_query->bind_param(
					"iii",
					$station_id,
					$last_measurement_id,
					$last_timestamp
				);
				if ($station_query->execute() === false)
					die(__LINE__ . ": " . $station_query->error);
			}

			print("<p>Generated $station_id out of $NUMBER_OF_STATIONS stations...</p>");
		}
		print("<p>Done generating data</p>");
	}
