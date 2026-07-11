<?php
/**
 * Migration from a legacy NOAA airport dataset (no longer downloadable) into
 * the local station database that once powered
 * `Horde_Service_Weather_Metar::getLocations()` and `autocompleteLocation()`.
 *
 * Superseded by migration 2, which switches the schema to the shape used by
 * the datasets/airport-codes dataset.
 *
 * @method void beginDbTransaction()
 * @method void commitDbTransaction()
 * @method void rollbackDbTransaction()
 * @method \Horde_Db_Adapter_Base_TableDefinition createTable(string $name, array $options = array())
 * @method void dropTable(string $name)
 * @method array<int, string> tables()
 */
class HordeServiceWeatherAirports extends Horde_Db_Migration_Base
{
    protected $_handle;

    public function up()
    {
        // Check for the file before attempting to populate the table. Will
        // save us from having to rollback the transaction.
        if (!$this->_checkForMetarData()) {
            // Return instead of failing since this will always fail if
            // the data isn't present locally (the data source is defunct).
            // The '2' migration will populate the table with the new data
            // source.
            return;
        }
        $this->beginDbTransaction();
        $t = $this->createTable('horde_metar_airports', array('autoincrementKey' => array('id')));
        $t->column('id', 'integer');
        $t->column('block', 'integer');
        $t->column('station', 'integer');
        $t->column('icao', 'string', array('limit' => 4));
        $t->column('name', 'string', array('limit' => 80));
        $t->column('state', 'string', array('limit' => 2));
        $t->column('country', 'string', array('limit' => 50));
        $t->column('wmo', 'integer');
        $t->column('latitude', 'float');
        $t->column('longitude', 'float');
        $t->column('elevation', 'float');
        $t->column('x', 'float');
        $t->column('y', 'float');
        $t->column('z', 'float');
        $t->end();
        $this->_populateTable();
        $this->commitDbTransaction();
    }

    protected function _checkForMetarData()
    {
        // First see if we have a local copy in the same directory.
        $file_name = __DIR__ . PATH_SEPARATOR . 'nsf_cccc.txt';
        if (file_exists($file_name)) {
            $this->_handle = @fopen($file_name, 'rb');
        }
        if (empty($this->_handle)) {
             $this->announce('ERROR: Unable to populate METAR database (defunct data source, trying next migration.');
             return false;
        }

        return true;
    }

    protected function _populateTable()
    {
        $dataOrder = array('b' => 1, 's' => 2, 'i' => 0);
        $line = 0;
        $error = 0;
        $insert = 'INSERT INTO horde_metar_airports '
            . '(id, block, station, icao, name, state, country, wmo, latitude,'
            . 'longitude, elevation, x, y, z) '
            . 'VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        while ($data = fgetcsv($this->_handle, 1000, ';')) {
            // Check for valid data
            if ((sizeof($data) < 9) || !$this->_checkData($data, $dataOrder)) {
                    $this->announce('ERROR: Invalid data in file!');
                    $this->announce('  Line ' . ($line + 1) . ': ' . implode(';', $data));
                    $error++;
            } else {
                // Calculate latitude and longitude in decimal degrees.
                // The dataset ships them in ddd-mm[-ss]N|S|E|W notation.
                $parsed = array('latitude' => 0.0, 'longitude' => 0.0);
                foreach (array('latitude' => 7, 'longitude' => 8) as $latlon => $aId) {
                    preg_match('/^(\d{1,3})-(\d{1,2})(-(\d{1,2}))?([NSEW])$/', $data[$aId], $result);
                    $value = 0;
                    $factor = 1;
                    foreach ($result as $var) {
                        if ((strlen($var) > 0) && ctype_digit($var)) {
                            $value += $var / $factor;
                            $factor *= 60;
                        } elseif (ctype_alpha($var) && in_array($var, array('S', 'W'))) {
                            $value *= (-1);
                        }
                    }
                    $parsed[$latlon] = $value;
                }
                $latitude = $parsed['latitude'];
                $longitude = $parsed['longitude'];

                // Calculate the cartesian coordinates for latitude and longitude
                $theta = deg2rad($latitude);
                $phi   = deg2rad($longitude);

                // Radius of Earth = 6378.15
                $x = 6378.15 * cos($phi) * cos($theta);
                $y = 6378.15 * sin($phi) * cos($theta);
                $z = 6378.15 * sin($theta);

                // Check for elevation in data
                $elevation = is_numeric($data[11]) ? $data[11] : 0;

                // integers: convert "--" fields to null, empty fields to 0
                foreach (array($dataOrder['b'], $dataOrder['s'], 6) as $i) {
                    if (strpos($data[$i], '--') !== false) {
                        $data[$i] = 'null';
                    } elseif ($data[$i] == '') {
                        $data[$i] = 0;
                    }
                }

                try {
                    $this->_connection->insert($insert, array(
                        $line - $error, $data[1], $data[2], $data[0],
                        $data[3], $data[4], $data[5],
                        $data[6], round($latitude, 4),
                        round($longitude, 4), $elevation,
                        round($x, 4), round($y, 4),
                        round($z, 4))
                    );
                } catch (Horde_Db_Exception $e) {
                    $this->announce('ERROR: ' . $e->getMessage());
                    $this->rollbackDbTransaction();
                    return;
                }
            }
            $line++;
        }
        $this->announce('Added ' . ($line - $error) . ' airport identifiers to the database.');
    }

    protected function _checkData($data, array $dataOrder = array('b' => 1, 's' => 2, 'i' => 0))
    {
        $return = true;
        foreach ($dataOrder as $type => $idx) {
            $len = 0;
            $func = 'ctype_digit';
            switch ($type) {
            case 'b':
                $len  = 2;
                $func = 'ctype_digit';
                break;
            case 's':
                $len  = 3;
                $func = 'ctype_digit';
                break;
            case 'i':
                $len  = 4;
                $func = 'ctype_alnum';
                break;
            default:
                // Keep the safe defaults declared above; the switch is a
                // whitelist and unknown types simply produce no match.
                continue 2;
            }
            if ((strlen($data[$idx]) != $len) ||
                (!$func($data[$idx]) && ($data[$idx] != str_repeat('-', $len)))) {
                $return = false;
                break;
            }
        }
        return $return;
    }

    public function down()
    {
        $tableList = $this->tables();
        if (in_array('horde_metar_airports', $tableList)) {
            $this->dropTable('horde_metar_airports');
        }
    }

    /**
     * DB connection injected by the migration base class. Declared here so
     * static analysis can see it; the parent uses a dynamic property.
     *
     * @var \Horde_Db_Adapter_Base
     */
    protected $_connection;
}
