<?php
/**
 * This file contains the Horde_Service_Weather_Alerts class for abstracting
 * access to weather alerts from Wunderground.
 *
 * Copyright 2011-2015 Horde LLC (http://www.horde.org/)
 *
 * @author   Michael J Rubinsky <mrubinsk@horde.org>
 * @license  http://www.horde.org/licenses/bsd BSD
 * @category Horde
 * @package  Service_Weather
 */

/**
 * Horde_Service_Weather_Alerts_WeatherUnderground class
 *
 * @author   Michael J Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @package  Service_Weather
 */
 class Horde_Service_Weather_Alerts_WeatherUnderground extends Horde_Service_Weather_Alerts_Base
 {

    public function __construct($properties, Horde_Service_Weather_Base $weather)
    {
        parent::__construct($properties, $weather);
        $this->_parse();
    }

    protected function _parse()
    {
        $this->_parsedAlerts = array();
        foreach ($this->_properties as $alert) {
            $alert = array(
                'type' => (!empty($this->_typeMap[$alert->type]) ? $this->_typeMap[$alert->type] : ''),
                'desc' => (!empty($alert->description) ? $alert->description : (!empty($alert->wtype_meteo_name) ? $alert->wtype_meteo_name : '')),
                'date_text' => $alert->date, // Euro only returns this, not epoch, but it's in UTC.
                'date' => new Horde_Date($alert->date_epoch, 'UTC'),
                'expires_text' => $alert->expires,
                'expires' => new Horde_Date($alert->expires_epoch, 'UTC'),
                'tz' => $alert->tz_long, //@todo - needed??
                'body' => $alert->message,
                // @todo This is available here: http://www.nws.noaa.gov/os/vtec/
                // but probably not needed, since 'description' looks like it
                // contains a sort-of-mapping already.
                //'phenomena' => $alert->phenomena
                'significance_text' => (!empty($this->_significanceMap[$alert->significance]) ? $this->_significanceMap[$alert->significance] : ''),
                'significance' => $alert->significance,
            );
            $this->_parsedAlerts[] = $alert;
        }
    }

 }