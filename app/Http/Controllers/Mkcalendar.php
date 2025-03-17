<?php

namespace App\Http\Controllers;

use App\Models\Base\Controller;
use App\Models\Db\Calendar;
use App\Models\Db\PropNS;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class Mkcalendar extends Controller
{
    public function index(Request $request, string $username){
        if($username != session('username')) {
            return response('', Response::HTTP_FORBIDDEN);
        }
        $uri = $request->getRequestUri();
        $dbCalendar = Calendar::getInstance();
        $info = $dbCalendar->getBaseInfoByUri($uri);
        if(!empty($info)){
            return response('', Response::HTTP_BAD_REQUEST);
        }
        $this->request = $request;
        $prop = $this->getSetProp();
        $dbCalendar = Calendar::getInstance();
        $dbCalendar->addCalendar(['uri' => $uri], $prop);
        return response('', Response::HTTP_CREATED);
    }

    public function getSetProp() {
        $xml = $this->request->getContent();
        $ns = $this->getNameSpaceFromXml($xml);
        $replace = [];
        foreach ($ns as $prefix => $uri) {
            $replace['<'.$prefix.':']  = '<'.PropNS::getPrefixByUri($uri).':';
            $replace['</'.$prefix.':'] = '</'.PropNS::getPrefixByUri($uri).':';
        }
        $xml = str_replace(array_keys($replace), array_values($replace), $xml);
        $dPrefix = array_search('DAV:', $ns);
        $cPrefix = array_search('urn:ietf:params:xml:ns:caldav', $ns);
        $xml = str_replace([$dPrefix . ':prop', $cPrefix . ':comp'], ['prop', 'c:comp'], $xml);
        $result = [];
        if (preg_match('/<prop>(.*?)<\/prop>/is', $xml, $propMatches)) {
            preg_match_all('/<([^>\/\s]+)(\s[^>\/\s]+\s*)*>(.*?)<\/\1>/is', $propMatches[1], $matches);
            for ($i = 0; $i < count($matches[1]); $i++) {
                $tagName = $matches[1][$i];
                if(!str_contains($tagName, ':')) {
                    $tagName = 'd:' . $tagName;
                } else {
                    [$prefix, $localName] = explode(':', $tagName, 2);
                    $tagName = PropNS::getPrefixByUri($ns[$prefix]) . ':' . $localName;
                }
                $tagValue = trim($matches[3][$i]);
                if(str_starts_with($tagValue, '<![CDATA[') && str_ends_with($tagValue, ']]>')) {
                    $tagValue = trim(substr($tagValue, 9, -3));
                }
                $result[$tagName] = $tagValue;
            }
        }
        return $result;
    }
}