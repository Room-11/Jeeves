<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;
use function Room11\DOMUtils\domdocument_load_xml;

class Convert extends BasePlugin
{

    private $chatClient;
    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

	private function get_array_key($needle, $haystack) 
	{
	    foreach ($haystack as $key => $item) {
	    	if (in_array($needle, $item)) {
	            return $key;
	        }
	    }

	    return false;
	}

	private function getRate($currency, $xpath) 
	{
		if ($currency === 'EUR') {
			return 1;
		}

		return $xpath->evaluate("string(.//*[@currency='" . $currency . "']/@rate)");
	}

	private function getUnitType($unit) 
	{
		$currencies = array(
			"USD" => array(
				"usd",
				"united states dollar",
				"us dollar",
				"united states dollars",
				"us dollars"
			),
			"JPY" => array(
				"jpy",
				"japanese yen",
				"jp yen",
				"yen"
			),
			"BGN" => array(
				"bgn",
				"bulgarian lev",
				"bulgarian leva",
				"lev",
				"leva"
			),
			"CZK" => array(
				"czk",
				"czech republican koruna",
				"koruna",
				"korun",
				"korunas",
				"koruny",
				"czech koruna",
				"czech korun",
				"czech korunas",
				"czech koruny",
				"czech republican koruna",
				"czech republican korun",
				"czech republican korunas",
				"czech republican koruny"
			),
			"DKK" => array(
				"dkk",
				"danish krone",
				"danish kroner"
			),
			"GBP" => array(
				"gbp",
				"british pound",
				"pound",
				"british pounds",
				"pounds"
			),
			"HUF" => array("
				huf",
				"hungarian forint",
				"forint",
				"hungarian forints",
				"forints"
			),
			"PLN" => array(
				"pln",
				"polish zloty",
				"zloty",
				"zlotys",
				"zlote",
				"zlotych",
				"zloties",
				"polish zloty",
				"polish zlotys",
				"polish zlote",
				"polish zlotych",
				"polish zloties"
			),
			"RON" => array("ron",
				"romanian leu",
				"romanian lei"
			),
			"SEK" => array(
				"sek",
				"swedish krona",
				"swedish kronor"
			),
			"CHF" => array(
				"chf",
				"swiss franc",
				"swiss francs"
			),
			"NOK" => array(
				"nok",
				"norwegian krone",
				"norwegian kroner"
			),
			"HRK" => array(
				"hrk",
				"croatian kuna",
				"kuna",
				"kunas",
				"croatian kunas"
			),
			"RUB" => array(
				"rub",
				"russian ruble",
				"russian rouble",
				"russian rubles",
				"russian roubles"
			),
			"TRY" => array(
				"try",
				"turkish lira",
				"turkish liras",
				"turkish lire"
			),
			"AUD" => array(
				"aud",
				"australian dollar",
				"australian dollars"
			),
			"BRL" => array(
				"brl",
				"brazilian real",
				"brazilian reais"
			),
			"CAD" => array(
				"cad",
				"canadian dollar",
				"canadian dollars"
			),
			"CNY" => array(
				"cny",
				"chinese yuan",
				"yuan",
				"chinese yuans",
				"yuans"
			),
			"HKD" => array(
				"hkd",
				"hong kong dollar",
				"hong kong dollars"
			),
			"IDR" => array(
				"idr",
				"indonesian rupiah",
				"indonesian rupiahs",
				"rupiah",
				"rupiahs"
			),
			"ILS" => array(
				"ils",
				"israeli new shekel",
				"isreali shekel",
				"isreali shekels",
				"isreali sheqalim",
				"isreali new shekels",
				"isreali new sheqalim",
				"new shekel",
				"new shekels",
				"new sheqalim",
				"shekel",
				"shekels",
				"sheqalim"
			),
			"INR" => array(
				"inr",
				"indian rupee",
				"indian rupees"
			),
			"KRW" => array(
				"krw",
				"south korean won",
				"won",
				"korean republic won"
			),
			"MXN" => array(
				"mxn",
				"mexican peso",
				"mexican pesos"
			),
			"MYR" => array(
				"myr",
				"malaysian ringgit",
				"ringgit",
				"malaysian ringgits",
				"ringgits"
			),
			"NZD" => array(
				"nzd",
				"new zealand dollar",
				"new zealand dollars",
				"nz dollar",
				"nz dollars"
			),
			"PHP" => array(
				"php",
				"philippine peso",
				"philippine pesos"
			),
			"SGD" => array(
				"sgd",
				"singapore dollar",
				"singapore dollars"
			),
			"THB" => array(
				"thb",
				"thai baht",
				"baht",
				"bahts",
				"thai bahts"
			),
			"ZAR" => array(
				"zar",
				"south african rand",
				"rand",
				"rands",
				"south african rands"
			),
			"EUR" => array(
				"eur",
				"euro"
			)
		);

		$weights = array(
			"t" => array(
				"tonne",
				"t",
				"tonnes"
			),
			"kg" => array(
				"kilogram",
				"kg",
				"kilograms"
			),
			"hg" => array(
				"hg",
				"hectogram",
				"hectograms"
			),
			"g" => array(
				"g",
				"gram",
				"grams"
			),
			"dg" => array(
				"dg",
				"decigram",
				"decigrams"
			),
			"cg" => array(
				"cg",
				"centigram",
				"centigrams"
			),
			"mg" => array(
				"mg",
				"milligram",
				"milligrams"
			),
			"µg" => array(
				"µg",
				"microgram",
				"ug",
				"micrograms"
			),
			"carat" => array(
				"carat",
				"carats"
			),
			"grain" => array(
				"grain",
				"grains"
			),
			"oz" => array(
				"oz",
				"ounce",
				"ounces"
			),
			"lb" => array(
				"lb",
				"pound",
				"pounds"
			),
			"st" => array(
				"st",
				"stone",
				"stones"
			)
		);

		$areas = array(
			"km²" => array(
				"km²",
				"sq kilometer",
				"square kilometer",
				"sq kilometers",
				"square kilometers",
				"kilometer squared",
				"kilometers squared",
				"sq km",
				"square km",
				"km squared"
			),
			"m²" => array(
				"m²",
				"sq meter",
				"square meter",
				"sq meters",
				"square meters",
				"meter squared",
				"meters squared",
				"sq m",
				"square m",
				"m squared"
			),
			"dm²" => array(
				"dm²",
				"sq decimeter",
				"square decimeter",
				"sq decimeters",
				"square decimeters",
				"decimeter squared",
				"decimeters squared",
				"sq dm",
				"square dm",
				"dm squared"
			),
			"cm²" => array(
				"cm²",
				"sq centimeter",
				"square centimeter",
				"sq centimeters",
				"square centimeters",
				"centimeter squared",
				"centimeters squared",
				"sq cm",
				"square cm",
				"cm squared"
			),
			"mm²" => array(
				"mm²",
				"sq millimeter",
				"square millimeter",
				"sq millimeters",
				"square millimeters",
				"millimeter squared",
				"millimeters squared",
				"sq mm",
				"square mm",
				"mm squared"
			),
			"ha" => array(
				"ha",
				"hectare",
				"hectares"
			),
			"a" => array(
				"a",
				"are",
				"ares"
			),
			"ca" => array(
				"ca",
				"centiare",
				"centiares"
			),
			"mile²" => array(
				"mile²",
				"sq mile",
				"square mile",
				"sq miles",
				"square miles",
				"mile squared",
				"miles squared"
			),
			"in²" => array(
				"in²",
				"sq inch",
				"square inch",
				"sq inches",
				"square inches",
				"inch squared",
				"inches squared",
				"sq in",
				"square in",
				"in squared"
			),
			"yd²" => array(
				"yd²",
				"sq yard",
				"square yard",
				"sq yards",
				"square yards",
				"yard squared",
				"yards squared",
				"sq yd",
				"square yd",
				"yd squared"
			),
			"ft²" => array(
				"ft²",
				"sq foot",
				"square foot",
				"sq feet",
				"square feet",
				"foot squared",
				"feet squared",
				"sq ft",
				"square ft",
				"ft squared"
			),
			"ro" => array(
				"ro",
				"rood",
				"roods"
			),
			"acre" => array(
				"acre",
				"acre",
				"acres"
				),
			"nautical mile²" => array(
				"sq nautical mile",
				"square nautical mile",
				"sq nautical miles",
				"square nautical miles",
				"nautical mile squared",
				"nautical miles squared"
				)
		);


		$speeds = array(
			"kmph" => array(
				"kilometer per hour",
				"km/h",
				"kmph",
				"kilometers per hour"
				),
			"mps" => array(
				"m/s",
				"mps",
				"meter per second",
				"meters per second"
				),
			"mph" => array(
				"mph",
				"mi/h",
				"mile per hour",
				"miles per hour"
				),
			"knot" => array(
				"nautical mile/h",
				"knot",
				"nautical miles per hour",
				"knots",
				"nautical mile per hour",
				"nautical miles/h"
			),
			"ma" => array(
				"ma",
				"mac",
				"macs"
			)
		);

		$distances = array(
			"km" => array(
				"km",
				"kilometer",
				"kilometers"
			),
			"m" => array(
				"m",
				"meter",
				"meters"
				),
			"dm" => array(
				"dm",
				"decimeter",
				"decimeters"
			),
			"cm" => array(
				"cm",
				"centimeter",
				"centimeters"
			),
			"mm" => array(
				"mm",
				"millimeter",
				"millimeters"
			),
			"mi" => array(
				"mi",
				"mile",
				"miles"
			),
			"in" => array(
				"in",
				"inch",
				"inches"
			),
			"ft" => array(
				"ft",
				"foot",
				"feet"
			),
			"yd" => array(
				"yd",
				"yard",
				"yards"
			),
			"nautical mile" => array(
				"nautical mile",
				"nautical miles"
			)
		);

		$temperatures = array(
			"°C" => array(
				"c",
				"°c",
				"celsius"
			),
			"°F" => array(
				"f",
				"°f",
				"fahrenheit"
			),
			"K" => array(
				"k",
				"kelvin"
			)
		);

		$unit = strtolower($unit);

		if ( ( $key = $this->get_array_key($unit, $currencies) ) !== false ) {
			return array("currency", $key);
		}

		if ( ( $key = $this->get_array_key($unit, $weights) ) !== false ) {
			return array("weight", $key);
		}

		if ( ( $key = $this->get_array_key($unit, $areas) ) !== false ) {
			return array("area", $key);
		}

		if ( ( $key = $this->get_array_key($unit, $speeds) ) !== false ) {
			return array("speed", $key);
		}

		if ( ( $key = $this->get_array_key($unit, $distances) ) !== false ) {
			return array("distance", $key);
		}

		if ( ( $key = $this->get_array_key($unit, $temperatures) ) !== false ) {
			return array("temperature", $key);
		}

		return false;
	}

	private function distanceConvert($from, $to, $amount) 
	{
		$distanceinmeters = array(
			"km" => 1000,
			"m" => 1,
			"dm" => 0.1,
			"cm" => 0.01,
			"mm" => 0.001,
			"mi" => 1609.344,
			"in" => 0.0254,
			"ft" => 0.3048,
			"yd" => 0.9144,
			"nautical mile" => 1852
		);
		return round(($amount * $distanceinmeters[$from]) / ($distanceinmeters[$to]), 4);
	}
	
	private function speedConvert($from, $to, $amount) 
	{
		$speedinkmph = array(
			"kmph" => 1,
			"mps" => 3.6,
			"mph" => 1.609344,
			"knot" => 1.852,
			"ma" => 1224
		);
		return round(($amount * $speedinkmph[$from]) / ($speedinkmph[$to]), 4);
	}

	private function areaConvert($from, $to, $amount) 
	{
		$weightinsqmt = array(
			"km²" => 1000000,
			"m²" => 1,
			"dm²" => 0.01,
			"cm²" => 0.0001,
			"mm²" => 0.000001,
			"ha" => 10000,
			"a" => 100,
			"ca" => 1,
			"mile²" => 2589988.110336,
			"in²" => 0.00064516000000258,
			"yd²" => 0.83612040133779,
			"ft²" => 0.092910898448388,
			"ro" => 1011.7141056007,
			"acre" => 4046.8564300508,
			"nautical mile²" => 3434290.0120544
		);
		return round(($amount * $weightinsqmt[$from]) / ($weightinsqmt[$to]), 4);
	}

	private function tempConvert($from, $to, $amount) 
	{
		$conversion = 0;

		if($from === "°F" && $to === "°C"){

	    	$conversion = ((9/5) * $amount) + (32);
		
		} else if ($from === "°F" && $to === "K"){
	    
	    	$conversion = ((($amount - 32) * 5) / 9) + 273.15;
		
		} else if ($from === "°C" && $to === "°F") {

	    	$conversion = ($amount - 32) * (5/9);

		} else if ($from === "°C" && $to === "K") {

			$conversion = ($amount + 273.15);

		} else if ($from === "K" && $to === "°C") {

			$conversion = ($amount - 273.15);

		} else if ($from === "K" && $to === "°F") {

			$conversion = (($temp - 273.15) * 1.8) + 32;

		}

		return round($conversion, 1);
	}

	private function currencyConvert($from, $to, $amount) 
	{	
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request('http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');

        if ($response->getStatus() !== 200) {
            return false;
        }

		$dom = domdocument_load_xml($response->getBody());

		$xpath = new \DOMXPath($dom);

		$from_rate = $this->getRate($from, $xpath);

		$to_rate = $this->getRate($to, $xpath);

		return (($amount / $from_rate) * ($to_rate));

	}

	private function weightConvert($from, $to, $amount) 
	{
		$weightsingrams = array(
			"t" => 1000000,
			"kg" => 1000,
			"hg" => 100,
			"g" => 1,
			"dg" => 0.1,
			"cg" => 0.01,
			"mg" => 0.001,
			"µg" => 0.000001,
			"carat" => 0.2,
			"grain" => 0.06479891,
			"oz" => 28.349523125,
			"lb" => 453.59237000021,
			"st" => 6350.2934
		);
	
		return round(($amount * $weightsingrams[$from]) / ($weightsingrams[$to]), 4);
	}

	private function parse_params(array $params_arr) 
	{
		if (is_numeric($params_arr[0])) {
	
			$amount = (int) $params_arr[0];
			array_shift($params_arr);
	
		} else if (is_numeric($params_arr[0][0])) {
	
			$first_param_arr = preg_split('/(?=[^0-9\.\,])/', $params_arr[0], 2);
			$amount = (int) $first_param_arr[0];
			$params_arr[0] = $first_param_arr[1];
	
		} else {

			$amount = 1;

		}

		if ( ( $to_position = array_search('to', $params_arr) ) === false) {

			return false;

		}
	
		$from_unit = implode(" ", array_slice($params_arr, 0, $to_position));
		$to_unit = implode(" ", array_slice($params_arr, $to_position + 1));

		return array(
			"amount" => $amount,
			"from_unit" => $from_unit,
			"to_unit" => $to_unit
		);
	}

	public function convert(Command $command) 
	{
		$params_arr = $command->getParameters();

		$parsed_params = $this->parse_params($params_arr);

		if ($parsed_params === false) {
			return $this->chatClient->postReply(
				$command,
				"I'm really not sure what you want me to do..."
			);

		}

		if ($parsed_params['amount'] === 0) {
			return $this->chatClient->postReply(
				$command,
				"Yeah let me just divide this by 0 and..."
			);
		}

		$from_unit_arr = $this->getUnitType($parsed_params['from_unit']);
		$to_unit_arr = $this->getUnitType($parsed_params['to_unit']);

		if ($from_unit_arr === false || $to_unit_arr === false) {
			return $this->chatClient->postReply(
				$command,
				"They didn't teach me that one yet."
			);
		}

		if ( ( $from_type = $from_unit_arr[0] ) !== ( $to_type = $to_unit_arr[0] ) ) {
			return $this->chatClient->postReply(
				$command, 
				"What?? They never taught me how to convert " . $from_type . " to " . $to_type . "."
			);
		}

		switch($from_type) {
			case 'temperature':
				return $this->chatClient->postReply(
					$command, 
					$parsed_params['amount'] 
					. $from_unit_arr[1] 
					. ' = ' 
					. $this->tempConvert(
						$from_unit_arr[1], 
						$to_unit_arr[1], 
						$parsed_params['amount']
					) 
					. $to_unit_arr[1]
				);
				break;

			case 'currency':
				$converted_currency = $this->currencyConvert(
					$from_unit_arr[1],
					$to_unit_arr[1],
					$parsed_params['amount']
				);
			
				if ($converted_currency === false) {

					return $this->chatClient->postReply(
						$command, 
						"I don't know what's going on but I can't find that information."
					);

				} else {

					return $this->chatClient->postReply(
						$command, 
						number_format($parsed_params['amount'], 2, '.', ',') 
						. $from_unit_arr[1] 
						. ' = ' 
						. number_format($converted_currency, 2, '.', ',') 
						. $to_unit_arr[1]
					);
				
				}
				break;

			case 'weight':
				return $this->chatClient->postReply(
					$command,
					$parsed_params['amount'] 
					. $from_unit_arr[1] 
					. ' = ' 
					. $this->weightConvert(
						$from_unit_arr[1],
						$to_unit_arr[1],
						$parsed_params['amount']
					) 
					. $to_unit_arr[1]
				);
				break;

			case 'area':
				return $this->chatClient->postReply(
					$command,
					$parsed_params['amount'] 
					. $from_unit_arr[1] 
					. ' = ' 
					. $this->areaConvert(
						$from_unit_arr[1],
						$to_unit_arr[1], 
						$parsed_params['amount']
					) 
					. $to_unit_arr[1]
				);
				break;

			case 'speed':
				return $this->chatClient->postReply(
					$command, 
					$parsed_params['amount'] 
					. $from_unit_arr[1] 
					. ' = ' 
					. $this->speedConvert(
						$from_unit_arr[1], 
						$to_unit_arr[1], 
						$parsed_params['amount']
					) 
					. $to_unit_arr[1]
				);
				break;

			case 'distance':
				return $this->chatClient->postReply(
					$command, 
					$parsed_params['amount'] 
					. $from_unit_arr[1] 
					. ' = ' 
					. $this->distanceConvert(
						$from_unit_arr[1], 
						$to_unit_arr[1], 
						$parsed_params['amount']) 
					. $to_unit_arr[1]
				);
		}
	}

    public function getDescription(): string
    {
        return "Converts different units of measurements as well as currencies.";
    }

    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Convert', [$this, 'convert'], 'convert')];
    }

}
?>
