<?php

/**
 * Core modules
 * @package modules
 * @subpackage core
 */

/**
 * Format a message body that has HMTL markup
 * @subpackage core/functions
 * @param string $str message HTML
 * @param bool $images allow external images
 * @return string
 */
if (!hm_exists('format_msg_html')) {
function format_msg_html($str, $images=false) {
    $str = str_ireplace('</body>', '', $str);
    require_once VENDOR_PATH.'autoload.php';
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.DefinitionImpl', null);
    if (!$images) {
        $config->set('URI.DisableExternalResources', true);
    }
    $config->set('URI.AllowedSchemes', array('mailto' => true, 'data' => true, 'http' => true, 'https' => true));
    $config->set('Filter.ExtractStyleBlocks.TidyImpl', true);
    $purifier = new HTMLPurifier($config);
    return @$purifier->purify($str);
}}

/**
 * Convert HTML to plain text
 * @param string $html content to convert
 * @return string
 */
if (!hm_exists('convert_html_to_text')) {
function convert_html_to_text($html) {
    $html = new HTMLToText($html);
    return $html->text;
}}

/**
 * Format image data
 * @subpackage core/functions
 * @param string $str binary image data
 * @param string $mime_type type of image
 * return string
 */
if (!hm_exists('format_msg_image')) {
function format_msg_image($str, $mime_type) {
    return '<img class="msg_img" alt="" src="data:image/'.$mime_type.';base64,'.chunk_split(base64_encode($str)).'" />';
}}

/**
 * Format a plain text message
 * @subpackage core/functions
 * @param string $str message text
 * @param object $output_mod Hm_Output_Module
 */
if (!hm_exists('format_msg_text')) {
function format_msg_text($str, $output_mod, $links=true) {
    $str = str_replace("\t", '    ', $str);
    $str = nl2br(str_replace(' ', '<wbr>', ($output_mod->html_safe($str)))).'<br />';
    $str = preg_replace("/(&(?!amp)[^;]+;)/", " $1", $str);
    if ($links) {
        $link_regex = "/((http|ftp|rtsp)s?:\/\/(%[[:digit:]A-Fa-f][[:digit:]A-Fa-f]|[-_\.!~\*';\/\?#:@&=\+$,%[:alnum:]])+)/m";
        $str = preg_replace($link_regex, "<a href=\"$1\">$1</a>", $str);
    }
    $str = preg_replace("/ (&[^;]+;)/", "$1", $str);
    $str = str_replace('<wbr>', '&#160;<wbr>', $str);
    return preg_replace("/^(&gt;.*<br \/>)/m", "<span class=\"reply_quote\">$1</span>", $str);
}}

/**
 * Format reply text
 * @subpackage core/functions
 * @param string $txt message text
 * @return string
 */
if (!hm_exists('format_reply_text')) {
function format_reply_text($txt) {
    $lines = explode("\n", $txt);
    $new_lines = array();
    foreach ($lines as $line) {
        $pre = '> ';
        if (preg_match("/^(>\s*)+/", $line, $matches)) {
            $pre .= $matches[1];
        }
        $wrap = 75 + strlen($pre);
        $new_lines[] = preg_replace("/$pre /", "$pre", "> ".wordwrap($line, $wrap, "\n$pre"));
    }
    return implode("\n", $new_lines);
}}

/**
 * Get reply to address
 * @subpackage core/functions
 * @param array $headers message headers
 * @param string $type type (forward, reply, reply_all)
 * @return string
 */
if (!hm_exists('reply_to_address')) {
function reply_to_address($headers, $type) {
    $msg_to = '';
    $msg_cc = '';
    $headers = lc_headers($headers);
    $parsed = array();
    $delivered_address = false;
    if (array_key_exists('delivered-to', $headers)) {
        $delivered_address = array('email' => $headers['delivered-to'],
            'comment' => '', 'label' => '');
    }

    if ($type == 'forward') {
        return $msg_to;
    }
    if (count($headers["return-path"]) == 1) {
        $msg_to = $headers["to"];
    }
    else {
        foreach (array('reply-to', 'from', 'sender', 'return-path') as $fld) {
            if (array_key_exists($fld, $headers)) { 
                list($parsed, $msg_to) = format_reply_address($headers[$fld], $parsed);
                if ($msg_to) {
                    break;
                }
            }
        }
    }
    if ($type == 'reply_all') {
        if ($delivered_address) {
            $parsed[] = $delivered_address;
        }
        if (array_key_exists('cc', $headers)) {
            list($cc_parsed, $msg_cc) = format_reply_address($headers['cc'], $parsed);
            $parsed += $cc_parsed;
        }
        if (array_key_exists('to', $headers)) {
            list($parsed, $recips) = format_reply_address($headers['to'], $parsed);
            if ($recips) {
                if ($msg_cc) {
                    $msg_cc .= ', '.$recips;
                }
                else {
                    $msg_cc = $recips;
                }
            }
        }
    }
    return array($msg_to, $msg_cc);
}}

/*
 * Format a reply address line
 * @param string $fld the field values from the E-mail being replied to
 * @param array $excluded list of parsed addresses to exclude
 * @return string
 */
if (!hm_exists('format_reply_address')) {
function format_reply_address($fld, $excluded) {
    $addr = process_address_fld(trim($fld));
    $res = array();
    foreach ($addr as $v) {
        $skip = false;
        foreach ($excluded as $ex) {
            if (strtolower($v['email']) == strtolower($ex['email'])) {
                $skip = true;
                break;
            }
        }
        if (!$skip) {
            $res[] = $v;
        }
    }
    if ($res) {
        return array($addr, implode(', ', array_map(function($v) {
            if (trim($v['label'])) {
                return $v['label'].' '.$v['email'];
            }
            else {
                return $v['email'];
            }
        }, $res)));
    }
    return array($addr, '');
}}

/**
 * Get reply to subject
 * @subpackage core/functions
 * @param array $headers message headers
 * @param string $type type (forward, reply, reply_all)
 * @return string
 */
if (!hm_exists('reply_to_subject')) {
function reply_to_subject($headers, $type) {
    $subject = '';
    if (array_key_exists('Subject', $headers)) {
        if ($type == 'reply' || $type == 'reply_all') {
            if (!preg_match("/^re:/i", trim($headers['Subject']))) {
                $subject = sprintf("Re: %s", $headers['Subject']);
            }
        }
        elseif ($type == 'forward') {
            if (!preg_match("/^fwd:/i", trim($headers['Subject']))) {
                $subject = sprintf("Fwd: %s", $headers['Subject']);
            }
        }
        if (!$subject) {
            $subject = $headers['Subject'];
        }
    }
    return $subject;
}}

/**
 * Get reply message lead in
 * @subpackage core/functions
 * @param array $headers message headers
 * @param string $type type (forward, reply, reply_all)
 * @param string $to reply to value
 * @param object $output_mod output module object
 * @return string
 */
if (!hm_exists('reply_lead_in')) {
function reply_lead_in($headers, $type, $to, $output_mod) {
    $lead_in = '';
    if ($type == 'reply' || $type == 'reply_all') {
        if (array_key_exists('Date', $headers)) {
            if ($to) {
                $lead_in = sprintf($output_mod->trans('On %s %s said')."\n\n", $headers['Date'], $to);
            }
            else {
                $lead_in = sprintf($output_mod->trans('On %s, somebody said')."\n\n", $headers['Date']);
            }
        }
    }
    elseif ($type == 'forward') {
        $flds = array();
        foreach( array('From', 'Date', 'Subject') as $fld) {
            if (array_key_exists($fld, $headers)) {
                $flds[$fld] = $headers[$fld];
            }
        }
        $lead_in = "\n\n----- ".$output_mod->trans('begin forwarded message')." -----\n\n";
        foreach ($flds as $fld => $val) {
            $lead_in .= $fld.': '.$val."\n";
        }
        $lead_in .= "\n";
    }
    return $lead_in;
}}

/**
 * Format reply field details
 * @subpackage core/functions
 * @param array $headers message headers
 * @param string $body message body
 * @param string $lead_in body lead in text
 * @param string $reply_type type (forward, reply, reply_all)
 * @param array $struct message structure details
 * @param int $html set to 1 if the output should be HTML
 * @return array
 */
if (!hm_exists('reply_format_body')) {
function reply_format_body($headers, $body, $lead_in, $reply_type, $struct, $html) {
    $msg = '';
    $type = 'textplain';
    if (array_key_exists('type', $struct) && array_key_exists('subtype', $struct)) {
        $type = strtolower($struct['type']).strtolower($struct['subtype']);
    }
    if ($html == 1) {
        $msg = format_reply_as_html($body, $type, $reply_type, $lead_in);
    }
    else {
        $msg = format_reply_as_text($body, $type, $reply_type, $lead_in);
    }
    return $msg;
}}

/**
 * Format reply text as HTML
 * @subpackage core/functions
 * @param string $body message body
 * @param string $type MIME type
 * @param string $reply_type type (forward, reply, reply_all)
 * @param string $lead_in body lead in text
 * @return string
 */
if (!hm_exists('format_reply_as_html')) {
function format_reply_as_html($body, $type, $reply_type, $lead_in) {
    if ($type == 'textplain') {
        if ($reply_type == 'reply' || $reply_type == 'reply_all') {
            $msg = nl2br($lead_in.format_reply_text($body));
        }
        elseif ($reply_type == 'forward') {
            $msg = nl2br($lead_in.$body);
        }
    }
    elseif ($type == 'texthtml') {
        $msg = nl2br($lead_in).'<hr /><blockquote>'.format_msg_html($body).'</blockquote>';
    }
    return $msg;
}}

/**
 * Format reply text as text
 * @subpackage core/functions
 * @param string $body message body
 * @param string $type MIME type
 * @param string $reply_type type (forward, reply, reply_all)
 * @param string $lead_in body lead in text
 * @return string
 */
if (!hm_exists('format_reply_as_text')) {
function format_reply_as_text($body, $type, $reply_type, $lead_in) {
    $msg = '';
    if ($type == 'texthtml') {
        if ($reply_type == 'reply' || $reply_type == 'reply_all') {
            $msg = $lead_in.format_reply_text(convert_html_to_text($body));
        }
        elseif ($reply_type == 'forward') {
            $msg = $lead_in.convert_html_to_text($body);
        }
    }
    elseif ($type == 'textplain') {
        if ($reply_type == 'reply' || $reply_type == 'reply_all') {
            $msg = $lead_in.format_reply_text($body);
        }
        else {
            $msg = $lead_in.$body;
        }
    }
    return $msg;
}}

/**
 * Convert header keys to lowercase versions
 * @param array $headers message headers
 * @return array
 */
if (!hm_exists('lc_headers')) {
function lc_headers($headers) {
    return array_change_key_case($headers, CASE_LOWER);
}}

/**
 * Get the in-reply-to message id for replied
 * @subpackage core/functions
 * @param array $headers message headers
 * @param string $type reply type
 * @return string
 */
if (!hm_exists('reply_to_id')) {
function reply_to_id($headers, $type) {
    $id = '';
    $headers = lc_headers($headers);
    if ($type != 'forward' && array_key_exists('message-id', $headers)) {
        $id = $headers['message-id'];
    }
    return $id;
}}

/**
 * Get reply field details
 * @subpackage core/functions
 * @param string $body message body
 * @param array $headers message headers
 * @param array $struct message structure details
 * @param int $html set to 1 if the output should be HTML
 * @param string $type optional type (forward, reply, reply_all)
 * @param object $output_mod output module object
 * @param string $type the reply type
 * @return array
 */
if (!hm_exists('format_reply_fields')) {
function format_reply_fields($body, $headers, $struct, $html, $output_mod, $type='reply') {
    $msg_to = '';
    $msg = '';
    $subject = reply_to_subject($headers, $type);
    $msg_id = reply_to_id($headers, $type);
    list($msg_to, $msg_cc) = reply_to_address($headers, $type);
    $lead_in = reply_lead_in($headers, $type, $msg_to, $output_mod);
    $msg = reply_format_body($headers, $body, $lead_in, $type, $struct, $html);
    return array($msg_to, $msg_cc, $subject, $msg, $msg_id);
}}

/**
 * decode mail fields to human readable text
 * @param string $string field to decode
 * @return string decoded field
 */
if (!hm_exists('decode_fld')) {
function decode_fld($string) {
    if (strpos($string, '=?') === false) {
        return $string;
    }
    $string = preg_replace("/\?=\s+=\?/", '?==?', $string);
    if (preg_match_all("/(=\?[^\?]+\?(q|b)\?[^\?]+\?=)/i", $string, $matches)) {
        foreach ($matches[1] as $v) {
            $fld = substr($v, 2, -2);
            $charset = strtolower(substr($fld, 0, strpos($fld, '?')));
            $fld = substr($fld, (strlen($charset) + 1));
            $encoding = $fld[0];
            $fld = substr($fld, (strpos($fld, '?') + 1));
            if (strtoupper($encoding) == 'B') {
                $fld = mb_convert_encoding(base64_decode($fld), 'UTF-8', $charset);
            }
            elseif (strtoupper($encoding) == 'Q') {
                $fld = mb_convert_encoding(quoted_printable_decode(str_replace('_', ' ', $fld)), 'UTF-8', $charset);
            }
            $string = str_replace($v, $fld, $string);
        }
    }
    return trim($string);
}}

/**
 * @subpackage core/class
 */
class HTMLToText {

    public $text = '';
    private $current = false;
    private $blocks = array('table', 'li', 'div', 'h1', 'h2', 'br', 'h3', 'h4', 'h5', 'p', 'tr');
    private $skips = array('head', 'script', 'style');

    function __construct($html) {
        $doc = new DOMDocument();
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        if (trim($html) && $doc->hasChildNodes()) {
            $this->parse_nodes($doc->childNodes);
        }
        $this->text = trim(strip_tags(html_entity_decode(preg_replace("/\n{2,}/m", "\n\n", $this->text), ENT_QUOTES, "UTF-8")));
    }

    function block($tag) {
        in_array($tag, $this->blocks) && $this->current != $tag ? $this->text .= "\n" : false;
        $this->current = $tag;
    }

    function parse_nodes($nodes) {
        $trims = " \t\n\r\0\x0B";
        foreach ($nodes as $node) {
            if (!in_array($node->nodeName, $this->skips)) {
                $this->block($node->nodeName);
                if ($node->nodeName == '#text' && trim($node->textContent, $trims)) {
                    $this->text .= trim($node->textContent, $trims)." ";
                }
                $node->hasChildNodes() ? $this->parse_nodes($node->childNodes) : false;
            }
        }
    }
}

/**
 * trim a potential E-mail value
 * @param $val string E-mail value
 * @return string trimmed value
 */
if (!hm_exists('addr_split')) {
function trim_email($val) {
    $seps = array(',', ';');
    $misc = array('"', "'", '>', '<');
    return trim($val, implode(array_merge($misc, $seps)));
}}

/**
 * Split an address field
 * @param $str string field value
 * @param $seps array break chars
 * @return array results
 */
if (!hm_exists('addr_split')) {
function addr_split($str, $seps = array(',', ';')) {
    $str = preg_replace('/(\s){2,}/', ' ', $str);
    $max = strlen($str);
    $word = '';
    $words = array();
    $capture = false;
    $capture_chars = array('"' => '"', '(' => ')', '<' => '>');
    for ($i=0;$i<$max;$i++) {
        if ($capture && $capture_chars[$capture] == $str[$i]) {
            $capture = false;
        }
        elseif (!$capture && in_array($str[$i], array_keys($capture_chars))) {
            $capture = $str[$i];
        }
        
        if (!$capture && in_array($str[$i], $seps)) {
            $words[] = trim($word);
            $word = '';
        }
        else {
            $word .= $str[$i];
        }
    }
    $words[] = trim($word);
    return $words;
}}

/**
 * Parse an address field
 * @param $str string field value
 * @return array results
 */
if (!hm_exists('addr_parse')) {
function addr_parse($str) {
    $label = array();
    $email = '';
    $comment = array();
    foreach (addr_split($str, array(' ')) as $token) {
        if (is_email_address(trim_email($token))) {
            $email = trim_email($token);
        }
        else {
            $label[] = $token;
        }
    }
    $label = implode(' ', $label);
    if (preg_match('/\([^)]+\)/', $label, $matches)) {
        foreach ($matches as $match) {
            $comment[] = $match;
            $label = str_replace($match, '', $label);
        }
        $comment = implode(',', $comment);
    }
    else {
        $comment = '';
    }
    return array('email' => $email, 'label' => preg_replace('/[\pZ\pC]+/u', ' ', trim($label, ' \'"')), 'comment' => $comment);
}}

/**
 * Parse an address field
 * @param $fld string field value
 * @return array results
 */
if (!hm_exists('process_address_fld')) {
function process_address_fld($fld) {
    $res = array();
    $count = 0;
    $pre = false;
    foreach (addr_split($fld) as $str) {
        $addr = addr_parse($str);
        if ($addr['email']) {
            if ($pre) {
                $addr['label'] = $pre.' '.$addr['label'];
                $pre = false;
            }
            $res[$count] = $addr;
        }
        elseif ($addr['label']) {
            $pre = $addr['label'];
        }
        $count++;
    }
    return $res;
}}
