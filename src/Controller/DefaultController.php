<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * Class DefaultController
 * @package App\Controller
 */
class DefaultController extends Controller
{
    const OSM = true;
    const OSM_FILENAME = 'bdx.xml';
    const OSM_KEYS = ["amenity"];
    const OSM_VALUES = ["restaurant"];
    const OSM_NOT_KEYS = [];
    const OSM_NOT_VALUES = [];

    const HERE = true;
    const HERE_FILENAMES = ['RESTAURANTS_001.xml', 'RESTAURANTS_002.xml'];
    const HERE_CATEGORIES = ["Restaurant"];
    const HERE_LAT_MIN = 44.7779;
    const HERE_LAT_MAX = 44.8914;
    const HERE_LON_MIN = -0.6794;
    const HERE_LON_MAX = -0.4992;

    /**
     * @param string $xml
     * @return array|null
     */
    function osm_getSearchedElements(string $xml) : ?array
    {
        $element = array();
        $node = new \SimpleXMLElement($xml);
        $isSearched = false;
        $isNotSearched = false;
        foreach ($node->children() as $tag) {
            if( $tag->getName() === "tag")
            {
                if (in_array((string) $tag["k"], self::OSM_KEYS) &&
                    in_array((string) $tag["v"], self::OSM_VALUES))
                {
                    $isSearched = true;
                }
                if (in_array((string) $tag["k"], self::OSM_NOT_KEYS) &&
                    in_array((string) $tag["v"], self::OSM_NOT_VALUES))
                {
                    $isNotSearched = true;
                }
                $element[(string) $tag["k"]] = (string) $tag["v"];
            }
        }
        unset($node);
        if ($isSearched && !$isNotSearched) {
            return $element;
        }
        return null;
    }

    /**
     * @param string $xml
     * @return array|null
     */
    function here_getSearchedElements(string $xml) : ?array
    {
        $element = array();
        $place = new \SimpleXMLElement($xml);
        $isSearched = false;
        $position = $place->LocationList->Location->GeoPositionList->GeoPosition;
        if ($position->Latitude >= self::HERE_LAT_MIN && $position->Latitude <= self::HERE_LAT_MAX
            && $position->Longitude >= self::HERE_LON_MIN && $position->Longitude <= self::HERE_LON_MAX)
        {
            $base = $place->Content->Base;
            $element["categories"] = "";
            foreach($base->CategoryList->children() as $category) {
                if (in_array($category->CategoryName->Text, self::HERE_CATEGORIES)) {
                    $isSearched = true;
                }
                if ((string) $category['categorySystem'] === 'navteq-lcms') {
                    $element["categories"] .= $category->CategoryName->Text." (".$category->Description->Text.") . ";
                }
            }
            if ($isSearched) {
                $address = $place->LocationList->Location->Address->ParsedList->Parsed;
                $area = $address->Admin->AdminLevel;
                $element["name"] = $base->NameList->Name->TextList->Text->BaseText;
                $element["address"] = $address->HouseNumber." ".$address->StreetName->StreetType." "
                    .$address->StreetName->BaseName." ".$address->PostalCode." ".$area->Level4." ".$area->Level3." ".$area->Level2;
                if (!empty($base->ContactList->getName())) {
                    $element["contact"] = "";
                    foreach ($base->ContactList->children() as $contactInfo) {
                        $element["contact"] .= ucfirst(strtolower($contactInfo['type']))." : ".$contactInfo->ContactString." . ";
                    }
                }
                if (!empty($place->Content->Extended)) {
                    if (!empty($place->Content->Extended->HoursOfOperationList)) {
                        if (!empty($place->Content->Extended->HoursOfOperationList->HoursOfOperation->OperatingTimeList)) {
                            $element["opening_hours"] = "";
                            foreach ($place->Content->Extended->HoursOfOperationList->HoursOfOperation->OperatingTimeList->children() as $openingHours) {
                                $element["opening_hours"] .= ucfirst(strtolower($openingHours['dayOfweek'])) . " : " . $openingHours->OpeningTime . "-" . $openingHours->ClosingTime . " . ";
                            }
                        }
                    }
                    if (!empty($place->Content->Extended->NoteList)) {
                        $element["notes"] = "";
                        foreach ($place->Content->Extended->NoteList->children() as $note) {
                            $element["notes"] .= ucfirst(strtolower($note['type'])) . " : " .$note->Text. " . ";
                        }
                    }
                }

            }
        }
        unset($place);
        if ($isSearched) {
            return $element;
        }
        return null;
    }

    /**
     * @param string $mapKind
     * @param string $elementKind
     * @param string|null $filename
     * @return array
     */
    function readElementsKind (string $mapKind, string $elementKind, string $filename = null) : array
    {
        $elements = array();
        $xml = new \XMLReader();
        if ($mapKind !== 'OSM' && $mapKind !== 'HERE') {
            return array();
        }
        $xml->open(__DIR__.'/'.($filename ?? constant("self::".$mapKind."_FILENAME")));
        while($xml->read() && $xml->name !== $elementKind){;}
        while($xml->name === $elementKind)
        {
            $getSearchedElements = strtolower($mapKind)."_getSearchedElements";
            $element = $this->$getSearchedElements($xml->readOuterXML());
            if (!empty($element)) {
                $elements[] = $element;
            }
            $xml->next($elementKind);
        }
        $xml->close();
        unset($xml);
        return $elements;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    function index ()
    {
        ini_set('max_execution_time', 300);

        if (self::OSM) {
            $osm_elements = array();
            $osm_elements = array_merge($osm_elements, $this->readElementsKind('OSM','node'));
            $osm_elements = array_merge($osm_elements, $this->readElementsKind('OSM','way'));
            $osm_elements = array_merge($osm_elements, $this->readElementsKind('OSM','relation'));
        } else {
            $osm_elements = array(['Piscine 1'],['Piscine 2']);
        }

        if (self::HERE) {
            $here_elements = array();
            foreach (self::HERE_FILENAMES as $filename) {
                $here_elements = array_merge($here_elements, $this->readElementsKind('HERE','Place', $filename));
            }
        } else {
            $here_elements = array(['Piscine 1'],['Piscine 2']);
        }

        return $this->render('index.html.twig',
            array(  'osm_elements' => $osm_elements,
                    'here_elements' => $here_elements));
    }
}