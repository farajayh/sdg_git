<?php

  $start = microtime(true);
  
  header("Access-Control-Allow-Methods: POST");
  header("Access-Control-Max-Age: 3600");
  header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

  
  
  $request_data = json_decode(file_get_contents("php://input"));
    
    
    $input_data = array (
      'region' => array(
                      'name' => $request_data->region->name,
                      'avgAge' => $request_data->region->avgAge,
                      'avgDailyIncomeInUSD' => $request_data->region->avgDailyIncomeInUSD,
                      'avgDailyIncomePopulation' => $request_data->region->avgDailyIncomePopulation
                    ),
      'periodType' => $request_data->periodType,
      'timeToElapse' =>  $request_data->timeToElapse,
      'reportedCases' => $request_data->reportedCases,
      'population' => $request_data->population,
      'totalHospitalBeds' => $request_data->totalHospitalBeds,
      );


  $request = $_SERVER['REQUEST_URI'];

  switch ($request) {
      case '/api/v1/on-covid-19/xml' :
          header("Content-type: text/xml");
          if ($request_data == null){
            header("Content-type: application/json");
            echo json_encode(array("Response" => "Invalid or missing inputs"));
            return;
          }
          echo xml_response($input_data);
          $log_file = fopen("log_file.txt","a");
          fwrite($log_file, $_SERVER['REQUEST_METHOD']."\t\t".$_SERVER['REQUEST_URI']."\t\t\t\t".http_response_code()."\t\t".((microtime(true)-$start)*1000)."ms\n");
          fclose($log_file);
          break;

      case '/api/v1/on-covid-19/json' :
          header("Content-type: application/json");
          if ($request_data == null){
            header("Content-type: application/json");
            echo json_encode(array("Response" => "Invalid or missing inputs"));
            return;
          }
          echo json_encode(covid19ImpactEstimator($input_data));
          $log_file = fopen("log_file.txt","a");
          fwrite($log_file, $_SERVER['REQUEST_METHOD']."\t\t".$_SERVER['REQUEST_URI']."\t\t\t\t".http_response_code()."\t\t".((microtime(true)-$start)*1000)."ms\n");
          fclose($log_file);
          break;

      case '/api/v1/on-covid-19' :
        header("Content-type: application/json");
        if ($request_data == null){
          header("Content-type: application/json");
          echo json_encode(array("Response" => "Invalid or missing inputs"));
          return;
        }
        echo json_encode(covid19ImpactEstimator($input_data));
        $log_file = fopen("log_file.txt","a");
        fwrite($log_file, $_SERVER['REQUEST_METHOD']."\t\t".$_SERVER['REQUEST_URI']."\t\t\t\t".http_response_code()."\t\t".((microtime(true)-$start)*1000)."ms\n");
        fclose($log_file);
        break;

      case '/api/v1/on-covid-19/logs' :
        header("Content-type: text");
        $log_file = fopen("log_file.txt","r");
        echo fread($log_file, filesize("log_file.txt"));
        break;

      default:
          header("Content-type: application/json");
          echo json_encode(array("Response" => "Unrecognised endpoint"));

          break;
  }

  function covid19ImpactEstimator($data)
  {
    switch ($data['periodType']){
      case 'weeks':
        $timeToElapse = $data['timeToElapse']*7;
        break;
      case 'months':
        $timeToElapse = $data['timeToElapse']*30;
        break;
      default:
        $timeToElapse = $data['timeToElapse'];
      
    }

    //currently infected
    $impactCurrentlyInfected = $data['reportedCases']*10;
    $severeImpactCurrentlyInfected = $data['reportedCases']*50;

    //infections by requested time 
    $impactInfectionsByRequestedTime = $impactCurrentlyInfected * (2**floor($timeToElapse/3));
    $severeImpactInfectionsByRequestedTime = $severeImpactCurrentlyInfected * (2**floor($timeToElapse/3));

    //severe cases by requested time
    $impactSevereCasesByRequestedTime = floor((15/100)*$impactInfectionsByRequestedTime);
    $severeImpactSevereCasesByRequestedTime = floor((15/100)*$severeImpactInfectionsByRequestedTime);

    //hospital bed by requested time
    $impactHospitalBedsByRequestedTime = ceil((35/100)*$data['totalHospitalBeds'] - $impactSevereCasesByRequestedTime);
    $severeImpactHospitalBedsByRequestedTime = ceil((35/100)*$data['totalHospitalBeds'] - $severeImpactSevereCasesByRequestedTime);

    //cases for ICU by requested time
    $impactCasesForICUByRequestedTime = floor((5/100)*$impactInfectionsByRequestedTime);
    $severeImpactCasesForICUByRequestedTime = floor((5/100)*$severeImpactInfectionsByRequestedTime);

    //cases for ventilators by requested time
    $impactCasesForVentilatorsByRequestedTime = floor((2/100)*$impactInfectionsByRequestedTime);
    $severeImpactCasesForVentilatorsByRequestedTime = floor((2/100)*$severeImpactInfectionsByRequestedTime);

    //dollars in flight
    $impactDollarsInFlight = floor(($impactInfectionsByRequestedTime * $data['region']['avgDailyIncomePopulation'] * $data['region']['avgDailyIncomeInUSD'])/$timeToElapse);
    $severeImpactDollarsInFlight = floor(($severeImpactInfectionsByRequestedTime * $data['region']['avgDailyIncomePopulation'] * $data['region']['avgDailyIncomeInUSD'])/$timeToElapse);

    $data = array(
                        'data' => $data,
                        'impact' => array(
                                          'currentlyInfected' => $impactCurrentlyInfected,
                                          'infectionsByRequestedTime' =>  $impactInfectionsByRequestedTime,
                                          'severeCasesByRequestedTime' => $impactSevereCasesByRequestedTime,
                                          'hospitalBedsByRequestedTime' => $impactHospitalBedsByRequestedTime,
                                          'casesForICUByRequestedTime' => $impactCasesForICUByRequestedTime,
                                          'casesForVentilatorsByRequestedTime' => $impactCasesForVentilatorsByRequestedTime,
                                          'dollarsInFlight' => $impactDollarsInFlight
                        ),
                        'severeImpact' => array(
                                          'currentlyInfected' => $severeImpactCurrentlyInfected,
                                          'infectionsByRequestedTime' =>  $severeImpactInfectionsByRequestedTime,
                                          'severeCasesByRequestedTime' => $severeImpactSevereCasesByRequestedTime,
                                          'hospitalBedsByRequestedTime' => $severeImpactHospitalBedsByRequestedTime,
                                          'casesForICUByRequestedTime' => $severeImpactCasesForICUByRequestedTime,
                                          'casesForVentilatorsByRequestedTime' => $severeImpactCasesForVentilatorsByRequestedTime,
                                          'dollarsInFlight' => $severeImpactDollarsInFlight
                        ),
                  );
    return $data;
  }

  //echo json_encode(covid19ImpactEstimator($input_data));
  
  //echo "<br>".$_SERVER['REQUEST_URI'];

  function xml_response($input_data){

    $result = covid19ImpactEstimator($input_data);

    // Start XML file, create parent node
    $xml = new DOMDocument("1.0");
    $estimate = $xml->createElement("Estimate");

    //input data
    $data = $xml->createElement("Data");
    $region = $xml->createElement("Region",  "Africa");
    $region->setAttribute("avgAge", "19.7");
    $region->setAttribute("avgDailyIncomeInUSD", "5");
    $region->setAttribute("avgDailyIncomePopulation", "0.71");
    $data->appendchild($region);
    $periodType = $xml->createElement("periodType",  "days");
    $data->appendchild($periodType);
    $timeToElapse = $xml->createElement("timeToElapse",  "58");
    $data->appendchild($timeToElapse);
    $reportedCases = $xml->createElement("reportedCases",  "674");
    $data->appendchild($reportedCases);
    $population = $xml->createElement("population",  "66622705");
    $data->appendchild($population);
    $totalHospitalBeds = $xml->createElement("totalHospitalBeds",  "1380614");
    $data->appendchild($totalHospitalBeds);
    $estimate->appendchild($data);
    
    //impact
    $impact = $xml->createElement("Impact");
    $currentlyInfected = $xml->createElement("currentlyInfected",  $result['impact']['currentlyInfected']);
    $impact->appendchild($currentlyInfected);
    $infectionsByRequestedTime = $xml->createElement("infectionsByRequestedTime",  $result['impact']['infectionsByRequestedTime']);
    $impact->appendchild($infectionsByRequestedTime);
    $severeCasesByRequestedTime = $xml->createElement("severeCasesByRequestedTime",  $result['impact']['severeCasesByRequestedTime']);
    $impact->appendchild($severeCasesByRequestedTime);
    $hospitalBedsByRequestedTime = $xml->createElement("hospitalBedsByRequestedTime",  $result['impact']['hospitalBedsByRequestedTime']);
    $impact->appendchild($hospitalBedsByRequestedTime);
    $casesForICUByRequestedTime = $xml->createElement("casesForICUByRequestedTime",  $result['impact']['casesForICUByRequestedTime']);
    $impact->appendchild($casesForICUByRequestedTime);
    $casesForVentilatorsByRequestedTime = $xml->createElement("casesForVentilatorsByRequestedTime",  $result['impact']['casesForVentilatorsByRequestedTime']);
    $impact->appendchild($casesForVentilatorsByRequestedTime);
    $dollarsInFlight = $xml->createElement("dollarsInFlight",  $result['impact']['dollarsInFlight']);
    $impact->appendchild($dollarsInFlight);
    $estimate->appendchild($impact);

    //severe impact
    $severeImpact = $xml->createElement("SevereImpact");
    $currentlyInfected = $xml->createElement("currentlyInfected",  $result['severeImpact']['currentlyInfected']);
    $severeImpact->appendchild($currentlyInfected);
    $infectionsByRequestedTime = $xml->createElement("infectionsByRequestedTime",  $result['severeImpact']['infectionsByRequestedTime']);
    $severeImpact->appendchild($infectionsByRequestedTime);
    $severeCasesByRequestedTime = $xml->createElement("severeCasesByRequestedTime",  $result['severeImpact']['severeCasesByRequestedTime']);
    $severeImpact->appendchild($severeCasesByRequestedTime);
    $hospitalBedsByRequestedTime = $xml->createElement("hospitalBedsByRequestedTime",  $result['severeImpact']['hospitalBedsByRequestedTime']);
    $severeImpact->appendchild($hospitalBedsByRequestedTime);
    $casesForICUByRequestedTime = $xml->createElement("casesForICUByRequestedTime",  $result['severeImpact']['casesForICUByRequestedTime']);
    $severeImpact->appendchild($casesForICUByRequestedTime);
    $casesForVentilatorsByRequestedTime = $xml->createElement("casesForVentilatorsByRequestedTime",  $result['severeImpact']['casesForVentilatorsByRequestedTime']);
    $severeImpact->appendchild($casesForVentilatorsByRequestedTime);
    $dollarsInFlight = $xml->createElement("dollarsInFlight",  $result['severeImpact']['dollarsInFlight']);
    $severeImpact->appendchild($dollarsInFlight);
    $estimate->appendchild($severeImpact);

    $xml->appendchild($estimate);
    
    return $xml->saveXML();
  }

?>