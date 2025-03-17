<?php

namespace App\Http\Controllers;

use App\Models\Base\Controller;
use App\Models\Base\Db;
use App\Models\Db\Calendar;
use App\Models\Db\Comp;
use App\Models\Db\PropNS;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PropPatch extends Controller
{
    public function index(Request $request, string $username)
    {
        if($username != session('username')) {
            return response(null,Response::HTTP_FORBIDDEN);
        }
        $uri = $request->getRequestUri();
        $this->request = $request;
        $prop = $this->getPatchProp();
        if (empty($prop['set']) && empty($prop['remove'])) {
            return response(null, Response::HTTP_BAD_REQUEST);
        }
        try{
            $dbCalendar = Calendar::getInstance();
            if(in_array(substr($uri, -4), ['.ics'])) {
                $dbComp = Comp::getInstance();
                $info = $dbComp->getBaseInfoByUri($uri);
                if (empty($info)) {
                    return response(null,Response::HTTP_NOT_FOUND);
                }
                $info['prop'] = json_decode($info['prop'], true);
                if (!empty($prop['set'])) {
                    $info['prop'] = array_merge($info['prop'], $prop['set']);
                }
                if (!empty($prop['remove'])) {
                    $info['prop'] = array_diff_key($info['prop'], $prop['remove']);
                }
                Db::beginTransaction();
                $dbComp->updateProp($info['id'], $info['prop']);
                $dbCalendar->updateEtag($info['calendar_id']);
                Db::commit();
            } else {
                $info = $dbCalendar->getBaseInfoByUri($uri);
                if (empty($info)) {
                    return response(null,Response::HTTP_NOT_FOUND);
                }
                $info['prop'] = json_decode($info['prop'], true);
                if (!empty($prop['set'])) {
                    $info['prop'] = array_merge($info['prop'], $prop['set']);
                }
                if (!empty($prop['remove'])) {
                    $info['prop'] = array_diff_key($info['prop'], $prop['remove']);
                }
                $dbCalendar->updateProp($info['id'], $info['prop']);
            }
        } catch(\Exception $e) {
            if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response(null,Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return response(null,Response::HTTP_OK);
    }

    protected function getPatchProp() {
        $xml = $this->request->getContent();
        $ns = $this->getNameSpaceFromXml($xml);
        $dPrefix = array_search('DAV:', $ns);
        $xml = str_replace([$dPrefix . ':set', $dPrefix . ':remove', $dPrefix . ':prop'], ['set', 'remove', 'prop'], $xml);
        $replace = [];
        foreach ($ns as $prefix => $uri) {
            $replace['<'.$prefix.':']  = '<'.PropNS::getPrefixByUri($uri).':';
            $replace['</'.$prefix.':'] = '</'.PropNS::getPrefixByUri($uri).':';
        }
        $xml = str_replace(array_keys($replace), array_values($replace), $xml);
        $prop = [];
        preg_match('/<set>\s*<prop>(.*?)<\/prop>\s*<\/set>/is', $xml, $xmlMatches);
        $prop['set'] = $this->getSetProp($xmlMatches[1], $ns);
        preg_match('/<remove>\s*<prop>(.*?)<\/prop>\s*<\/remove>/is', $xml, $xmlMatches);
        if (!empty($xmlMatches[1])) {
            $prop['remove'] = [];
            preg_match_all('/<([^\/]+)\/>/', $xmlMatches[1], $matches);
            $n = count($matches[1]);
            for ($i = 0; $i < $n; $i++) {
                $prop['remove'][trim(str_contains($matches[1][$i], ':') ? $matches[1][$i] : 'd:' . $matches[1][$i])] = '';
            }
        }
        return $prop;
    }

    protected function getSetProp($xml, $ns) {
        if(empty($xml)) {
            return null;
        }
        preg_match_all('/<([^>\/\s]+)(\s[^>\/\s]+\s*)*>(.*?)<\/\1>/is', $xml, $matches);
        $n = count($matches[1]);
        $prop = [];
        for ($i = 0; $i < $n; $i++) {
            $tagName = $matches[1][$i];
            if(!str_contains($tagName, ':')) {
                $tagName = 'd:' . $tagName;
            }
            $tagValue = trim($matches[3][$i]);
            if(str_starts_with($tagValue, '<![CDATA[') && str_ends_with($tagValue, ']]>')) {
                $tagValue = trim(substr($tagValue, 9, -3));
            }
            $prop[$tagName] = $tagValue;
        }
        return $prop;
    }
}