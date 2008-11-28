<?php
// Copyright 2008 Won-Kyu Park <wkpark at kldp.org>
// All rights reserved. Distributable under GPL see COPYING
// a sample plugin for the MoniWiki
//
// Author: Won-kyu Park <wkpark@kldp.org>
// Date: 2008-11-26
// Name: Message Translation plugin
// Description: make a Translation *.mo from simple message files.
// URL: MoniWiki:DynamicMessageTranslation
// Version: $Revision$
// License: GPL
//
// Usage: [[Test]]
//
// $Id$

function macro_MsgTrans($formatter,$value,$param=array()) {
    global $DBInfo;

    $user=new User();
    if (!is_array($DBInfo->owners) or !in_array($user->id,$DBInfo->owners)) {
        return sprintf(_("You are not allowed to \"%s\" !"),"msgtrans");
    }

    if (!$pagename)
        $pagename=$DBInfo->default_translation ? $DBInfo->default_translation:'LocalTranslationKo';
    $page=$DBInfo->getPage($pagename);
    if (!$page->exists()) return '';
    $raw=$page->get_raw_body();$raw=rtrim($raw);

    $lines = explode("\n",$raw);

    $charset = strtoupper($DBInfo->charset);
    $lang = $DBInfo->lang ? $DBInfo->lang:'en_US.'.$charset;

    $strs = array();
    foreach ($lines as $l) {
        $l=trim($l);
        if ($l{0}=='#') {
            if (preg_match('/^#lang(?>uage)? (ko_KR|en_US|fr_FR)$/',$l,$m)) {
                $lang=$m[1];
                if ($DBInfo->charset) $lang.='.'.$charset;
            }
            continue;
        }
        if ($l{0}=='"') {
            if (preg_match('/^(("(([^"]|\\\\")*?)"\s*)+)\s*(.*)$/',$l,$m)) {
                $smap = array('/"\s+"/', '/\\\\n/', '/\\\\r/', '/\\\\t/', '/\\\\"/');
                $rmap = array('', "\n", "\r", "\t", '"');
                $w = preg_replace($smap,$rmap,$m[3]);
                $t = preg_replace($smap,$rmap,$m[5]);
            }
        } else {
            list($w,$t) = explode(" ",$l,2);
        }
        $strs[$w]=$t;
    }

    if(getenv("OS")=="Windows_NT") $lang=substr($lang,0,2);

    //print_r($strs);
    if (!empty($strs)) {
        $myMO = null;
        $ldir='locale/'.$lang.'/LC_MESSAGES';
        $mofile=$ldir.'/moniwiki.mo';

        if (!file_exists($mofile)) {
            # load *.po file
            $mylang = substr($lang,0,2);
            $pofile = 'locale/po/'.$mylang.'.po';
            if (file_exists($pofile)) {
                include_once 'lib/Gettext/PO.php';
                $myPO = new TGettext_PO;
                if ( ($e = $myPO->load($pofile)) == true) {
                    $myMO = $myPO->toMO();
                    preg_match('/charset=(.*)$/',$myMO->meta['Content-Type'],$cs);
                    if (strtoupper($cs[1]) != $charset) {
                        if (function_exists("iconv")) {
                            $myMO->meta['Content-Type']= 'text/plain; charset='.$charset;
                            foreach ($myMO->strings as $k=>$v) {
                                $nv = iconv($cs[1],$charset,$v);
                                if (isset($nv)) $myMO->strings[$k]=$nv;
                            }
                        } else {
                            $e = false;
                        }
                    }
                }
            }
        } else {
            # load *.mo file
            include_once 'lib/Gettext/MO.php';
            $myMO = new TGettext_MO;
            $e = $myMO->load($mofile);
        }

        if ($myMO and $e == true) {
            $myMO->strings = array_merge($myMO->strings,$strs);
            #$myMO->meta['PO-Revision-Date']= date('Y-m-d H:iO');
            ksort($myMO->strings); // important!
            #print_r($myMO->strings);
        } else {
           $meta = array(
                'Content-Type'      => 'text/plain; charset='.$charset,
                'Last-Translator'   => 'MoniWiki Translator',
                'PO-Revision-Date'  => date('Y-m-d H:iO'),
                'MIME-Version'      => '1.0',
                'Language-Team'     => 'MoniWiki Translator',
           );
            if (true !== ($e = $myMO->fromArray(array('meta'=>$meta,'strings'=>$strs)))) {
                return "Fail to make a mo file.\n";
            }
        }


        $vartmp_dir=$DBInfo->vartmp_dir;
        $tmp=tempnam($vartmp_dir,"GETTEXT");
        #$tmp=$vartmp_dir."/GETTEXT.mo";

        if (true !== ($e = $myMO->save($tmp))) {
            return "Fail to save mo file.\n";
        }
        # gettext cache workaround
        # http://kr2.php.net/manual/en/function.gettext.php#58310
        # use md5sum instead
        $md5 = md5_file($tmp);
        $md5file = $DBInfo->cache_dir.'/'.$ldir.'/md5sum';
        $ldir=$DBInfo->cache_dir.'/'.$ldir;
        _mkdir_p($ldir,0777);

        $f = fopen($md5file,'w');
        if (is_resource($f)) {
            fwrite($f,$md5);
            fclose($f);
        }

        if (!rename($tmp,$ldir.'/moniwiki-'.$md5.'.mo')) {
            unlink($md5file); // fail to copy ?
            return "Fail to save mo file.\n";
        }

        return _("Local translation files are successfully translated !\n");
    }
    return "Empty !\n";
}

function do_msgtrans($formatter,$options) {
    $formatter->send_header('',$options);
    $formatter->send_title('','',$options);
    $ret= macro_MsgTrans($formatter,$options['value']);
    $formatter->send_page($ret);
    $formatter->send_footer('',$options);
    return;
}

// vim:et:sts=4:
?>