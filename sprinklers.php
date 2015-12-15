<?php
require __DIR__ . '/forecast.io.php';
//Script to check the weather and determine whether the sprinklers should be turned on
//Cron will run this at desired times that you want sprinklers to turn on (e.g 8am and 8pm)

//Variables - change to desired values
$api_key = "";
$latitude = "";
$longitude = "";
$units = "ca";
//$lang = "en";


//todo:
//1. probably need persistant storage so we can store whether we turned sprinkler on that day / to store current relay state

//Check weather and get forcast for the next 2 days
function getweather($api_key,$latitude,$longitude,$units = "auto",$lang = "en") {
	$forecast = new ForecastIO($api_key, $units, $lang);	
	$now = array();
	$twoday = array();

	//Get current conditions and give to array too
	$condition = $forecast->getCurrentConditions($latitude, $longitude);
	echo "current: " . $condition->getTemperature() . "\n";
	echo "percent rain: " . $condition->getPrecipitationProbability() . "\n";
	echo "humidity: " . $condition->getHumidity() . "\n";
	$now['temp'] =  $condition->getTemperature();
	$now['chancerain'] = $condition->getPrecipitationProbability();
	$now['humidity'] = $condition->getHumidity();
	$now['summary'] = $condition->getSummary();
	//If precip probability > 0 then get precip type too
	if($now['chancerain'] > 0){
		echo "Precip type: " . $current->PrecipitationType() . "\n";
		$now['preciptype'] = $current->PrecipitationType();
	}

	//Get weekly forecast (next 2 days is fine)
	$conditions_week = $forecast->getForecastWeek($latitude, $longitude);
	echo "\nConditions this week:\n";
	$i = 0;
	foreach($conditions_week as $conditions) {
		$cheese = new DateTime("now");
		$step = new DateInterval('P2D');
		//If day is less then or equal to 2 days from now then we want that info
		if($cheese->add($step)->format('Y-m-d') >= $conditions->getTime('Y-m-d'))
		{
			echo $conditions->getTime('Y-m-d') . ": " . $conditions->getMaxTemperature() . "\n";
			$twoday[$i]['maxtemp'] = $conditions->getMaxTemperature();
			$twoday[$i]['chancerain'] = $condition->getPrecipitationProbability();
			if($twoday[$i]['chancerain'] > 0){
                		echo "Precip type: " . $current->PrecipitationType() . "\n";
	                	$twoday[$i]['preciptype'] = $current->PrecipitationType();
		        }
			$twoday[$i]['humidity'] = $condition->getHumidity();
			$twoday[$i]['summary'] = $condition->getSummary();
		}
		$i++;

	}
	//todo: return array with current, tomorrow, and day after temp/conditions
	return [$now,$twoday];
}

//Get weather and save to arrays for use in logic below
list($now, $twoday) = getweather($api_key,$latitude,$longitude,$units);

//todo: Function to handle commands to send to relay and post something to instapush

//Now lets set up some logic
//todo: should it be based on humidity, chance of rain and summary? or no humidity

//If currently raining, dont turn on
if($now['chancerain'] > 0.60){
	echo "Dont do anything, currently raining";
}
//If lots of rain in the next 2 days, dont turn on

//if light rain next 2 days, turn on for 10 minutes

//if no rain in the next 2 days, turn on for 20 minutes

echo "\n after getweather \n";
echo $now['temp'] . "\n";
echo $twoday[0]['summary'] . "\n";


?>
