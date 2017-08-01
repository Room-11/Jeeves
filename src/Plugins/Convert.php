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

    private function get_array_key(string $needle, array $haystack): string
    {
        foreach ($haystack as $key => $item) {
            if (in_array($needle, $item)) {
                return $key;
            }
        }
        //Throw Exception
        return false;
    }

    private function getRate(string $currency, \DOMXPath $xpath): integer
    {
        if ($currency === 'EUR') {
            return 1;
        }

        return $xpath->evaluate("string(.//*[@currency='" . $currency . "']/@rate)");
    }

    private function getUnitType(string $unit): array
    {
        $currencies = [
            "USD" => [
                "usd",
                "united states dollar",
                "us dollar",
                "united states dollars",
                "us dollars"
            ],
            "JPY" => [
                "jpy",
                "japanese yen",
                "jp yen",
                "yen"
            ],
            "BGN" => [
                "bgn",
                "bulgarian lev",
                "bulgarian leva",
                "lev",
                "leva"
            ],
            "CZK" => [
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
            ],
            "DKK" => [
                "dkk",
                "danish krone",
                "danish kroner"
            ],
            "GBP" => [
                "gbp",
                "british pound",
                "pound",
                "british pounds",
                "pounds"
            ],
            "HUF" => ["
                huf",
                "hungarian forint",
                "forint",
                "hungarian forints",
                "forints"
            ],
            "PLN" => [
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
            ],
            "RON" => ["ron",
                "romanian leu",
                "romanian lei"
            ],
            "SEK" => [
                "sek",
                "swedish krona",
                "swedish kronor"
            ],
            "CHF" => [
                "chf",
                "swiss franc",
                "swiss francs"
            ],
            "NOK" => [
                "nok",
                "norwegian krone",
                "norwegian kroner"
            ],
            "HRK" => [
                "hrk",
                "croatian kuna",
                "kuna",
                "kunas",
                "croatian kunas"
            ],
            "RUB" => [
                "rub",
                "russian ruble",
                "russian rouble",
                "russian rubles",
                "russian roubles"
            ],
            "TRY" => [
                "try",
                "turkish lira",
                "turkish liras",
                "turkish lire"
            ],
            "AUD" => [
                "aud",
                "australian dollar",
                "australian dollars"
            ],
            "BRL" => [
                "brl",
                "brazilian real",
                "brazilian reais"
            ],
            "CAD" => [
                "cad",
                "canadian dollar",
                "canadian dollars"
            ],
            "CNY" => [
                "cny",
                "chinese yuan",
                "yuan",
                "chinese yuans",
                "yuans"
            ],
            "HKD" => [
                "hkd",
                "hong kong dollar",
                "hong kong dollars"
            ],
            "IDR" => [
                "idr",
                "indonesian rupiah",
                "indonesian rupiahs",
                "rupiah",
                "rupiahs"
            ],
            "ILS" => [
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
            ],
            "INR" => [
                "inr",
                "indian rupee",
                "indian rupees"
            ],
            "KRW" => [
                "krw",
                "south korean won",
                "won",
                "korean republic won"
            ],
            "MXN" => [
                "mxn",
                "mexican peso",
                "mexican pesos"
            ],
            "MYR" => [
                "myr",
                "malaysian ringgit",
                "ringgit",
                "malaysian ringgits",
                "ringgits"
            ],
            "NZD" => [
                "nzd",
                "new zealand dollar",
                "new zealand dollars",
                "nz dollar",
                "nz dollars"
            ],
            "PHP" => [
                "php",
                "philippine peso",
                "philippine pesos"
            ],
            "SGD" => [
                "sgd",
                "singapore dollar",
                "singapore dollars"
            ],
            "THB" => [
                "thb",
                "thai baht",
                "baht",
                "bahts",
                "thai bahts"
            ],
            "ZAR" => [
                "zar",
                "south african rand",
                "rand",
                "rands",
                "south african rands"
            ],
            "EUR" => [
                "eur",
                "euro"
            ]
        ];

        $weights = [
            "t" => [
                "tonne",
                "t",
                "tonnes"
            ],
            "kg" => [
                "kilogram",
                "kg",
                "kilograms"
            ],
            "hg" => [
                "hg",
                "hectogram",
                "hectograms"
            ],
            "g" => [
                "g",
                "gram",
                "grams"
            ],
            "dg" => [
                "dg",
                "decigram",
                "decigrams"
            ],
            "cg" => [
                "cg",
                "centigram",
                "centigrams"
            ],
            "mg" => [
                "mg",
                "milligram",
                "milligrams"
            ],
            "µg" => [
                "µg",
                "microgram",
                "ug",
                "micrograms"
            ],
            "carat" => [
                "carat",
                "carats"
            ],
            "grain" => [
                "grain",
                "grains"
            ],
            "oz" => [
                "oz",
                "ounce",
                "ounces"
            ],
            "lb" => [
                "lb",
                "pound",
                "pounds"
            ],
            "st" => [
                "st",
                "stone",
                "stones"
            ]
        ];

        $areas = [
            "km²" => [
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
            ],
            "m²" => [
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
            ],
            "dm²" => [
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
            ],
            "cm²" => [
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
            ],
            "mm²" => [
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
            ],
            "ha" => [
                "ha",
                "hectare",
                "hectares"
            ],
            "a" => [
                "a",
                "are",
                "ares"
            ],
            "ca" => [
                "ca",
                "centiare",
                "centiares"
            ],
            "mile²" => [
                "mile²",
                "sq mile",
                "square mile",
                "sq miles",
                "square miles",
                "mile squared",
                "miles squared"
            ],
            "in²" => [
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
            ],
            "yd²" => [
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
            ],
            "ft²" => [
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
            ],
            "ro" => [
                "ro",
                "rood",
                "roods"
            ],
            "acre" => [
                "acre",
                "acre",
                "acres"
                ],
            "nautical mile²" => [
                "sq nautical mile",
                "square nautical mile",
                "sq nautical miles",
                "square nautical miles",
                "nautical mile squared",
                "nautical miles squared"
                ]
        ];


        $speeds = [
            "kmph" => [
                "kilometer per hour",
                "km/h",
                "kmph",
                "kilometers per hour"
                ],
            "mps" => [
                "m/s",
                "mps",
                "meter per second",
                "meters per second"
                ],
            "mph" => [
                "mph",
                "mi/h",
                "mile per hour",
                "miles per hour"
                ],
            "knot" => [
                "nautical mile/h",
                "knot",
                "nautical miles per hour",
                "knots",
                "nautical mile per hour",
                "nautical miles/h"
            ],
            "ma" => [
                "ma",
                "mac",
                "macs"
            ]
        ];

        $distances = [
            "km" => [
                "km",
                "kilometer",
                "kilometers"
            ],
            "m" => [
                "m",
                "meter",
                "meters"
                ],
            "dm" => [
                "dm",
                "decimeter",
                "decimeters"
            ],
            "cm" => [
                "cm",
                "centimeter",
                "centimeters"
            ],
            "mm" => [
                "mm",
                "millimeter",
                "millimeters"
            ],
            "mi" => [
                "mi",
                "mile",
                "miles"
            ],
            "in" => [
                "in",
                "inch",
                "inches"
            ],
            "ft" => [
                "ft",
                "foot",
                "feet"
            ],
            "yd" => [
                "yd",
                "yard",
                "yards"
            ],
            "nautical mile" => [
                "nautical mile",
                "nautical miles"
            ]
        ];

        $temperatures = [
            "°C" => [
                "c",
                "°c",
                "celsius"
            ],
            "°F" => [
                "f",
                "°f",
                "fahrenheit"
            ],
            "K" => [
                "k",
                "kelvin"
            ]
        ];

        $unit = strtolower($unit);

        if ( ( $key = $this->get_array_key($unit, $currencies) ) !== false ) {
            return ["currency", $key];
        }

        if ( ( $key = $this->get_array_key($unit, $weights) ) !== false ) {
            return ["weight", $key];
        }

        if ( ( $key = $this->get_array_key($unit, $areas) ) !== false ) {
            return ["area", $key];
        }

        if ( ( $key = $this->get_array_key($unit, $speeds) ) !== false ) {
            return ["speed", $key];
        }

        if ( ( $key = $this->get_array_key($unit, $distances) ) !== false ) {
            return ["distance", $key];
        }

        if ( ( $key = $this->get_array_key($unit, $temperatures) ) !== false ) {
            return ["temperature", $key];
        }

        //Throw exception
        return false;
    }

    private function distanceConvert(string $from, string $to, float $amount): float
    {
        $distanceinmeters = [
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
        ];
        return round(($amount * $distanceinmeters[$from]) / ($distanceinmeters[$to]), 4);
    }
    
    private function speedConvert(string $from, string $to, float $amount): float
    {
        $speedinkmph = [
            "kmph" => 1,
            "mps" => 3.6,
            "mph" => 1.609344,
            "knot" => 1.852,
            "ma" => 1224
        ];
        return round(($amount * $speedinkmph[$from]) / ($speedinkmph[$to]), 4);
    }

    private function areaConvert(string $from, string $to, float $amount): float
    {
        $weightinsqmt = [
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
        ];
        return round(($amount * $weightinsqmt[$from]) / ($weightinsqmt[$to]), 4);
    }

    private function tempConvert(string $from, string $to, float $amount): float
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

    private function currencyConvert(string $from, string $to, float $amount): float
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

    private function weightConvert(string $from, string $to, float $amount): float
    {
        $weightsingrams = [
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
        ];
    
        return round(($amount * $weightsingrams[$from]) / ($weightsingrams[$to]), 4);
    }

    private function parse_params(array $params_arr): array
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

        return [
            "amount" => $amount,
            "from_unit" => $from_unit,
            "to_unit" => $to_unit
        ];
    }

    public function convert(Command $command): Promise
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
