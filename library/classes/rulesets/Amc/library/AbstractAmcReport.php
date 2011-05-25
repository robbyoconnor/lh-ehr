<?php
// Copyright (C) 2011 Ken Chapple <ken@mi-squared.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
require_once( 'AmcFilterIF.php' );

abstract class AbstractAmcReport implements RsReportIF
{
    protected $_amcPopulation;

    protected $_resultsArray = array();

    protected $_rowRule;
    protected $_ruleId;
    protected $_beginMeasurement;
    protected $_endMeasurement;

    public function __construct( array $rowRule, array $patientIdArray, $dateTarget )
    {
        // require all .php files in the report's sub-folder
        $className = get_class( $this );
        foreach ( glob( dirname(__FILE__)."/../reports/".$className."/*.php" ) as $filename ) {
            require_once( $filename );
        }
        // require common .php files
        foreach ( glob( dirname(__FILE__)."/../reports/common/*.php" ) as $filename ) {
            require_once( $filename );
        }
        // require clinical types
        foreach ( glob( dirname(__FILE__)."/../../../ClinicalTypes/*.php" ) as $filename ) {
            require_once( $filename );
        }

        $this->_amcPopulation = new AmcPopulation( $patientIdArray );
        $this->_rowRule = $rowRule;
        $this->_ruleId = isset( $rowRule['id'] ) ? $rowRule['id'] : '';
        // Parse measurement period, which is stored as array in $dateTarget ('dateBegin' and 'dateTarget').
        $this->_beginMeasurement = $dateTarget['dateBegin'];
        $this->_endMeasurement = $dateTarget['dateTarget'];
    }
    
    public abstract function createNumerator();
    public abstract function createDenominator();
        
    public function getResults() {
        return $this->_resultsArray;
    }

    public function execute()
    {
        $numerator = $this->createNumerator();
        if ( !$numerator instanceof AmcFilterIF ) {
            throw new Exception( "Numerator must be an instance of AmcFilterIF" );
        }
        
        $denominator = $this->createDenominator();
        if ( !$denominator instanceof AmcFilterIF ) {
            throw new Exception( "Denominator must be an instance of AmcFilterIF" );
        }
        
        $totalPatients = count( $this->_amcPopulation );
        $numeratorPatients = 0;
        $denominatorPatients = 0;
        foreach ( $this->_amcPopulation as $patient ) 
        {
            // If begin measurement is empty, then make the begin
            //  measurement the patient dob.
            $tempBeginMeasurement = "";
            if (empty($this->_beginMeasurement)) {
                $tempBeginMeasurement = $patient->dob;
            }
            else {
                $tempBeginMeasurement = $this->_beginMeasurement;
            }

            if ( !$denominator->test( $patient, $tempBeginMeasurement, $this->_endMeasurement ) ) {
                continue;
            }
            
            $denominatorPatients++;
            
            if ( !$numerator->test( $patient, $tempBeginMeasurement, $this->_endMeasurement ) ) {
                continue;
            }
            
            $numeratorPatients++;
        }
        
        $percentage = calculate_percentage( $denominatorPatients, 0, $numeratorPatients );
        $result = new AmcResult( $this->_rowRule, $totalPatients, $denominatorPatients, 0, $numeratorPatients, $percentage );
        $this->_resultsArray[]= $result;
    }
}