<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;
use function Room11\DOMUtils\domdocument_load_xml;

class RequestFailedException extends \Exception {}

class InvalidParametersException extends \Exception {}

class Convert extends BasePlugin
{

    private $chatClient;
    private $httpClient;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getArrayKey(string $needle, array $haystack): string
    {
        foreach ($haystack as $key => $item) {

            if (in_array($needle, $item)) {

                return $key;

            }

        }
        
        return '';
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

        if ( !empty( $key = $this->getArrayKey($unit, $currencies) ) ) {

            return ["currency", $key];

        }

        if ( !empty( $key = $this->getArrayKey($unit, $weights) ) ) {

            return ["weight", $key];

        }

        if ( !empty( $key = $this->getArrayKey($unit, $areas) ) ) {

            return ["area", $key];

        }

        if ( !empty( $key = $this->getArrayKey($unit, $speeds) ) ) {

            return ["speed", $key];

        }

        if ( !empty( $key = $this->getArrayKey($unit, $distances) ) ) {

            return ["distance", $key];

        }

        if ( !empty( $key = $this->getArrayKey($unit, $temperatures) ) ) {

            return ["temperature", $key];

        }

        throw new InvalidParametersException("Unit type does not match any registered unit types.");
    }

    private function distanceConvert(string $from, string $to, float $amount): float
    {
        $distanceAsMeters = [
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

        return round(($amount * $distanceAsMeters[$from]) / ($distanceAsMeters[$to]), 4);
    }
    
    private function speedConvert(string $from, string $to, float $amount): float
    {
        $speedAsKmph = [
            "kmph" => 1,
            "mps" => 3.6,
            "mph" => 1.609344,
            "knot" => 1.852,
            "ma" => 1224
        ];

        return round(($amount * $speedAsKmph[$from]) / ($speedAsKmph[$to]), 4);
    }

    private function areaConvert(string $from, string $to, float $amount): float
    {
        $areaAsSqmt = [
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

        return round(($amount * $areaAsSqmt[$from]) / ($areaAsSqmt[$to]), 4);
    }

    private function tempConvert(string $from, string $to, float $amount): float
    {
        switch ($from) {
            case "°F":
                $temperatureAsKelvin = ((($amount - 32) * 5) / 9) + 273.15;
                break;
            case "°C":
                $temperatureAsKelvin = ($amount + 273.15);
                break;
            case "K":
                $temperatureAsKelvin = $amount;
                break;
        }

        switch ($to) {
            case "°F":
                $convertedTemperature = (((9/5) * ($temperatureAsKelvin - 273.15)) + 32);
                break;
            case "°C":
                $convertedTemperature = ($temperatureAsKelvin - 273.15);
                break;
            case "K":
                $convertedTemperature = $temperatureAsKelvin;
                break;
        }

        return round($convertedTemperature, 2);
    }

    private function currencyConvert(string $from, string $to, float $amount)
    {    
        /** @var HttpResponse $response */
        $response = $this->httpClient->request('http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');

        if ($response->getStatus() !== 200) {

            throw new RequestFailedException('Error code ' . $response->getStatus() . 'when requesting xml page.');

        }

        $dom = domdocument_load_xml($response->getBody());

        $xpath = new \DOMXPath($dom);

        $fromRate = $this->getRate($from, $xpath);

        $toRate = $this->getRate($to, $xpath);

        return (($amount / $fromRate) * ($toRate));

    }

    private function weightConvert(string $from, string $to, float $amount): float
    {
        $weightAsGrams = [
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
    
        return round(($amount * $weightAsGrams[$from]) / ($weightAsGrams[$to]), 4);
    }

    private function parseParams(array $paramsArr): array
    {
        if (is_numeric($paramsArr[0])) {
    
            $amount = (int) $paramsArr[0];
            array_shift($paramsArr);
    
        } else if (is_numeric($paramsArr[0][0])) {
    
            $firstParamArr = preg_split('/(?=[^0-9\.\,])/', $paramsArr[0], 2);
            $amount = (int) $firstParamArr[0];
            $paramsArr[0] = $firstParamArr[1];
    
        } else {

            $amount = 1;

        }

        if ( ( $toPosition = array_search('to', $paramsArr) ) === false) {

            throw new InvalidParametersException("The from unit type and to unit type do not match.");

        }
    
        $fromUnit = implode(" ", array_slice($paramsArr, 0, $toPosition));
        $toUnit = implode(" ", array_slice($paramsArr, $toPosition + 1));

        return [
            "amount" => $amount,
            "from_unit" => $fromUnit,
            "to_unit" => $toUnit
        ];
    }

    public function convert(Command $command): Promise
    {
        $paramsArr = $command->getParameters();

        try {

            $parsedParams = $this->parseParams($paramsArr);

        } catch (\InvalidParametersException $e) {

            return $this->chatClient->postReply(
                $command,
                "I'm really not sure what you want me to do..."
            );

        }

        if ($parsedParams['amount'] === 0) {

            return $this->chatClient->postReply(
                $command,
                "Yeah let me just divide this by 0 and..."
            );

        }

        try {

            $fromUnitArr = $this->getUnitType($parsedParams['from_unit']);
            $toUnitArr = $this->getUnitType($parsedParams['to_unit']);

        } catch (\InvalidParametersException $e) {

            return $this->chatClient->postReply(
                $command,
                "They didn't teach me that one yet."
            );

        }

        if ( ( $fromType = $fromUnitArr[0] ) !== ( $toType = $toUnitArr[0] ) ) {

            return $this->chatClient->postReply(
                $command, 
                "What?? They never taught me how to convert " . $fromType . " to " . $toType . "."
            );

        }

        switch($fromType) {
            case 'temperature':
                $convertedToAmount = $this->tempConvert(
                    $fromUnitArr[1], 
                    $toUnitArr[1], 
                    $parsedParams['amount']
                );
                break;

            case 'currency':
                $parsedParams['amount'] = number_format($parsedParams['amount'], 2, '.', ',');

                try {

                    $convertedCurrency = $this->currencyConvert(
                        $fromUnitArr[1],
                        $toUnitArr[1],
                        $parsedParams['amount']
                    );

                } catch (\RequestFailedException $e) {

                    $message = "I don't know what's going on but I can't find that information.";

                    return $this->chatClient->postReply(
                        $command, 
                        $message
                    );

                }
                
                $convertedToAmount = number_format($convertedCurrency, 2, '.', ',');

                break;

            case 'weight':
                $convertedToAmount = $this->weightConvert(
                    $fromUnitArr[1],
                    $toUnitArr[1],
                    $parsedParams['amount']
                );
                break;

            case 'area':
                $convertedToAmount = $this->areaConvert(
                    $fromUnitArr[1],
                    $toUnitArr[1], 
                    $parsedParams['amount']
                );
                break;

            case 'speed':
                $convertedToAmount = $this->speedConvert(
                    $fromUnitArr[1], 
                    $toUnitArr[1], 
                    $parsedParams['amount']
                );
                break;

            case 'distance':
                $convertedToAmount = $this->distanceConvert(
                    $fromUnitArr[1], 
                    $toUnitArr[1], 
                    $parsedParams['amount']
                );
        }

        $sprintfFormat = '%s%s = %s%s';

        $message = sprintf(
            $fromUnitArr,
            $parsedParams['amount'],
            $fromUnitArr[1],
            $convertedToAmount,
            $toUnitArr[1]
        );

        return $this->chatClient->postReply(
            $command, 
            $message
        );
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
