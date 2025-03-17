<?php

namespace App\Models\Base;

use App\Models\Db\PropNS;

abstract class Controller
{
    protected $request;

    protected function getNameSpaceFromXml($xml)
    {
        preg_match_all('/xmlns:([^=]+)=["\'](.*?)["\']/is', $xml, $matches);
        $namespaces = [];
        if (!empty($matches[1]) && !empty($matches[2])) {
            $count = count($matches[1]);
            for ($i = 0; $i < $count; $i++) {
                $prefix              = trim($matches[1][$i]);
                $uri                 = trim($matches[2][$i]);
                $namespaces[$prefix] = $uri;
            }
        }
        return $namespaces;
    }

    protected function formatReqXml($xml)
    {
        $ns = $this->getNameSpaceFromXml($xml);
        $replace = [];
        foreach ($ns as $prefix => $uri) {
            $replace['<'.$prefix.':']  = '<'.PropNS::getPrefixByUri($uri).':';
            $replace['</'.$prefix.':'] = '</'.PropNS::getPrefixByUri($uri).':';
        }
        return str_replace(array_keys($replace), array_values($replace), $xml);
    }

    protected function xml_encode(array $data)
    {
        $nsId = isset($data[2]) && is_numeric($data[2]) ? intval($data[2]) : PropNS::DAV_ID;
        $nsInfo = PropNS::getInstance()->getInfoByNsId($nsId);
        $nsUri = $nsInfo['uri'];
        $nsPrefix = $nsInfo['prefix'];
        $qualifiedName = $nsPrefix . ':' . $data[0];
        if (!empty($data[1]) && is_array($data[1])) {
            $nsMap = [$nsPrefix => 'xmlns:' . $nsPrefix . '="' . $nsUri . '"'];
            $element = '';
            foreach ($data[1] as $node) {
                $element .= $this->item_encode($node, $nsMap);
            }
            $element = '<' . $qualifiedName . ' ' . implode(' ', $nsMap) . '>' . $element . "</" . $qualifiedName . '>';
        } else {
            $element = '<' . $qualifiedName . ' xmlns:' . $nsPrefix . '="' . $nsUri . '"' . (!isset($data[1]) || $data[1] === '' || is_array($data[1])) ? '/>' : ('>' . strval($data[1]) . '</' . $qualifiedName . '>');
        }
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $element;
    }

    /**
     * @param array $data
     * @param array $nsMap
     * @return string
     */
    public function item_encode(array $data, array &$nsMap)
    {
        $nsId = isset($data[2]) && is_numeric($data[2]) ? intval($data[2]) : PropNS::DAV_ID;
        $nsInfo = PropNS::getInstance()->getInfoByNsId($nsId);
        $nsUri = $nsInfo['uri'];
        $nsPrefix = $nsInfo['prefix'];
        if (empty($nsMap[$nsPrefix])) {
            $nsMap[$nsPrefix] = 'xmlns:' . $nsPrefix . '="' . $nsUri . '"';
        }
        $qualifiedName = $nsPrefix . ':' . $data[0];
        $element = '<' . $qualifiedName;
        if (!empty($data[3]) && is_array($data[3])) {
            foreach ($data[3] as $k => $v) {
                $element .= ' '.$k.'="'.$v.'"';
            }
        }
        if (!empty($data[1]) && is_array($data[1])) {
            $element .= '>';
            foreach ($data[1] as $node) {
                $element .= $this->item_encode($node, $nsMap);
            }
            $element .= '</' . $qualifiedName . '>';
        } else {
            $element .= ((isset($data[1]) && $data[1] !== '' && !is_array($data[1])) ? ('>' . strval($data[1]) . '</' . $qualifiedName . '>') : '/>');
        }
        return $element;
    }
}