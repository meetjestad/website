<?php
// This code make node IDs available by project in json format.
// Data is based on the content of the sensorsets.json file in the root of this project.
//
// Usage:
//   /data/ids/                  returns all nodes IDs of all projects
//   /data/ids/?project=all      same as above
//   /data/ids/?project=utrecht  returns all nodes IDs of the Utrecht project
//
// Remarks:
// - Also the project name, description and number of nodes is provided in the results.
// - All IDs are reported individually (not in ranges as in the sensorsets.json file).
// - Misspelled project names, in the URL query, will return no results.


$sensorsetsFile = '../../sensorsets.json';
$resultSet =[];

if (file_exists($sensorsetsFile)) {
    $sensor_sets = json_decode(file_get_contents($sensorsetsFile), true);
    $projects = array_keys($sensor_sets);
    if (isset($_GET['project']) && $_GET['project'] != 'all') {
        if (in_array($_GET['project'], $projects)) {
            $projects = [$_GET['project']];
        } else {
            $projects = [];
        }
    }

    foreach ($projects as $project) {
        $ids = getNodesFromString($sensor_sets[$project]['ids']);
        $projectData = [
            'name' => $project,
            'description' => $sensor_sets[$project]['description'],
            'ids' => $ids,
            'amount' => sizeof($ids)
        ];
        $resultSet['project'][$project] = $projectData;
    }
}

header('Content-type: application/json');
echo json_encode($resultSet);


/**
 * Convert string of IDs/ID-ranges into an array containing all node IDs
 *
 * returns array of IDs (integers)
 */
function getNodesFromString($idsString, $unique = true)
{
    $idList = [];
    $ids = explode(',', $idsString);
    foreach ($ids as $key => $id) {
        $id = trim($id);
        if (!empty($id)) {
            if (ctype_digit($id)) { // checks if all of the characters are numerical
                $idList[] = intval($id);
            } else { // $id is not a single value, but (probably) a range
                $pattern = '/^(\d+)-(\d+)$/';
                if (preg_match($pattern, $id, $matches)) {
                    foreach (range($matches[1], $matches[2]) as $nid) {
                        $idList[] = $nid;
                    }
                }
            }
        }
    }

    return $unique ? array_values(array_unique($idList, SORT_NUMERIC)) : $idList;
}
