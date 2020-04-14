<?php
  
  header("Access-Control-Allow-Methods: POST, GET");
  header("Access-Control-Max-Age: 3600");
  header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

  //accept input as json
  $request_data = json_decode(file_get_contents("php://input"));
    
  //set input array
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
  
  //routing
  $request = $_SERVER['REQUEST_URI'];

  switch ($request) {
      case '/api/v1/on-covid-19/xml' :
          header("Content-type: application/xml");
          if ($request_data == null){
            header("Content-type: application/json");
            echo json_encode(array("Response" => "Invalid or missing inputs"));
            return;
          }
          echo xml_response($input_data);
          $log_file = fopen("log_file.txt","a");
          fwrite($log_file, $_SERVER['REQUEST_METHOD']."\t\t".$_SERVER['REQUEST_URI']."\t\t".http_response_code()."\t\t".intval(((microtime(true)-$start)*1000))."ms\n");
          fclose($log_file);
          break;

      case '/api/v1/on-covid-19/json' :
          header("Content-type: application/json");
          if ($request_data == null){
            header("Content-type: application/json");
            echo json_encode(array("Response" => "Invalid or missing inputs"));
            return;
          }
          http_response_code(200);
          echo json_encode(covid19ImpactEstimator($input_data));
          $log_file = fopen("log_file.txt","a");
          fwrite($log_file, $_SERVER['REQUEST_METHOD']."\t\t".$_SERVER['REQUEST_URI']."\t".http_response_code()."\t\t".intval(((microtime(true)-$start)*1000))."ms\n");
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
        fwrite($log_file, $_SERVER['REQUEST_METHOD']."\t\t".$_SERVER['REQUEST_URI']."\t\t".http_response_code()."\t\t".intval(((microtime(true)-$start)*1000))."ms\n");
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

  //estimator function
  function covid19ImpactEstimator($data)
  {
    //normalise time elapsed to days
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
  }
  
?>