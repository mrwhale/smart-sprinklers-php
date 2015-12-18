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
$units = "ca";

/*
 * Push notification variables
 * Can add in other providers if needed, currently I use instapush
*/
$ipappid = "";
$ipappsecret = "";
$event = "";

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

function instapush_notify($ipappid, $ipappsecret, $event, $time, $reason){
            echo "\ninstapush \n";
            //Send notification to instapush, man
            $ip = InstaPush::getInstance($ipappid, $ipappsecret);
            $ip->track($event, array("time"=> $time,"reason" => $reason));
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
        echo "\nrainfall: light \n";
    }elseif($now['rainfall'] >= $modrain && $now['rainfall'] < $heavyrain){
        $nowrainmod = true;
        echo "\nrainfall: mdoerate \n";
    }elseif($now['rainfall'] >= $heavyrain){
        $nowrainheavy = true;
        echo "\nrainfall: heavy \n";
    }

}

if($now['temp'] <= 10){
    $nowtemp = "cold";
}elseif($now['temp'] > 10 && $now['temp'] <=21){
    $nowtemp = "mild";
}elseif($now['temp'] >21 && $now['temp']<= 28){
    $nowtemp = "warm";
}elseif($now['temp'] > 28){
    $nowtemp = "hot";
}
echo "now temp: " . $nowtemp . "\n";

//will it be raining soon? (how heavy)
if($twoday[0]['rainfall'] > 0){
    $soonrain = true;
    if($twoday[0]['rainfall'] >= $lightrain && $twoday[0]['rainfall'] < $modrain){
                $soonrainlight = true;
                echo "\nsoon rainfall: light \n";
        }elseif($twoday[0]['rainfall'] >= $modrain && $twoday[0]['rainfall'] < $heavyrain){
                $soonrainmod = true;
                echo "\nsoon ainfall: mdoerate \n";
        }elseif($twoday[0]['rainfall'] >= $heavyrain){
                $soonrainheavy = true;
                echo "\nsoon rainfall: heavy \n";
        }

}

if($twoday[0]['maxtemp'] <= 10){
        $soontemp = "cold";
}elseif($twoday[0]['maxtemp'] > 10 && $twoday[0]['maxtemp'] <=21){
        $soontemp = "mild";
}elseif($twoday[0]['maxtemp'] >21 && $twoday[0]['maxtemp']<= 28){
        $soontemp = "warm";
}elseif($twoday[0]['maxtemp'] > 28){
        $soontemp = "hot";
}
echo "soon temp: " , $soontemp . "\n";


//todo: Will it be raining tomorrow? (how heavy)
if($twoday[1]['rainfall'] > 0){
        $tomrain = true;
        if($twoday[1]['rainfall'] >= $lightrain && $twoday[1]['rainfall'] < $modrain){
                $tomrainlight = true;
                echo "\ntomorrow rainfall: light \n";
        }elseif($twoday[1]['rainfall'] >= $modrain && $twoday[1]['rainfall'] < $heavyrain){
                $tomrainmod = true;
                echo "\ntomorrow rainfall: mdoerate \n";
        }elseif($twoday[1]['rainfall'] >= $heavyrain){
                $tomrainheavy = true;
                echo "\ntomorrow rainfall: heavy \n";
        }

}

if($twoday[1]['maxtemp'] <= 10){
        $tomtemp = "cold";
}elseif($twoday[1]['maxtemp'] > 10 && $twoday[1]['maxtemp'] <=21){
        $tomtemp = "mild";
}elseif($twoday[1]['maxtemp'] >21 && $twoday[1]['maxtemp']<= 28){
        $tomtemp = "warm";
}elseif($twoday[1]['maxtemp'] > 28){
        $tomtemp = "hot";
}
echo "tomorrow temp: " , $soontemp . "\n";

//todo: Did it rain yesterday (how heavy)
// need to get this data

//todo: did it rain yesterday and is it going to be hot today? how heavy was the rain yesterday?
    //is it going to rain today? if not how hot was it?

//todo: did it rain yesterday, is there no rain today, and is it going to rain tomorrow (how heavy)
    //how hot will it be today?


/**
Logic Area. This is where we will check the logic on the data and determine how long we will water for or not
*/

if($nowrain){
    //Its Currently Raining!!
    //If currently raining heavier then light, or has the change to rain greater then 80%, dont turn on
    if($nowrainlight){
        //todo: Lets check yesterdays weather. If no rain then maybe we should give a light water
        echo "now light rain \n";
    }
    if($nowrainmod || $nowrainheavy){
        //probably dont need to do anything as its raining
        echo "now mod or heavy rain \n";
    }
    //maybe: get relay status (if possible) and turn off if currently on
}else{
    //No rain currently
    echo "now no rain \n";
    //todo: check now Chance and today chance of rain for today if its really high, check how heavy. if only light
    // put on for little bit. if mod of heavy then dont turn on
    // If no rain for today, then turn on for x mins. also check tomorrow's chance of rain. if low then put on for longer. if high put on for short amount
    if(!$soonrain){
            echo "no rain now or today :( better water \n";
    }elseif($now['chancerain'] > 0.60 && $twoday[0]['chancerain'] > 0.60){
        //echo "Chance of rain is high today \n";
        if($soonrainlight){
            //echo "only light rain today, lets check tomorrow's";
            if($tomrainmod || $tomrainheavy){
                echo "Chance of rain is high today (only light) gonna be mod to heavy rain tomorrow though (pretty high change too)\n";
            }else{
                echo "Chance of rain is high today (only light) and only going to be light rain tomorrow so we should water a bit? \n"; 
            }
        }
    }else{
        //echo "No rain currently and chance of rain is pretty low today \n";
        if($twoday[1]['chancerain'] < 0.40){
            echo "No rain currently and chance of rain is pretty low today pretty low chance of rain tomorrow,too, should water \n";
        }
    }



/*
    if($now['chancerain'] > 0.60 && $twoday[0]['chancerain'] > 0.60){
        echo "Chance of rain is high today \n";
        if($soonrainlight){
            echo "only light rain today, lets check tomorrow's";
        }
    }elseif(!$soonrain){
            echo "no rain now or today :( better water \n";
    }else{
        echo "chance of rain is pretty low today \n";
        if($twoday[1]['chancerain'] < 0.40){
            echo "pretty low chance of rain tomorrow, should water \n";
        }
    }
*/
    //todo: check tomorrow chance of rain, if high, check how heavy the rain will be. If light then turn on for x minutes
    // if mod or heavy, dont turn on

    //todo: Check yesterday if it rained. If it did see how heavy. If it was only light then check tomorrows chance. If high then dont turn on. If tomorrows chance is low, then turn on
    // if heavy rain yesterday and some rain tomorrow. dont turn on.
    // if have rain yesterday and no rain tomorrow then turn on slightly
    // if no rain yesterday, no rain today, or tomorrow put on for a while
}
//if light rain next 2 days, turn on for 5 minutes

//if no rain today, but chance of rain tomorrow higher then 70% and amount > light then only turn on for a 5 mins

//if no rain in the next 2 days,and no rain yesterday, turn on for 20 minutes (chance of rain below 30 % too)
if($twoday[0]['chancerain'] < 0.10 && $twoday[1]['chancerain'] < 0.10 && $twoday[2]['chancerain'] < 0.10){
    echo "Turning on for long time, no rain for a little while \n";
    //todo send command to turn on and notify someone?
}

else{
    echo "Something bad happened? \n";
    instapush_notify($ipappid, $ipappsecret, $event, 20, "its really hot");
}
echo "\n after getweather \n";
echo $now['temp'] . "\n";
echo $twoday[0]['summary'] . "\n";

?>
