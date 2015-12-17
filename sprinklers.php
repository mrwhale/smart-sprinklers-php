<?php
require __DIR__ . '/lib/forecast.io.php';
require __DIR__ . '/lib/instapush.php';
//Script to check the weather and determine whether the sprinklers should be turned on
//Cron will run this at desired times that you want sprinklers to turn on (e.g 8am and 8pm)

/*
 * Weather variables
*/
$api_key = "";
$latitude = "";
$longitude = "";
$units = "";

/*
 * Push notification variables
 * Can add in other providers if needed, currently I use instapush
*/
$ipappid = "";
$ipappsecret = "";


//Guide only. set what you think is light/moderate/heavy. These are in inches/hour
// see http://www.irrigationdirect.com/chart-precipitation-equivalents-inches-per-hour-to-millimeters-per-hour for conversion to mm
$vlightrain = 0.002;
$lightrain = 0.02;
$modrain = 0.15;
$heavyrain = 0.5;
//$lang = "en";


//todo:
//1. probably need persistant storage so we can store whether we turned sprinkler on that day / to store current relay state

//Check weather and get forcast for the next 2 days
function getweather($api_key,$latitude,$longitude,$units = "auto",$lang = "en") {
	$forecast = new ForecastIO($api_key, $units, $lang);
	//This is array holding CURRENT weather data i.e. weather right now
	$now = array();
	//This holds today's, tomorrow's and the day after's weather data
	$twoday = array();

	//Get current conditions and give to array too
	$condition = $forecast->getCurrentConditions($latitude, $longitude);
	echo "current: " . $condition->getTemperature() . "\n";
	echo "percent rain: " . $condition->getPrecipitationProbability() . "\n";
	echo "humidity: " . $condition->getHumidity() . "\n";
	echo "rain fall: " . $condition->getPrecipitationIntensity();
	$now['temp'] =  $condition->getTemperature();
	$now['chancerain'] = $condition->getPrecipitationProbability();
	$now['humidity'] = $condition->getHumidity();
	$now['summary'] = $condition->getSummary();
	$now['rainfall'] = $condition->getPrecipitationIntensity();
	//If precip probability > 0 then get precip type too
	if($now['chancerain'] > 0){
		echo "Precip type: " . $current->PrecipitationType() . "\n";
		$now['preciptype'] = $current->PrecipitationType();
	}

	//Get weekly forecast (next 2 days is fine)
	$conditions_week = $forecast->getForecastWeek($latitude, $longitude);
	//print_r($conditions_week);
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
			$twoday[$i]['chancerain'] = $conditions->getPrecipitationProbability();
			$twoday[$i]['rainfall'] = $conditions->getPrecipitationIntensity();
			$twoday[$i]['rainfallmax'] = $conditions->getPrecipitationIntensityMax();
			if($twoday[$i]['rainfallmax'] > 0)
			{
				$twoday[$i]['rainfallmaxtime'] = $conditions->getPrecipitationIntensityMaxTime();
			}
			if($twoday[$i]['chancerain'] > 0){
                		echo "Precip type: " . $conditions->getPrecipitationType() . "\n";
	                	$twoday[$i]['preciptype'] = $conditions->getPrecipitationType();
		        }
			$twoday[$i]['humidity'] = $conditions->getHumidity();
			$twoday[$i]['summary'] = $conditions->getSummary();
		}
		$i++;

	}
	//return array with current, today, tomorrow, and day after temp/conditions
	return [$now,$twoday];
}

//Get weather and save to arrays for use in logic below
list($now, $twoday) = getweather($api_key,$latitude,$longitude,$units);

//Probably a better way to do this below
$yestrainlight = false;
$yestrainmod = false;
$yestrainheavy = false;
//cold/mild/warm/hot
$yesttemp = null;

$nowrain = false;
$nowrainlight = false;
$nowrainmod = false;
$nowrainheavy = false;
$nowtemp = null;

$soonrain = false;
$soonrainlight = false;
$soonrainmod = false;
$soonrainheavy = false;
$soontemp = null;

$tomrain = false;
$tomrainlight = false;
$tomrainmod = false;
$tomrainheavy = false;
$tomtemp = null;

//todo: Function to handle commands to send to relay and post something to instapush

/*
 Setup area. Lets get info into usable varaibles for the logic area so we are doing the heavy lifting here, then next bit 
will just be about checking true/false to make the decision. Hopefully this will seperate it nicely so if you want to 
change the values it'll be slightly easier
*/
//is Raining right now? how heavy?
if($now['rainfall'] > 0){
	//Sounds liek its currently raining
	$nowrain = true;
	if($now['rainfall'] >= $lightrain && $now['rainfall'] < $modrain){
		$nowrainlight = true;
		echo "\n rainfall: light \n";
	}elseif($now['rainfall'] >= $modrain && $now['rainfall'] < $heavyrain){
		$nowrainmod = true;
		echo "\n rainfall: mdoerate \n";
	}elseif($now['rainfall'] >= $heavyrain){
		$nowrainheavy = true;
		echo "\n rainfall: heavy \n";
	}

}

if($now['temp'] <= 10){
	$nowtemp = "cold";
}elseif($now['temp'] > 10 && $now['temp'] <=25){
	$nowtemp = "mild";
}elseif($now['temp'] > 25){
	$nowtemp = "hot";
}
echo "now temp: " . $nowtemp . "\n";

//will it be raining soon? (how heavy)
if($twoday[0]['rainfall'] > 0){
	$soonrain = true;
	if($twoday[0]['rainfall'] >= $lightrain && $twoday[0]['rainfall'] < $modrain){
                $soonrainlight = true;
                echo "\n soon rainfall: light \n";
        }elseif($twoday[0]['rainfall'] >= $modrain && $twoday[0]['rainfall'] < $heavyrain){
                $soonrainmod = true;
                echo "\n rsoon ainfall: mdoerate \n";
        }elseif($twoday[0]['rainfall'] >= $heavyrain){
                $soonrainheavy = true;
                echo "\n soon rainfall: heavy \n";
        }

}

if($twoday[0]['maxtemp'] <= 10){
        $soontemp = "cold";
}elseif($twoday[0]['maxtemp'] > 10 && $twoday[0]['maxtemp'] <=25){
        $soontemp = "mild";
}elseif($twoday[0]['maxtemp'] > 25){
        $soontemp = "hot";
}
echo "soon temp: " , $soontemp . "\n";


//todo: Will it be raining tomorrow? (how heavy)
if($twoday[1]['rainfall'] > 0){
        $tomrain = true;
        if($twoday[1]['rainfall'] >= $lightrain && $twoday[1]['rainfall'] < $modrain){
                $tomrainlight = true;
                echo "\n tomorrow rainfall: light \n";
        }elseif($twoday[1]['rainfall'] >= $modrain && $twoday[1]['rainfall'] < $heavyrain){
                $tomrainmod = true;
                echo "\n tomorrow rainfall: mdoerate \n";
        }elseif($twoday[1]['rainfall'] >= $heavyrain){
                $tomrainheavy = true;
                echo "\n tomorrow rainfall: heavy \n";
        }

}

if($twoday[1]['maxtemp'] <= 10){
        $tomtemp = "cold";
}elseif($twoday[1]['maxtemp'] > 10 && $twoday[1]['maxtemp'] <=25){
        $tomtemp = "mild";
}elseif($twoday[1]['maxtemp'] > 25){
        $tomtemp = "hot";
}
echo "tomorrow temp: " , $soontemp . "\n";

//todo: Did it rain yesterday (how heavy)

//todo: did it rain yesterday and is it going to be hot today? how heavy was the rain yesterday?
	//is it going to rain today? if not how hot was it?

//todo: did it rain yesterday, is there no rain today, and is it going to rain tomorrow (how heavy)
	//how hot will it be today?


/**
Logic Area. This is where we will check the logic on the data and determine how long we will water for or not
*/

//If currently raining heavier then light, or has the change to rain greater then 80%, dont turn on
if($nowrain){
	echo "Dont do anything, currently raining or about to";
	//maybe: get relay status (if possible) and turn off if currently on
}
//if light rain next 2 days, turn on for 5 minutes

//if no rain today, but chance of rain tomorrow higher then 70% and amount > light then only turn on for a 5 mins

//if no rain in the next 2 days,and no rain yesterday, turn on for 20 minutes (chance of rain below 30 % too)
elseif($twoday[0]['chancerain'] < 0.10 && $twoday[1]['chancerain'] < 0.10 && $twoday[2]['chancerain'] < 0.10){
	echo "Turning on for long time, no rain for a little while \n";
	//todo send command to turn on and notify someone?
}

else{
	echo "Something bad happened? \n";
}
echo "\n after getweather \n";
echo $now['temp'] . "\n";
echo $twoday[0]['summary'] . "\n";

?>
