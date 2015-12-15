# smart-sprinklers-php
Php script to monitor weather data and determine watering cycle

As I couldn't find anything that would suit my project I am creating a php script that will read in local weather data and determine the next watering cycle for your sprinklers

As the hardware for this project hasn't arrived yet, I'm working on this. The hardware will be an esp6822 WiFi module attached to a relay and solenoid valve (more info to come) with this script sitting on my home server

This will, hopefully, monitor local weather data and combine with historical data to determine if the sprinklers should be turned on or off for a time period

Commands will be send to the hardware controller via either mqtt or a RESTful API (haven't decided yet) 

Check it out and help update my logic on deciding on the cycle. Other than opensprinkler I was unable to find anything else that would be suitable for my project
