<?php

/**
 * @author    Antonisin Max <antonisin.maxim@gmail.com>
 * @copyright 2015
 */

namespace FW\AirBundle\Helper;

use FW\AirBundle\Entity\Offer;
use FW\AirBundle\Entity\Sale;
use FW\AirBundle\Entity\OfferFlight;
use FW\AirBundle\Entity\Passanger;

/**
 * Class AirHelper
 *
 * @package FW\AirBundle\Helper
 */
class AirHelper
{
    /**
     * Doctrine Entity Manager
     *
     * @var object
     * @access private
     */
    private $em;

    /**
     * Offer array of parameters
     *
     * code   - Offer code
     * basic  - basic part of Offer code or array of flights parameters
     * second - second part of Offer code or array of flights parameters
     *
     * @access private
     * @var array
     */
    private $offerArray = ['code' => null, 'basic' => [], 'second' => false];

    /**
     * Sale array of parameters
     *
     * code       - Sale code
     * passengers - passengers strings or array of passengers parameters from Sale code
     * flights    - flights strings or array of flights parameters from Sale code
     * prices     - prices strings or array of prices parameters from Sale code
     *
     * @access private
     * @var array
     */
    private $saleArray = ['code' => null, 'passengers' => false, 'flights' => null, 'prices' => null];

    /**
     * OfferFlight array
     *
     * @access private
     * @var array of objects
     */
    private $offerFlights;

    /**
     * Warnings Array
     *
     * 'meals'=>[]
     *
     * @access private
     * @var array
     */
    private $warnings = [];

    /**
     * Error message
     *
     * @access private
     * @var string
     */
    private $errors = null;

    /**
     * __construct method
     *
     * @access public
     *
     * @param \Doctrine\ORM\EntityRepository $entityManager Doctrine Entity Manager
     */
    public function __construct($entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * Start parse a Offer string code reservation
     * Slice Offer code for all rows and return TRUE
     * Start parse a offer string
     * Set option $offerArray['code'] to Offer code $offer
     *
     * @access public
     *
     * @param  string $offer Offer code
     *
     * @return boolean TRUE
     */
    public function parseOffer($offer)
    {
        $offer = $this->fixCode($offer);
        $this->setOffer($offer);
        $this->sliceOffer($offer);
        $this->setOfferFlights();

        if ($this->getErrors() == null) {
            return true;
        } else {
            return $this->getErrors();
        }
    }


    /**
     * Start parse a Sale string code reservation
     * Slice Sale code for all rows like Passengers, Flights and prices
     * Set option $saleArray['code'] to Sale code $sale
     *
     * @access public
     *
     * @param  string $sale Sale code
     */
    public function parseSale($sale)
    {
        $sale = $this->fixCode($sale);
        $this->setSale($sale, 'code');
        $this->slicePassengers($sale);
        $this->sliceFlights($sale);
        $this->slicePrices($sale);
        $this->validateCode();
    }

    /**
     * Fix Offer/Sale string code for normal form and new lines
     * Erase all double spaces and new lines where need. Return this fixed code.
     *
     * @access public
     *
     * @param  string $code Offer/Sale code
     *
     * @return string $code  Offer/Sale code
     */
    public function fixCode($code)
    {
        /** Remove more then 1 spaces */
        $code = preg_replace("#\040{2,}#", " ", $code);

        /** Remove new line (\n) when it need */
        $code = preg_replace("#\012(\040{1,}?\057\S{1,})#", '$1', $code);



        return $code;
    }

    /**
     * Fix Offer/Sale string code for normal form and new lines
     * Erase all double spaces and new lines where need.
     * Erase All spaces in start of string. Erase all empty new line
     *
     * @access public
     *
     * @param string $code
     *
     * @return string $code
     */
    public function fixCodeAdvanced($code)
    {
        /** Remove more then 1 spaces */
        $code = preg_replace("#\040{2,}#", " ", $code);

        /** Remove new line (\n) when it need */
        $code = preg_replace("#\012(\040{1,}?\057\S{1,})#", '$1', $code);

        /** Remove Double new line */
        $code = preg_replace("#\012{2,}#", "\012", $code);

        /** Remove every spaces in start of row  */
        $code = preg_replace("#\012\040{1,}#", "\012", $code);

        /** Remove spaces in start of code */
        $code = preg_replace("#^[\012, \040]{1,}#", "", $code);

        return $code;
    }

    /**
     * Cut Offer code to basic/second options
     * Offer code may be extended by second code.
     * In this case Offer code is formatted from 2 parts: Basic and Second, divided by special string (ex.VI*«).
     *
     * Ex.:
     *    1 LH9694Y 12JUN 5 FRAADD SS1  2205  0615   13JUN 6 /DCLH /E
     *    VI*«
     *    1 LH*9694 12JUN FRA ADD 2205  0615 ‡1 M    788  7.10  3324  N
     *
     * [basic] - array of rows
     * [VI\*«] - string (example)
     * [second]  - array of rows
     *
     * @access private
     *
     * @param string $offer offer string
     */
    private function sliceOffer($offer)
    {
        /* Slice Offer code by rows when is new line symbol (\n) */
        $rows = preg_split("#[\\n,]+#", $offer);

        /*  For-each all Offer code rows for find a special string (ex.VI*«)
            and divided this Offer code in 2 parts: Basic ans Second */
        foreach ($rows as $index => $row) {
            if (preg_match('#\S?\052?VI\*?\S*#', $row)) {
                $this->setOfferBasic(array_slice($rows, 0, $index));
                $this->setSecond(array_slice($rows, $index + 1));
                break;
            }
        }

        /* Set option $Basic if special string (ex. VI\*«) is missing */
        if (empty($this->getBasic()) or empty($this->getSecond())) {
            $this->setOfferBasic($rows);
        }
    }

    /**
     * Parse Flights array of rows
     * For every item from income flight array, find all needed flight parameters. Return an array of flight parameters
     *
     * (1) ? (2) ? (3) \s (4) ? (*5) \s (6) ? (7) ? (*8) \s (9) \s (10) \s (*11)
     * 1 - order number (digital)
     *     (ex. 1, 6, 25)
     * 2 - airline code (1 - 3 symbol)(often 2 symbols)
     *     (ex. 9U, S7)
     * 3 - flight number (1 - 4 symbol) + if exist booking class
     *     (ex. 7777 - only flight number, 173V - flight number 173 + V booking class)
     * 4 - start date (4 - 5 symbol)
     *     (ex. 20JUN, 15DEC)
     * 5 - number of the day of the week (digital range [1 - 7])
     *     (ex. 5,4,1)
     *     * not required
     * 6 - airport from (3 symbol)
     *     (ex. KIV, DME)
     * 7 - airport to (3 symbol)
     *     (ex. DME, KIV)
     * 8 - segment sold + number of booking for this segment (3 symbols)
     *     (ex. SS1, BB2)
     *     * not required
     * 9 - time from (3 - 4 digital)
     *     (ex. 0514, 2103, 317)
     * 10 - time to (3 - 4 digital)
     *     (ex. 0514, 2103, 317)
     * 11 - system information (all remain code)
     *     (ex. /E, /DC9U, /AF4C /E)
     * \s - space
     * ? - space may be absent
     * * - group may be absent
     * (...) - group
     *
     * Ex. (1) (LH)(9694Y) (12JUN) (5) (FRA)(ADD) (SS1)  (2205)  (0615)   (13JUN 6 /DCLH /E)
     *
     * @access private
     *
     * @param  array $rows array of flights
     *
     * @return array  $flights  array of flights with parameters
     */
    private function parseFlights(array $rows)
    {
        /* Default */
        $flights = null;
        $pattern = "#(\d)\s?(\S{2})\s?(\S{1,5})\s(\S{4,5})\s?(\d?)\s(\S{3})\s?(\S{3})\s?\S?(\S{3})?\s(\d{4})\s(\d{4})\s(.*)#";
        foreach ($rows as $index => $row) {
            if (preg_match($pattern, trim($row), $rows[$index])) {
                $flights[] = $rows[$index];
            }
        }

        return $flights;
    }

    /**
     * Slice Second array of flights from Offer code
     * Match flights parameters for every item from array ($rows). Income array ($rows) is formatted from every flight
     * from Second part or Offer code of reservation. Formatted an array of flights parameters and set them in option
     * $offerArray['second'].
     *
     * @access private
     *
     * @param array $rows Second rows of Offer code
     */
    private function setSecond(array $rows)
    {
        /* Default*/
        $second = null;
        $pattern = '#(\d)[\040,\052](\S{2})[\040,\052](\S{1,5})\s(\S{4,5})\s?(\d?)\s(\D{3})\s?(\D{3})\s?(\S{3})?\s(\d{4})\s(\d{4})\s?(\S{2,3}\d)?\s?(\D{1,3})?\s?(\S{3}?)\s?(\d{1,2}[\056]\d{2})?\s?(\d{2,4}?)\s(\D?)#';

        foreach ($rows as $index => $row) {
            if (preg_match($pattern, trim($row).' ', $rows[$index])) {
                $second[] = $rows[$index];
            }
        }
        $this->setOfferSecond($second);
    }

    /**
     * Match all Flights rows from Sale code
     * Find every string in Sale code ($code) where is flight parameters. Set all found strings by array in option
     * $saleArray['flights'].
     *
     * @access private
     *
     * @param  string $code Sale code
     */
    private function sliceFlights($code)
    {
        $pattern = '#\d\040\S{2}\040?\S{3,5}\040\S{4,5}\040?\d?\040[a-zA-Z]{6}.*\d{4}.*#';
        preg_match_all($pattern, trim($code), $array);
        if (!empty($array[0])) {
            $this->setSaleFlights($array[0]);
        }
    }

    /**
     * Match all Passengers rows from Sale code
     * Find every string in Sale code ($code) where is passengers parameters. Set all found strings by array in option
     * $saleArray['passengers'].
     *
     * @access private
     *
     * @param  string $code Sale code
     */
    private function slicePassengers($code)
    {
        $pattern = '#\d\056\d[a-zA-Z]{2,}\057[a-zA-Z]{2,}#';
        preg_match_all($pattern, trim($code), $array);
        if (!empty($array[0])) {
            $this->setSalePassengers($array[0]);
        }
    }

    /**
     * Match all Prices rows from Sale code
     * Find every string in Sale code ($code) where is prices parameters. Set all found string by array in option
     * $saleArray['prices'].
     *
     * @access private
     *
     * @param  string $code Sale code
     */
    private function slicePrices($code)
    {
        $pattern = '#\d\056\w\d{1,}\040\d{2,}\056?\040?\d{1,2}?.*#';
        preg_match_all($pattern, trim($code), $array);

        if (!empty($array[0])) {
            $this->setSalePrices($array[0]);
        }
    }

    /**
     * Slice array of Passengers for parameters. Set array of passengers parameters in $saleArray['passengers']
     * Match passengers parameters for every item from array ($rows). Income array ($rows) is formatted from every
     * passenger from Sale code reservation. Formatted an array of passengers parameters and set them in option
     * $saleArray['passangers'].
     *
     * (1) . (2) (3) \ (4) ? (*5)
     * 1 - order number
     * 2 - ??????????
     * 3 - first name
     * 4 - last name
     * 5 - name (not required)
     * ? - space may be absent
     * * - group may be absent
     *
     * Ex.
     *   (1).(1)(CHERNOVA)/(LIUDMILA) (BAT)
     *
     * @access private
     *
     * @param array $rows Array of Passengers
     */
    private function setSalePassengers(array $rows)
    {
        /* Defaults */
        $passengers = null;
        $pattern = "#(\d)\056(\d)([a-zA-Z]{2,})\057([a-zA-Z]{2,})\040?(\D{2,})?()\z#";
        foreach ($rows as $index => $row) {
            if (preg_match($pattern, trim($row), $rows[$index])) {
                $passengers[] = $rows[$index];
            }
        }
        $this->setSale($passengers, 'passengers');
    }

    /**
     * Slice array of Prices for parameters. Set array of prices parameters in $saleArray['prices']
     * March price parameters for every item from array ($rows).
     * Income array ($rows) is formatted from every price from Sale code reservation.
     * Formatted an array of prices parameters and set them in option $saleArray['prices'].
     *
     * (1) (2) (3) (4)
     * 1 - price to sale
     * 2 - pure price
     * 3 - tariff
     * 4 - quantity
     *
     * Ex.
     *   1.S1 (8917.00) N1 (7898.00) F1 (7000.00) Q1 (1.00)
     *
     * @access private
     *
     * @param array $rows array of rows of prices from sale code
     */
    private function setSalePrices(array $rows)
    {
        $prices = [];
        $pricePat = '(\d{2,})\056?\d{2}?'   ;
        $indexPat = '\d{1,2}';
        $casePat = '\w\d{1,2}';
        $pattern = '#\040{0,}'.$indexPat.'\056'.$casePat.'\040'.$pricePat.'\040'.$casePat.'\040'.$pricePat.'\040'.$casePat.'\040'.$pricePat.'#';

        foreach ($rows as $index => $row) {
            if (preg_match($pattern, trim($row), $rows[$index])) {
                $prices[] = $rows[$index];
            }
        }

        $this->setSale($prices, 'prices');
    }

    /**
     * Initialize to create Passengers and offer flight objects
     * For every Passenger and every Flight create Entity Object and set parameters.
     *
     * Return array in format:
     *     [
     *       'passengers' => [object, object, ... , object],
     *       'flights'    => [object, object, ... , object]
     *     ]
     *
     * @access public
     *
     * @param  Sale $sale Sale entity object
     *
     * @return array  Objects array of passengers and flights
     */
    public function createObjects(Sale $sale)
    {
        $passengers = $this->createPassengerObjects($sale);
        $flights = $this->createFlightObjects($sale);

        return ['passengers' => $passengers, 'flights' => $flights];
    }

    /**
     * Create Passengers Objects
     * Create an array of Passengers Objects for every passenger from Sale code.
     *
     * Return array in format:
     *     [ object, object, ... , object ]
     *
     * @access private
     *
     * @param  Sale $sale Sale Entity Object
     *
     * @return array    Array of passengers Objects
     */
    private function createPassengerObjects(Sale $sale)
    {
        /** Defaults */
        $return = null;

        foreach ($this->getSale()['passengers'] as $index => $passenger) {
            $passengerEntity = new Passanger();
            $passengerEntity->setFirstName($passenger[3])
                ->setLastName($passenger[4])
                ->setSale($sale);

            $passengerEntity = $this->preparePassengerPrices($passengerEntity, $index);
            $return[] = $passengerEntity;
        }

        return $return;
    }

    /**
     * Create Flight Objects
     * Create an array of Flights Objects for every flight from Sale code.
     *
     * Return array in format:
     *      [ object, object, ... , object ]
     *
     * @access private
     *
     * @param  Sale $sale Sale Entity Object
     *
     * @return array Array of flights Objects
     */
    private function createFlightObjects(Sale $sale)
    {
        /** Defaults */
        $return = null;

        foreach ($this->getSale()['flights'] as $index => $offerFlight) {
            $flight = new OfferFlight();
            $flight
                ->setSale($sale)
                ->setAirline($this->prepareAirline($offerFlight[2]))
                ->setStartLocation($this->prepareLocation($offerFlight[6]))
                ->setEndLocation($this->prepareLocation($offerFlight[7]))
                ->setStartDate($this->formatDateTime($offerFlight[4], $offerFlight[9]))
                ->setEndDate($this->formatDateTime($offerFlight[4], $offerFlight[10]))
                ->setDay($offerFlight[5])
                ->setSits(null)
                ->setFoodType(null)
                ->setAirplaneType(null)
                ->setIsEconom($this->prepareMatch("#\057E#", $offerFlight[11]))
                ->setIsBusiness(0)
                ->setAirtime(null)
                ->setName($this->prepareFlightNumber($offerFlight[3]))
                ->setReservationCode($this->prepareReservationCode($offerFlight[11]));

            $return[] = $flight;
        }

        return $return;
    }

    /**
     * Set Offer Object(id) for every Flights from Sale code and flush all Flights in DB
     *
     * @access public
     *
     * @param  Offer $offer Offer Object
     */
    public function flushOfferFlights(Offer $offer)
    {
        foreach ($this->getOfferFlights() as $index => $object) {
            $this->getOfferFlights()[$index]->setOffer($offer);
            $this->em->persist($this->getOfferFlights()[$index]);
        }

        $this->em->flush();
    }

    /**
     * Set Basic Offers flights parameters for every flights in Offer code
     *
     * @access private
     */
    private function setBasicOfferFlights()
    {
        foreach ($this->getBasic() as $index => $array) {
            $flight = new OfferFlight();

            $flight
                ->setName($array[3])
                ->setAirline($this->prepareAirline($array[2]))
                ->setStartLocation($this->prepareLocation($array[6]))
                ->setEndLocation($this->prepareLocation($array[7]))
                ->setDay($array[5])
                ->setSits(null)
                ->setFoodType(null)
                ->setAirplaneType(null)
                ->setIsBusiness(0)
                ->setIsEconom($this->prepareMatch("#\057E#", $array[11]))
                ->setAirtime(null)
                ->setSale(null) //temp
                ->setStartDate($this->formatDateTime($array[4], $array[9]))
                ->setEndDate($this->formatDateTime($array[4], $array[10]));

            /** If Offer code have second part set offerFlights[] by calling method setStaticOfferFlights()
             * else set offerFlights simply */
            if ($this->getSecond() != false and !empty($this->getSecond()[$index])) {
                $this->offerFlights[] = $this->setStaticOfferFlights($flight, $this->getSecond()[$index]);
            } else {
                $this->offerFlights[] = $flight;
            }
        }
    }

    /**
     * Set Second Offer flights parameters for every flights
     *
     * @access private
     *
     * @param OfferFlight $flight
     * @param array       $array Flight parameters
     *
     * @return OfferFlight Offer flight object
     */
    private function setStaticOfferFlights(OfferFlight $flight, array $array)
    {
        $flight
            ->setFoodType($this->prepareFoodType($array[12]))
            ->setAirplaneType($this->prepareAirlineType($array[13]))
            ->setAirtime(
                strlen($array[14]) != 5 ? '0'.str_replace(".", ":", $array[14]) : str_replace(".", ":", $array[14])
            )
            ->setDistance($array[15] * 1.609344);

        return $flight;
    }

    /**
     * Set Offer second flights parameters
     * Set flights parameters ($array) of Offer code in option $offerArray['second'].
     * Income array ($array) of Offer Flights parameters.
     *
     * @access private
     *
     * @param array $array second rows of offer
     */
    private function setOfferSecond(array $array)
    {
        $this->offerArray['second'] = $array;
    }

    /**
     * Set Basic part of offer flights
     *
     * @access private
     *
     * @param array $rows basic rows of offer
     */
    private function setOfferBasic(array $rows)
    {
        $this->offerArray['basic'] = $this->parseFlights($rows);
    }

    /**
     * Set array of Flights parameter in $saleArray['flights']
     * Catch formatted array of flights parameters from method parseFlights() and set it in option
     * $saleArray['flights'].
     *
     * @access private
     *
     * @param array $rows array of rows of flights
     */
    private function setSaleFlights(array $rows)
    {
        $this->setSale($this->parseFlights($rows), 'flights');
    }

    /**
     * Set option $saleArray with index ($index) by value ($value).
     *
     * @access private
     *
     * @param        string /array $value   Value to set in $saleArray
     * @param string $index Index to set in $saleArray
     */
    private function setSale($value, $index)
    {
        $this->saleArray[$index] = $value;
    }

    /**
     * Return $saleArray
     *
     * @access public
     * @return array Sale array
     */
    public function getSale()
    {
        return $this->saleArray;
    }

    /**
     * Return value from $saleArray by index
     *
     * @param $index
     * @access public
     * @return mixed
     */
    private function getSaleIndex($index)
    {
        return $this->saleArray[$index];
    }

    /**
     * Set Offer code reservation
     *
     * @access private
     *
     * @param string $offer Offer code reservation
     */
    private function setOffer($offer)
    {
        $this->offerArray['code'] = $offer;
    }

    /**
     * Return $offerArray
     *
     * @access public
     * @return array Offer array
     */
    public function getOffer()
    {
        return $this->offerArray;
    }

    /**
     * Set Offers flights
     *
     * @access private
     */
    private function setOfferFlights()
    {
        if ($this->getBasic()) {
            $this->setBasicOfferFlights();
        } else {
            $this->setErrors('Не правельнный PNR!');
        }
    }

    /**
     * Return array of Offers flights
     *
     * @access private
     * @return OfferFlight[] array of offerFlights
     */
    private function getOfferFlights()
    {
        return $this->offerFlights;
    }

    /**
     * Return option basic
     *
     * @access private
     * @return array basic rows of offer
     */
    public function getBasic()
    {
        return $this->offerArray['basic'];
    }

    /**
     * Return option second
     *
     * @access private
     * @return array second rows of offer
     */
    private function getSecond()
    {
        return $this->offerArray['second'];
    }

    /**
     * Set value of warning with index
     *
     * Ex. $warning = [ 'airline' = ['231', '441'], 'meals' = [ 'M', 'BLS' ] ]
     *
     * @access private
     *
     * @param        string /integer $value value to insert in array with index $index
     * @param string $index index to set a value
     */
    private function setWarning($value, $index)
    {
        $this->warnings[$index][] = $value;
    }

    /**
     * Return an array of warnings message
     *
     * Ex. $warning = [ 'airline' = ['231', '441'], 'meals' = [ 'M', 'BLS' ] ]
     *
     * @access public
     * @return array Array of warnings cases
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Set an error message
     *
     * @access private
     * @param $string
     */
    private function setErrors($string)
    {
        $this->errors = $string;
    }

    /**
     * Return an error message
     *
     * @access public
     * @return string
     */
    private function getErrors()
    {
        return $this->errors;
    }

    /**
     * Format DateTme Function
     *
     * @param string $day  Day String
     * @param string $time Time String
     *
     * @access public
     *
     * @return \DateTime
     */
    private function formatDateTime($day, $time)
    {
        $date = \DateTime::createFromFormat("dM H:i:s e",
            $day." ".substr($time, 0, 2).":".substr($time, 2).":00 +00:00"
        );

        return $date;
    }

    /**
     * Prepare Boolean Match for ex. for setIsEconom
     *
     * @param integer $pattern Preg_Match Pattern
     * @param string $string   String for Prepare Data
     * @access public
     * @return int
     */
    private function prepareMatch($pattern, $string)
    {
        if (preg_match($pattern, $string) ) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Prepare Airline code
     * If it exist in DB return an airline object else return an NULL
     *
     * @access private
     *
     * @param  string $code Airline code
     *
     * @return \FW\AirBundle\Entity\Airline     airline object or NULL
     */
    private function prepareAirline($code)
    {
        $airline = $this->em->getRepository('FWAirBundle:Airline')->findOneBy(['code' => $code]);
        if ($airline) {
            return $airline;
        } else {
            $this->setWarning($code, 'airline');

            return null;
        }
    }

    /**
     * Prepare Location code
     * If it exist in DB return an location object else return NULL
     *
     * @access private
     *
     * @param  string $code Location code
     *
     * @return \FW\AirBundle\Entity\Location    location object or NULL
     */
    private function prepareLocation($code)
    {
        $location = $this->em->getRepository('FWAirBundle:Location')->findOneBy(['code' => $code]);
        if ($location) {
            return $location;
        } else {
            $this->setWarning($code, 'location');

            return null;
        }
    }

    /**
     * Prepare FoodType code
     * If it exist in DB return an FoodType object else NULL
     *
     * @access private
     *
     * @param  string $code FoodType code
     *
     * @return \FW\AirBundle\Entity\FoodType FoodType object or NULL
     */
    private function prepareFoodType($code)
    {
        $foodType = $this->em->getRepository('FWAirBundle:FoodType')->findOneBy(['code' => $code]);
        if ($foodType) {
            return $foodType;
        } else {
            $this->setWarning($code, 'foodType');

            return null;
        }
    }

    /**
     * Prepare AirlineType code
     * If it exist in DB return an AirlineType object else NULL
     *
     * @access private
     *
     * @param  string $code AirlineType code
     *
     * @return \FW\AirBundle\Entity\AirplaneType  AirlineType object or NULL
     */
    private function prepareAirlineType($code)
    {
        $airlineType = $this->em->getRepository('FWAirBundle:AirplaneType')->findOneBy(['code' => $code]);
        if ($airlineType) {
            return $airlineType;
        } else {
            $this->setWarning($code, 'airlineType');

            return null;
        }
    }

    /**
     * Prepare Offer/Sale Flight Reservation Code
     *
     * @access private
     *
     * @param  string $code Part of sale/offer string code with reservation code
     *
     * @return string/null Returns string reservation code or null
     */
    private function prepareReservationCode($code)
    {
        $pattern = '#\S{5}\052?(\S{2,})#';
        preg_match($pattern, $code, $reservationCode);
        if (!empty($reservationCode)) {
            return $reservationCode[1];
        } else {
            return null;
        }
    }

    /**
     * Prepare Offer/Sale Flight Number code
     *
     * @access private
     *
     * @param string $code Flight Number code
     *
     * @return string/null return flight number or null
     */
    private function prepareFlightNumber($code)
    {
        preg_match('#\d{1,}#', $code, $number);
        if (!empty($number)) {
            return $number[0];
        } else {
            return null;
        }
    }

    /**
     * Prepare Offer/Sale General Reservation Code
     *
     * @access private
     * @return string/null  general reservation code or null
     */
    public function prepareReservationCodeGeneral()
    {
        preg_match_all('#\d{3,4}\057\S{4,5}\d{2,}\040([a-zA-Z]{1,})#', $this->getSale()['code'], $reservationCode);
        if (!empty($reservationCode) and !empty($reservationCode[1])) {
            return $reservationCode[1][0];
        } else {
            return null;
        }
    }

    /**
     * Set price for every passenger from the sale code
     * Ex:
     *  (3).(S2) (22300) (N2) (17411) (F2) (15230)
     *
     *  1.Price index (is same like passenger) (Ex. 3)
     *  2. Total price label (Ex. S2)
     *  3. Total price value (Ex. 22300)
     *  4. Pure price label (Ex. N2)
     *  5. Pure price value (Ex. 17411)
     *  6. Tariff price label (Ex. F2)
     *  7. Tariff price value (Ex. 15230)
     *
     * @access private
     *
     * @param Passanger $passenger
     * @param integer   $index
     *
     * @return Passanger
     */
    private function preparePassengerPrices(Passanger $passenger, $index)
    {
        $prices = $this->getSale()['prices'];

        if (!empty($prices) and !empty($prices[$index])) {
            $passenger->setTotal($prices[$index][1])
                ->setNet($prices[$index][2])
                ->setFee($prices[$index][3])
                ->setMarkup($prices[$index][1] - $prices[$index][2]);
        }

        return $passenger;
    }

    private function validateCode()
    {
        /** Validate number of passengers and number of tickets prices
            count(prices) != count(passengers) */
        if (count($this->getSaleIndex('prices')) != count($this->getSaleIndex('passengers'))) {
            $this->setWarning(001, 'stack');
        }

        /** Validate number of passengers
            count(passengers) == 0 */
        if (count($this->getSaleIndex('passengers')) == 0) {
            $this->setWarning(002, 'stack');
        }

        /** Validate number of flights
            count(flights) == 0 */
        if (count($this->getSaleIndex('flights')) == 0 ) {
            $this->setWarning(003, 'stack');
        }
    }
}