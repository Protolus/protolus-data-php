<?php

class WhereParser{
    
    public static function parse($whereClause){
        $results = array();
        $inQuote = false;
        $quoteChar = '';
        $quotation = '';
        $result = array();
        $quoteChars = array('\'', '"');
        $breakingChars = array(' ', '=', '>', '<', '!');
        for($lcv=0; $lcv<strlen($whereClause); $lcv++){
            if($inQuote){
                if($whereClause[$lcv] == $quoteChar){ //close a quote
                    $result[sizeof($result)-1] .= '\''.$quotation.'\'';
                    $inQuote = false;
                    $quotation = '';
                }else{
                    $quotation .= $whereClause[$lcv];
                }
            }else{
                if(!isset($result[0])) $result[0] = ''; //init a new subject if we have nothing
                if($whereClause[$lcv] == ' ' ||
                    (!isset($result[1]) && in_array($whereClause[$lcv], $breakingChars)) ||
                    (isset($result[1]) && !isset($result[2]) && !in_array($whereClause[$lcv], $breakingChars))
                ){
                    if(isset($result[2]) && strtolower(substr($whereClause, $lcv, 4)) == 'and '){
                        $results[] = $result;
                        $result = array();
                        $lcv += 3; //skip the 'and' chars
                        continue;
                    }
                    if(isset($result[0]) && $result[0] == '') continue; //don't advance if we have nothing yet (leading spaces)
                    if(!isset($result[1])){
                        $result[1] = '';
                    }else if(!isset($result[2])){
                        $result[2] = '';
                    }
                }
                if(in_array($whereClause[$lcv], $quoteChars)){ //open a quote
                    $quoteChar = $whereClause[$lcv];
                    $inQuote = true;
                    $quotation = '';
                }else{
                    $result[sizeof($result)-1] .= $whereClause[$lcv];
                }
            }
        }
        if($quotation != '') $result[2] = $quotation;
        $results[] = $result;
        return $results;
    }
    
    public static function construct($discriminants){
        if(gettype($discriminants) == "string") return $discriminants;
        if(count($discriminants) == 3 && gettype($discriminants[0]) == "string" && gettype($discriminants[1]) == "string"){
            return $discriminants[0].' '.$discriminants[1].' '.$discriminants[2];
        }
        $result = '';
        foreach($discriminants as $discriminant){
            $result .= WhereParser::construct($discriminant);
        }
        return $result;
    }
    
}