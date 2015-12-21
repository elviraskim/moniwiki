<?php
// Copyright 2003-2015 Won-Kyu Park <wkpark at kldp.org> all rights reserved.
// distributable under GPLv2 see COPYING
//
// many codes are imported from the MoinMoin
// some codes are reused from the Phiki
//
// * MoinMoin is a python based wiki clone based on the PikiPiki
//    by Ju"rgen Hermann <jhs at web.de>
// * PikiPiki is a python based wiki clone by MartinPool
// * Phiki is a php based wiki clone based on the MoinMoin
//    by Fred C. Yankowski <fcy at acm.org>
//
// $Id: wiki.php,v 1.639 2011/08/09 13:51:53 wkpark Exp $
//
$_revision = substr('$Revision: 1.1950 $',1,-1);
$_release = '1.3.0-GIT';

#ob_start("ob_gzhandler");

error_reporting(E_ALL ^ E_NOTICE);
#error_reporting(E_ALL);

if (!function_exists ('bindtextdomain')) {
    $_locale = array();

    function gettext ($text) {
        global $_locale,$locale;
        if (sizeof($_locale) == 0) $_locale=&$locale;
        if (!empty ($_locale[$text]))
            return $_locale[$text];
        return $text;
    }

    function _ ($text) {
        return gettext($text);
    }
}

function _t ($text) {
    return gettext($text);
}

function goto_form($action,$type="",$form="") {
    if ($type==1) {
        return "
<form id='go' method='get' action='$action'>
<div>
<span title='TitleSearch'>
<input type='radio' name='action' value='titlesearch' />
Title</span>
<span title='FullSearch'>
<input type='radio' name='action' value='fullsearch' />
Contents</span>&nbsp;
<input type='text' name='value' class='goto' accesskey='s' size='20' />
<input type='submit' name='status' value='Go' style='width:23px' />
</div>
</form>
";
    } else if ($type==2) {
        return "
<form id='go' method='get' action='$action'>
<div>
<select name='action' style='width:60px'>
<option value='goto'>goto</option>
<option value='titlesearch'>TitleSearch</option>
<option value='fullsearch'>FullSearch</option>
</select>
<input type='text' name='value' class='goto' accesskey='s' size='20' />
<input type='submit' name='status' value='Go' />
</div>
</form>
";
    } else if ($type==3) {
        return "
<form id='go' method='get' action='$action'>
<table class='goto'>
<tr><td nowrap='nowrap' style='width:220px'>
<input type='text' name='value' size='28' accesskey='s' style='width:110px' />
<input type='submit' name='status' value='Go' class='goto' style='width:23px' />
</td></tr>
<tr><td>
<span title='TitleSearch' class='goto'>
<input type='radio' name='action' value='titlesearch' class='goto' />
Title(?)</span>
<span title='FullSearch' class='goto'>
<input type='radio' name='action' value='fullsearch' accesskey='s' class='goto'/>
Contents(/)</span>&nbsp;
</td></tr>
</table>
</form>
";
    } else {
        return <<<FORM
<form id='go' method='get' action='$action' onsubmit="moin_submit(this);">
<div>
<input type='text' name='value' size='20' accesskey='s' class='goto' style='width:100px' />
<input type='hidden' name='action' value='goto' />
<input type='submit' name='status' value='Go' style='width:23px;' />
</div>
</form>
FORM;
    }
}

function kbd_handler($prefix = '') {
    global $Config;

    if (!$Config['kbd_script']) return '';
    $prefix ? null : $prefix = get_scriptname();
    $sep = $Config['query_prefix'];
    return <<<EOS
<script type="text/javascript">
/*<![CDATA[*/
url_prefix="$prefix";
_qp="$sep";
FrontPage= "$Config[frontpage]";
/*]]>*/
</script>
<script type="text/javascript" src="$Config[kbd_script]"></script>\n
EOS;
}

function getConfig($configfile, $options=array()) {
    extract($options);
    unset($key,$val,$options);

    // ignore BOM and garbage characters
    ob_start();
    $myret = @include($configfile);
    ob_get_contents();
    ob_end_clean();

    if ($myret === false) {
        if (!empty($init)) {
            $script= preg_replace("/\/([^\/]+)\.php$/",'/monisetup.php',
                    $_SERVER['SCRIPT_NAME']);
            if (is_string($init)) $script .= '?init='.$init;
            header("Location: $script");
            exit;
        }
        return array();
    } 
    unset($configfile);
    unset($myret);

    $config=get_defined_vars();

    if (isset($config['include_path']))
        ini_set('include_path',ini_get('include_path').PATH_SEPARATOR.$config['include_path']);

    return $config;
}

class Formatter {
    var $sister_idx=1;
    var $group='';
    var $use_purple=1;
    var $purple_number=0;
    var $java_scripts=array();

    function Formatter($page="",$options=array()) {
        global $DBInfo;

        // string properties
        $this->url_prefix = $DBInfo->url_prefix;
        $this->imgs_dir = $DBInfo->imgs_dir;
        $this->imgs_url_interwiki = !empty($DBInfo->imgs_url_interwiki) ? $DBInfo->imgs_url_interwiki : '';
        $this->imgs_dir_url = !empty($DBInfo->imgs_dir_url) ? $DBInfo->imgs_dir_url : '';
        $this->external_image_regex = !empty($DBInfo->external_image_regex) ? $DBInfo->external_image_regex : '';
        $this->nonexists = $DBInfo->nonexists;

        // boolean properties
        $this->auto_linebreak = !empty($DBInfo->auto_linebreak) ? $DBInfo->auto_linebreak : 0;
        $this->css_friendly = $DBInfo->css_friendly;
        $this->use_smartdiff = !empty($DBInfo->use_smartdiff) ? $DBInfo->use_smartdiff : 0;
        $this->use_easyalias = $DBInfo->use_easyalias;
        $this->use_folding = !empty($DBInfo->use_folding) ? $DBInfo->use_folding : 0;
        $this->use_group = !empty($DBInfo->use_group) ? $DBInfo->use_group : 0;
        $this->use_htmlcolor = !empty($DBInfo->use_htmlcolor) ? $DBInfo->use_htmlcolor : 0;
        $this->use_metadata = !empty($DBInfo->use_metadata) ? $DBInfo->use_metadata : 0;
        $this->use_namespace = !empty($DBInfo->use_namespace) ? $DBInfo->use_namespace : 0;
        $this->use_purple = !empty($DBInfo->use_purple) ? $DBInfo->use_purple : 0;
        $this->use_rating = !empty($DBInfo->use_rating) ? $DBInfo->use_rating : 0;
        $this->use_smileys = $DBInfo->use_smileys;
        // use thumbnail by default
        $this->use_thumb_by_default = !empty($DBInfo->use_thumb_by_default) ? $DBInfo->use_thumb_by_default : 0;
        $this->markdown_style = !empty($DBInfo->markdown_style) ? $DBInfo->markdown_style : 0;
        $this->mediawiki_style=!empty($DBInfo->mediawiki_style) ? $DBInfo->mediawiki_style : 0;
        $this->check_openid_url=!empty($DBInfo->check_openid_url) ? $DBInfo->check_openid_url : 0;
        $this->fetch_action = !empty($DBInfo->fetch_action) ? $DBInfo->fetch_action : 0;
        $this->fetch_images = !empty($DBInfo->fetch_images) ? $DBInfo->fetch_images : 0;
        $this->fetch_imagesize = !empty($DBInfo->fetch_imagesize) ? $DBInfo->fetch_imagesize : 0;
        // the original source site for mirror sites
        $this->source_site = !empty($DBInfo->source_site) ? $DBInfo->source_site : 0;

        $this->actions = !empty($DBInfo->actions) ? $DBInfo->actions : array();
        $this->submenu = !empty($DBInfo->submenu) ? $DBInfo->submenu : null;
        $this->email_guard = !empty($DBInfo->email_guard) ? $DBInfo->email_guard : null;
        $this->filters = !empty($DBInfo->filters) ? $DBInfo->filters : null;
        $this->postfilters = !empty($DBInfo->postfilters) ? $DBInfo->postfilter : null;
        $this->url_mappings = $DBInfo->url_mappings;

        // use mediawiki like built-in category support
        if (!empty($DBInfo->use_builtin_category) && !empty($DBInfo->category_regex)) {
            $this->use_builtin_category = true;
            $this->category_regex = $DBInfo->category_regex;
        } else {
            $this->use_builtin_category = false;
        }

        // call externalimage macro for these external images

        // strtr() old wiki markups
        $this->trtags = !empty($DBInfo->trtags) ? $DBInfo->trtags : null;

        // initialize
        $this->page = $page;
        $this->self_query = '';
        if (!empty($options['prefix'])) {
            $this->prefix = $options['prefix'];
        } else if (!empty($DBInfo->base_url_prefix)) {
            // force the base url prefix
            $this->prefix = $DBInfo->base_url_prefix;
        } else {
            // call get_scriptname() to get the base url prefix
            $this->prefix = get_scriptname();
        }

        if (is_object($page)) {
            if ($this->use_group and ($p=strpos($page->name, '~')))
                $this->group = substr($page->name, 0, $p + 1);
        }

        // for TOC
        $this->head_num = 1;
        $this->head_dep = 0;
        $this->sect_num = 0;
        $this->toc = 0;
        $this->toc_prefix = '';

        $this->sister_on = 1;
        $this->sisters = array();
        $this->foots = array();
        $this->pagelinks = array();
        $this->aliases = array();
        $this->icons = '';

        $this->themedir = !empty($DBInfo->themedir) ? $DBInfo->themedir : dirname(__FILE__);
        $this->themeurl = !empty($DBInfo->themeurl) ? $DBInfo->themeurl : $DBInfo->url_prefix;

        $this->set_theme(!empty($options['theme']) ? $options['theme'] : '', $options);
        $this->register_javascripts($DBInfo->javascripts);

        // some initialize
        $this->section_edit = $DBInfo->use_sectionedit;
        if (!empty($DBInfo->external_target))
            $this->external_target = 'target="'.$DBInfo->external_target.'"';
        $this->inline_latex = $DBInfo->inline_latex == 1 ? 'latex':$DBInfo->inline_latex;
        $this->interwiki_target=!empty($DBInfo->interwiki_target) ?
            ' target="'.$DBInfo->interwiki_target.'"':'';

        // init
        if (empty($this->fetch_action))
            $this->fetch_action = $this->link_url('', '?action=fetch&amp;url=');
        else
            $this->fetch_action = $DBInfo->fetch_action;

        // copy directly
        $this->lang = $DBInfo->lang;
        // copy reference
        $this->udb = &$DBInfo->udb;
        $this->user = &$DBInfo->user;

        // setup for html5
        $this->tags = array();
        if (!empty($DBInfo->html5)) {
            $this->html5 = $DBInfo->html5;
            $this->tags['article'] = 'article';
            $this->tags['header'] = 'header';
            $this->tags['footer'] = 'footer';
            $this->tags['nav'] = 'nav';
        } else {
            $this->html5 = null;
            $this->tags['article'] = 'div';
            $this->tags['header'] = 'div';
            $this->tags['footer'] = 'div';
            $this->tags['nav'] = 'div';
        }

        // goto wikiconfig
        $this->quote_style = !empty($DBInfo->quote_style) ? $DBInfo->quote_style : 'quote';
        $this->NULL = '';
        if(getenv("OS") != "Windows_NT") $this->NULL = ' 2>/dev/null';
        $this->thumb_width = !empty($DBInfo->thumb_width) ? $DBInfo->thumb_width : 320;

        $this->_macrocache = 0;
        $this->wikimarkup = 0;
        $this->pi = array();
        $this->external_on = 0;
        $this->external_target = '';


        // set filter
        if (!empty($this->filters)) {
            if (!is_array($this->filters)) {
                $this->filters=preg_split('/(\||,)/',$this->filters);
            }
        } else {
            $this->filters = '';
        }
        if (!empty($this->postfilters)) {
            if (!is_array($this->postfilters)) {
                $this->postfilters=preg_split('/(\||,)/',$this->postfilters);
            }
        } else {
            $this->postfilters = '';
        }

        $this->baserule=array("/(?<!\<)<(?=[^<>]*>)/",
                "/&(?!([^&;]+|#[0-9]+|#x[0-9a-fA-F]+);)/",
                "/(?<!')'''((?U)(?:[^']|(?<!')'(?!')|'')*)?'''(?!')/",
                "/''''''/", // SixSingleQuote
                "/(?<!')''((?:[^']|[^']'(?!'))*)''(?!')/",
                "/`(?<!\s)(?!`)([^`']+)(?<!\s)'(?=\s|$)/",
                "/`(?<!\s)(?U)(.*)(?<!\s)`/",
                "/^(={4,})$/",
                "/,,([^,]{1,40}),,/",
                "/\^([^ \^]+)\^(?=\s|$)/",
                "/\^\^(?<!\s)(?!\^)(?U)(.+)(?<!\s)\^\^/",
                "/__(?<!\s)(?!_)(?U)(.+)(?<!\s)__/",
                "/--(?<!\s)(?!-)(?U)(.+)(?<!\s)--/",
                "/~~(?<!\s)(?!~)(?U)(.+)(?<!\s)~~/",
                #"/(\\\\\\\\)/", # tex, pmWiki
                );
        $this->baserepl=array("&lt;",
                "&amp;",
                "<strong>\\1</strong>",
                "<strong></strong>",
                "<em>\\1</em>",
                "&#96;\\1'","<code>\\1</code>",
                "<br clear='all' />",
                "<sub>\\1</sub>",
                "<sup>\\1</sup>",
                "<sup>\\1</sup>",
                "<em class='underline'>\\1</em>",
                "<del>\\1</del>",
                "<del>\\1</del>",
                #"<br />\n",
                );

        // set extra baserule
        if (!empty($DBInfo->baserule)) {
            foreach ($DBInfo->baserule as $rule=>$repl) {
                $t = @preg_match($rule,$repl);
                if ($t!==false) {
                    $this->baserule[]=$rule;
                    $this->baserepl[]=$repl;
                }
            }
        }

        // check and prepare $url_mappings
        if (!empty($DBInfo->url_mappings)) {
            if (!is_array($DBInfo->url_mappings)) {
                $maps=explode("\n",$DBInfo->url_mappings);
                $tmap=array();
                foreach ($maps as $map) {
                    if (strpos($map,' ')) {
                        $key=strtok($map,' ');
                        $val=strtok('');
                        $tmap["$key"]=$val;
                    }
                }
                $this->url_mappings=$tmap;
            }
        }

        # recursive footnote regex
        $this->footrule='\[\*[^\[\]]*((?:[^\[\]]++|\[(?13)\])*)\]';
    }

    /**
     * init Smileys
     * load smileys and set smily_rule and smiley_repl
     */
    function initSmileys() {
        $this->smileys = getSmileys();

        $tmp = array_keys($this->smileys);
        $tmp = array_map('_preg_escape', $tmp);
        $rule = implode('|', $tmp);

        $this->smiley_rule = '/(?<=\s|^|>)('.$rule.')(?=\s|<|$)/';
    }

    function set_wordrule($pis=array()) {
        global $DBInfo;

        $single=''; # single bracket
            $camelcase= isset($pis['#camelcase']) ? $pis['#camelcase']:
            $DBInfo->use_camelcase;

        if (!empty($pis['#singlebracket']) or !empty($DBInfo->use_singlebracket))
            $single='?';

        #$punct="<\"\'}\]\|;,\.\!";
        #$punct="<\'}\]\)\|;\.\!"; # , is omitted for the WikiPedia
        #$punct="<\'}\]\|\.\!"; # , is omitted for the WikiPedia
        $punct="<\'}\]\|\.\!\010\006"; # , is omitted for the WikiPedia
        $punct="<>\"\'}\]\|\.\!\010\006"; # " and > added
        $url="wiki|http|https|ftp|nntp|news|irc|telnet|mailto|file|attachment";
        if (!empty($DBInfo->url_schemas)) $url.='|'.$DBInfo->url_schemas;
        $this->urls=$url;
        $urlrule="((?:$url):\"[^\"]+\"[^\s$punct]*|(?:$url):(?:[^\s$punct]|(\.?[^\s$punct]))+(?<![,\.\):;\"\'>]))";
        #$urlrule="((?:$url):(\.?[^\s$punct])+)";
        #$urlrule="((?:$url):[^\s$punct]+(\.?[^\s$punct]+)+\s?)";
        # solw slow slow
        #(?P<word>(?:/?[A-Z]([a-z0-9]+|[A-Z]*(?=[A-Z][a-z0-9]|\b))){2,})
        $this->wordrule=
            # nowiki
            "!?({{{(?:(?:[^{}]+|{[^{}]+}(?!})|(?<!{){{1,2}(?!{)|(?<!})}{1,2}(?!})|(?<=\\\\)[{}]{3}(?!}))|(?2))++}}})|".
            # {{{{{{}}}, {{{}}}}}}, {{{}}}
            "(?:(?!<{{{){{{}}}(?!}}})|{{{(?:{{{|}}})}}})|".
            # single bracketed rule [http://blah.blah.com Blah Blah]
            "(?:\[\^?($url):[^\s\]]+(?:\s[^\]]+)?\])|".
            # InterWiki
            # strict but slow
            #"\b(".$DBInfo->interwikirule."):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+\s{0,1})|".
            #"\b([A-Z][a-zA-Z]+):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+\s{0,1})|".
            #"\b([A-Z][a-zA-Z]+):([^<>\s\'\/]{1,2}[^\(\)<>\s\']+[^\(\)<>\s\',\.:\?\!]+)|".
            "(?:\b|\^?|!?)(?:[A-Z][a-zA-Z0-9]+):(?:\"[^\"]+\"|[^:\(\)<>\s\']?[^\s<\'\",\!\010\006]+(?:\s(?![\x21-\x7e]))?)(?<![,\.\)>])|".
            #"(?:\b|\^?)(?:[A-Z][a-zA-Z]+):(?:[^:\(\)<>\s\']?[^\s<\'\",:\!\010\006]+(?:\s(?![\x21-\x7e]))?(?<![,\.\)>]))|".
            #"(\b|\^?)([A-Z][a-zA-Z]+):([^:\(\)<>\s\']?[^<>\s\'\",:\?\!\010\006]*(\s(?![\x21-\x7e]))?)";
            # for PR #301713
            #
            # new regex pattern for
            #  * double bracketted rule similar with MediaWiki [[Hello World]]
            #  * single bracketted words [Hello World] etc.
            #  * single bracketted words with double quotes ["Hello World"]
            #  * double bracketted words with double quotes [["Hello World"]]
            "(?<!\[)\!?\[(\[)$single(\")?(?:[^\[\]\",<\s'\*]?[^\[\]]{0,255}[^\"])(?(5)\"(?:[^\"\]]*))(?(4)\])\](?!\])";

        if ($camelcase)
            $this->wordrule.='|'.
                "(?<![a-zA-Z0-9#])\!?(?:((\.{1,2})?\/)?[A-Z]([A-Z]+[0-9a-z]|[0-9a-z]+[A-Z])[0-9a-zA-Z]*)+\b";
        else
            # only bangmeta syntax activated
            $this->wordrule.='|'.
                "(?<![a-zA-Z])\!(?:((\.{1,2})?\/)?[A-Z]([A-Z]+[0-9a-z]|[0-9a-z]+[A-Z])[0-9a-zA-Z]*)+\b";
            # "(?<!\!|\[\[)\b(([A-Z]+[a-z0-9]+){2,})\b|".
            # "(?<!\!|\[\[)((?:\/?[A-Z]([a-z0-9]+|[A-Z]*(?=[A-Z][a-z0-9]|\b))){2,})\b|".
            # WikiName rule: WikiName ILoveYou (imported from the rule of NoSmoke)
            # and protect WikiName rule !WikiName
            #"(?:\!)?((?:\.{1,2}?\/)?[A-Z]([A-Z]+[0-9a-z]|[0-9a-z]+[A-Z])[0-9a-zA-Z]*)+\b|".

        $this->wordrule.='|'.
            # double bracketed rule similar with MediaWiki [[Hello World]]
            #"(?<!\[)\!?\[\[([^\[:,<\s'][^\[:,>]{1,255})\]\](?!\])|".
            # bracketed with double quotes ["Hello World"]
            #"(?<!\[)\!?\[\\\"([^\\\"]+)\\\"\](?!\])|".
            # "(?<!\[)\[\\\"([^\[:,]+)\\\"\](?!\])|".
            "($urlrule)|".
            # single linkage rule ?hello ?abacus
            #"(\?[A-Z]*[a-z0-9]+)";
            "(\?[A-Za-z0-9]+)";

            #if ($sbracket)
            #  # single bracketed name [Hello World]
            #  $this->_wordrule.= "|(?<!\[)\!?\[([^\[,<\s'][^\[,>]{1,255})\](?!\])";
            #else
            #  # only anchor [#hello], footnote [* note] allowed
            #  $this->wordrule.= "|(?<!\[)\!?\[([#\*\+][^\[:,>]{1,255})\](?!\])";
        return $this->wordrule;
    }

    function header($args) {
        header($args);
    }

    function set_theme($theme = '', $params = array()) {
        set_theme($this, $theme, $params);
    }

    function include_theme($themepath, $file = 'default', $params = array()) {
        include_theme($this, $themepath, $file, $params);
    }

    function _diff_repl($arr) {
        if ($arr[1]{0}=="\010") { $tag='ins'; $sty='added'; }
        else { $tag='del'; $sty='removed'; }
        if (strpos($arr[2],"\n") !== false)
            return "<div class='diff-$sty'>".$arr[2]."</div>";
        return "<$tag class='diff-$sty'>".$arr[2]."</$tag>";
    }

    function write($raw) {
        echo $raw;
    }

    function _url_mappings_callback($m) {
        return $this->url_mappings[$m[1]];
    }

    function link_repl($url,$attr='',$opts=array()) {
        $nm = 0;
        $force = 0;
        $double_bracket = false;
        if (is_array($url)) $url=$url[1];
        #if ($url[0]=='<') { echo $url;return $url;}
        $url=str_replace('\"','"',$url); // XXX
        $bra = '';
        $ket = '';
        if ($url{0}=='[') {
            $bra='[';
            $ket=']';
            $url=substr($url,1,-1);
            $force=1;
        }
        // set nomacro option for callback
        if (!empty($this->nomacro)) $opts['nomacro'] = 1;

        switch ($url[0]) {
            case '{':
                $url=substr($url,3,-3);
                if (empty($url))
                    return "<code class='nowiki'></code>"; # No link
                if (preg_match('/^({([^{}]+)})/s',$url,$sty)) { # textile like styling
                    $url=substr($url,strlen($sty[1]));
                    $url = preg_replace($this->baserule, $this->baserepl, $url); // apply inline formatting rules
                    return "<span style='$sty[2]'>$url</span>";
                }
                if ($url[0]=='#' and ($p=strpos($url,' '))) {
                    $col=strtok($url,' '); $url=strtok('');
                    #$url = str_replace('<', '&lt;', $url);
                    if (!empty($this->use_htmlcolor) and !preg_match('/^#[0-9a-f]{6}$/i', $col)) {
                        $col = substr($col, 1);
                        return "<span style='color:$col'>$url</span>";
                    }
                    if (preg_match('/^#[0-9a-f]{6}$/i',$col))
                        return "<span style='color:$col'>$url</span>";
                    $url=$col.' '.$url;
                } else if (preg_match('/^((?:\+|\-)([1-6]?))(?=\s)(.*)$/',$url,$m)) {
                    if ($m[2]=='') $m[1].='1';
                    $fsz=array(
                            '-5'=>'10%','-4'=>'20%','-3'=>'40%','-2'=>'60%','-1'=>'80%',
                            '+1'=>'140%','+2'=>'180%','+3'=>'220%','+4'=>'260%','+5'=>'200%');
                    return "<span style='font-size:".$fsz[$m[1]]."'>$m[3]</span>";
                }

                $url = str_replace("<","&lt;",$url);
                if ($url[0]==' ' and in_array($url[1],array('#','-','+')) !==false)
                    $url='<span class="markup invisible"> </span>'.substr($url,1);
                return "<code class='wiki'>".$url."</code>"; # No link
                break;
            case '<':
                $nm = 1; // XXX <<MacroName>> support
                $url=substr($url,2,-2);
                preg_match("/^([^\(]+)(\((.*)\))?$/", $url, $match);
                if (isset($match[1])) {
                    $myname = getPlugin($match[1]);
                    if (!empty($myname)) {
                        if (!empty($opts['nomacro'])) return ''; # remove macro
                            return $this->macro_repl($url); # valid macro
                    }
                }
                return '<<'.$url.'>>';
                break;
            case '[':
                $bra.='[';
                $ket.=']';
                $url=substr($url,1,-1);
                $double_bracket = true;

                // mediawiki like built-in category support
                if ($this->use_builtin_category && preg_match('@'.$this->category_regex.'@', $url)) {
                    return $this->macro_repl('Category', $url); # call category macro
                } else if (preg_match("/^([^\(:]+)(\((.*)\))?$/", $url, $match)) {
                    if (isset($match[1])) {
                        $name = $match[1];
                    } else {
                        $name = $url;
                    }

                    // check alias
                    $myname = getPlugin($name);
                    if (!empty($myname)) {
                        if (!empty($opts['nomacro'])) return ''; # remove macro
                            return $this->macro_repl($url); # No link
                    }
                }

                break;
            case '$':
                #return processor_latex($this,"#!latex\n".$url);
                $url=preg_replace('/<\/?sup>/','^',$url);
                //if ($url[1] != '$') $opt=array('type'=>'inline');
                //else $opt=array('type'=>'block');
                $opt=array('type'=>'inline');
                // revert &amp;
                $url = preg_replace('/&amp;/i', '&', $url);
                return $this->processor_repl($this->inline_latex,$url,$opt);
                break;
            case '*':
                if (!empty($opts['nomacro'])) return ''; # remove macro
                    $url = preg_replace($this->baserule, $this->baserepl, $url); // apply inline formatting rules
                return $this->macro_repl('FootNote',$url);
                break;
            case '!':
                $url=substr($url,1);
                return $url;
                break;
            default:
                break;
        }

        if ($url[0] == '#') {
            // Anchor syntax in the MoinMoin 1.1
            $anchor = strtok($url,' |');
            return ($word = strtok('')) ? $this->link_to($anchor, $word):
                "<a id='".substr($anchor, 1)."'></a>";
        }

        //$url=str_replace('&lt;','<',$url); // revert from baserule
        $url=preg_replace('/&(?!#?[a-z0-9]+;)/i','&amp;',$url);

        // ':' could be used in the title string.
        $urltest = $url;
        $tmp = preg_split('/\s|\|/', $url); // [[foobar foo]] or [[foobar|foo]]
        if (count($tmp) > 1) $urltest = $tmp[0];

        if ($url[0] == '"') {
            $url = preg_replace('/&amp;/i', '&', $url);
            // [["Hello World"]], [["Hello World" Page Title]]
            return $this->word_repl($bra.$url.$ket, '', $attr);
        } else
        if (($p = strpos($urltest, ':')) !== false and
            (!isset($url{$p+1}) or (isset($url{$p+1}) and $url{$p+1}!=':'))) {

            // namespaced pages
            // [[한글:페이지]], [[한글:페이지 이름]]
            // mixed name with non ASCII chars
            if (preg_match('/^([^\^a-zA-Z0-9]+.*)\:/', $url))
                return $this->word_repl($bra.$url.$ket, '', $attr);

            if ($url[0]=='a') { # attachment:
                $url=preg_replace('/&amp;/i','&',$url);
                return $this->macro_repl('attachment',substr($url,11));
            }

            $external_icon='';
            $external_link='';
            if ($url[0] == '^') {
                $attr.=' target="_blank" ';
                $url=substr($url,1);
                $external_icon=$this->icon['external'];
            }

            if (!empty($this->url_mappings)) {
                if (!isset($this->url_mapping_rule))
                    $this->macro_repl('UrlMapping', '', array('init'=>1));
                if (!empty($this->url_mapping_rule))
                    $url=
                        preg_replace_callback('/('.$this->url_mapping_rule.')/i',
                                array($this, '_url_mappings_callback'), $url);
            }

            // InterWiki Pages
            if (preg_match("/^(:|w|[A-Z])/",$url)
                    or (!empty($this->urls) and !preg_match('/^('.$this->urls.')/',$url))) {
                $url = preg_replace('/&amp;/i', '&', $url);
                return $this->interwiki_repl($url,'',$attr,$external_icon);
            }

            if (preg_match("/^mailto:/",$url)) {
                $email=substr($url,7);
                $link=strtok($email,' ');
                $myname=strtok('');
                $link=email_guard($link,$this->email_guard);
                $myname=!empty($myname) ? $myname:$link;
                #$link=preg_replace('/&(?!#?[a-z0-9]+;)/i','&amp;',$link);
                return $this->icon['mailto']."<a class='externalLink' href='mailto:$link' $attr>$myname</a>$external_icon";
            }

            if ($force or strstr($url, ' ') or strstr($url, '|')) {
                if (($tok = strtok($url, ' |')) !== false) {
                    $text = strtok('');
                    $text = preg_replace($this->baserule, $this->baserepl, $text);
                    $text = str_replace('&lt;', '<', $text); // revert from baserule
                    $url = $tok;
                }
                #$link=str_replace('&','&amp;',$url);
                $link=preg_replace('/&(?!#?[a-z0-9]+;)/i','&amp;',$url);
                if (!isset($text[0])) $text=$url;
                else {
                    $img_attr='';
                    $img_cls = '';
                    if (preg_match("/^attachment:/",$text)) {
                        $atext=$text;
                        if (($p=strpos($text,'?')) !== false) {
                            $atext=substr($text,0,$p);
                            parse_str(substr($text,$p+1),$attrs);
                            foreach ($attrs as $n=>$v) {
                                if ($n == 'align') $img_cls = ' img'.ucfirst($v);
                                else
                                    $img_attr.="$n=\"$v\" ";
                            }
                        }

                        $msave = $this->_macrocache;
                        $this->_macrocache = 0;
                        $fname = $this->macro_repl('attachment', substr($text, 11), 1);
                        if (file_exists($fname))
                            $text = qualifiedUrl($this->url_prefix.'/'.$fname);
                        else
                            $text = $this->macro_repl('attachment', substr($text, 11));
                        $this->_macrocache = $msave; // restore _macrocache
                    }
                    $text = preg_replace('/&amp;/i', '&', $text);
                    if (preg_match("/^((?:https?|ftp).*\.(png|gif|jpeg|jpg))(?:\?|&(?!>amp;))?(.*?)?$/i",$text, $match)) {
                        // FIXME call externalimage macro for these external images
                        if (!empty($this->external_image_regex) and preg_match('@'.$this->external_image_regex.'@x', $match[0])) {
                            $res = $this->macro_repl('ExternalImage', $natch[0]);
                            if ($res !== false)
                                return $res;
                        }

                        $cls = 'externalImage';
                        $type = strtoupper($match[2]);
                        $atext=isset($atext[0]) ? $atext:$text;
                        $url = str_replace('&','&amp;',$match[1]);
                        // trash dummy query string
                        $url = preg_replace('@(\?|&)\.(png|gif|jpe?g)$@', '', $url);
                        $tmp = !empty($match[3]) ? preg_replace('/&amp;/', '&', $match[3]) : '';
                        $attrs = explode('&', $tmp);
                        $eattr = array();
                        foreach ($attrs as $a) {
                            $name = strtok($a, '=');
                            $val = strtok(' ');
                            if ($name == 'align') $cls.=' img'.ucfirst($val);
                            else if ($name and $val) $eattr[] = $name.'="'.urldecode($val).'"';
                        }

                        $info = '';
                        // check internal links and fetch image
                        if (!empty($this->fetch_images) and !preg_match('@^https?://'.$_SERVER['HTTP_HOST'].'@', $url)) {
                            $url = $this->fetch_action. str_replace(array('&', '?'), array('%26', '%3f'), $url);
                            $size = '';
                            if (!empty($this->fetch_imagesize))
                                $size = '('.$this->macro_repl('ImageFileSize', $url).')';

                            // use thumbnails ?
                            if (!empty($this->use_thumb_by_default)) {
                                if (!empty($this->no_gif_thumbnails)) {
                                    if ($type != 'GIF')
                                        $url.= '&amp;thumbwidth='.$this->thumb_width;
                                } else {
                                    $url.= '&amp;thumbwidth='.$this->thumb_width;
                                }
                            }

                            $info = "<div class='info'><a href='$url'><span>[$type "._("external image")."$size]</span></a></div>";
                        }
                        $iattr = '';
                        if (isset($eattr[0]))
                            $iattr = implode(' ', $eattr);

                        return "<div class='$cls$img_cls'><div><a class='externalLink named' href='$link' $attr $this->external_target title='$link'><img $iattr alt='$atext' src='$url' $img_attr/></a>".$info.'</div></div>';
                    }
                    if (!empty($this->external_on))
                        $external_link='<span class="externalLink">('.$url.')</span>';
                }
                $icon = '';
                if (substr($url,0,7)=='http://' and $url[7]=='?') {
                    $link=substr($url,7);
                    return "<a href='$link'>$text</a>";
                } else if ($this->check_openid_url and preg_match("@^https?://@i",$url)) {
                    if (is_object($this->udb) and $this->udb->_exists($url)) {
                        $icon='openid';
                        $icon="<a class='externalLink' href='$link'><img class='url' alt='[$icon]' src='".$this->imgs_dir_url."$icon.png' /></a>";
                        $attr.=' title="'.$link.'"';
                        $link=$this->link_url(_rawurlencode($text));
                    }
                }
                if (empty($this->_no_urlicons) and empty($icon)) {
                    $icon= strtok($url,':');
                    $icon="<img class='url' alt='[$icon]' src='".$this->imgs_dir_url."$icon.png' />";
                }
                if ($text != $url) $eclass='named';
                else $eclass='unnamed';
                $link =str_replace(array('<','>'),array('&#x3c;','&#x3e;'),$link);
                return $icon. "<a class='externalLink $eclass' $attr $this->external_target href='$link'>$text</a>".$external_icon.$external_link;
            } # have no space
            $link = str_replace(array('<','>'),array('&#x3c;','&#x3e;'),$url);
            if (preg_match("/^(http|https|ftp)/",$url)) {
                $url1 = preg_replace('/&amp;/','&',$url);
                if (preg_match("/(^.*\.(png|gif|jpeg|jpg))(?:\?|&(?!>amp;))?(.*?)?$/i", $url1, $match)) {
                    // FIXME call externalimage macro for these external images
                    if (!empty($this->external_image_regex) and preg_match('@'.$this->external_image_regex.'@x', $url1)) {
                        $res = $this->macro_repl('ExternalImage', $url1);
                        if ($res !== false)
                            return $res;
                    }

                    $cls = 'externalImage';
                    $url=$match[1];
                    // trash dummy query string
                    $url = preg_replace('@(\?|&)\.(png|gif|jpe?g)$@', '', $url);
                    $type = strtoupper($match[2]);
                    $attrs = !empty($match[3]) ? explode('&', $match[3]) : array();
                    $eattr = array();
                    foreach ($attrs as $arg) {
                        $name=strtok($arg,'=');
                        $val=strtok(' ');
                        if ($name == 'align') $cls.=' img'.ucfirst($val);
                        else if ($name and $val) $eattr[] = $name.'="'.urldecode($val).'"';
                    }
                    $attr = '';
                    if (isset($eattr[0]))
                        $attr = implode(' ', $eattr);

                    // XXX fetch images
                    $fetch_url = $url;
                    $info = '';
                    // check internal images
                    if (!empty($this->fetch_images) and !preg_match('@^https?://'.$_SERVER['HTTP_HOST'].'@', $url)) {
                        $fetch_url = $this->fetch_action.
                            str_replace(array('&', '?'), array('%26', '%3f'), $url);

                        $size = '';
                        if (!empty($this->fetch_imagesize))
                            $size = '('.$this->macro_repl('ImageFileSize', $fetch_url).')';

                        // use thumbnails ?
                        if (!empty($this->use_thumb_by_default)) {
                            if (!empty($this->no_gif_thumbnails)) {
                                if ($type != 'GIF')
                                    $fetch_url.= '&amp;thumbwidth='.$this->thumb_width;
                            } else {
                                $fetch_url.= '&amp;thumbwidth='.$this->thumb_width;
                            }
                        }

                        $info = "<div class='info'><a href='$url'><span>[$type "._("external image")."$size]</span></a></div>";
                    }

                    return "<div class=\"$cls\"><div><img alt='$link' $attr src='$fetch_url' />".$info.'</div></div>';
                }
            }
            if (substr($url,0,7)=='http://' and $url[7]=='?') {
                $link=substr($url,7);
                return "<a class='internalLink' href='$link'>$link</a>";
            }
            $url=urldecode($url);

            // auto detect the encoding of a given URL
            if (function_exists('mb_detect_encoding'))
                $url = _autofixencode($url);

            return "<a class='externalLink' $attr href='$link' $this->external_target>$url</a>";
        } else {
            if ($url{0}=='?')
                $url=substr($url,1);

            $url = preg_replace('/&amp;/i', '&', $url);
            return $this->word_repl($bra.$url.$ket, '', $attr);
        }
    }

    function interwiki_repl($url,$text='',$attr='',$extra='') {
        global $DBInfo;

        /**
         * wiki: FrontPage => wiki:FrontPage (Rigveda fix) FIXME
         * wiki:MoinMoin:FrontPage
         * wiki:MoinMoin/FrontPage is not supported.
         * wiki:"Hello World" or wiki:Hello_World, wiki:Hello%20World work
         *
         * wiki:MoinMoin:"Hello World"
         * [wiki:"Hello World" hello world] - spaced
         * [wiki:"Hello World"|hello world] - | separator
         * [wiki:"Hello World"hello world] - no separator but separable
         * [wiki:Hello|World hello world] == [wiki:Hello World hello world]
         * [wiki:Hello World|hello world] == [wiki:"Hello" World|hello world] - be careful!!
         */
        $wiki = '';
        if (isset($url[0]) &&
                /* unified interwiki regex */
                preg_match('@^(wiki:\s*)?(?:([A-Z][a-zA-Z0-9]+):)?
                    (")?([^"|]+?)(?(3)")
                    (?(3)(?:\s+|\\|)?(.*)|(?:\s+|\\|)(.*))?$@x', $url, $m)) {
            $wiki = $m[2];
            $url = $m[4];
            $text = isset($m[5][0]) ? $m[5] : $m[6];
        }

        if (empty($wiki)) {
            # wiki:FrontPage (not supported in the MoinMoin)
            # or [wiki:FrontPage Home Page]
            return $this->word_repl($url,$text.$extra,$attr,1);
        }

        if (empty($DBInfo->interwiki)) {
            $this->macro_repl('InterWiki', '', array('init'=>1));
        }

        // invalid InterWiki name
        if (empty($DBInfo->interwiki[$wiki])) {
            #$dum0=preg_replace("/(".$this->wordrule.")/e","\$this->link_repl('\\1')",$wiki);
            #return $dum0.':'.($page?$this->link_repl($page,$text):'');

            return $this->word_repl("$wiki:$url",$text.$extra,$attr,1);
        }

        $icon=$this->imgs_url_interwiki.strtolower($wiki).'-16.png';
        $sx=16;$sy=16;
        if (isset($DBInfo->intericon[$wiki])) {
            $icon=$DBInfo->intericon[$wiki][2];
            $sx=$DBInfo->intericon[$wiki][0];
            $sy=$DBInfo->intericon[$wiki][1];
        }

        $page=$url;
        $url=$DBInfo->interwiki[$wiki];

        if (isset($page[0]) and $page[0]=='"') # "extended wiki name"
            $page=substr($page,1,-1);

        if ($page=='/') $page='';
        $sep='';
        if (substr($page,-1)==' ') {
            $sep='<b></b>'; // auto append SixSingleQuotes
            $page=rtrim($page);
        }
        $urlpage=_urlencode($page);
        #$urlpage=trim($page);
        if (strpos($url,'$PAGE') === false)
            $url.=$urlpage;
        else {
            # GtkRef http://developer.gnome.org/doc/API/2.0/gtk/$PAGE.html
            # GtkRef:GtkTreeView#GtkTreeView
            # is rendered as http://...GtkTreeView.html#GtkTreeView
            $page_only=strtok($urlpage,'#?');
            $query= substr($urlpage,strlen($page_only));
            #if ($query and !$text) $text=strtok($page,'#?');
            $url=str_replace('$PAGE',$page_only,$url).$query;
        }


        $img="<a class=\"interwiki\" href='$url' $this->interwiki_target>".
            "<img class=\"interwiki\" alt=\"$wiki:\" src='$icon' style='border:0' height='$sy' ".
            "width='$sx' title='$wiki:' /></a>";
        #if (!$text) $text=str_replace("%20"," ",$page);
        if (!$text) $text=urldecode($page);
        else if (preg_match("/^(http|ftp|attachment):.*\.(png|gif|jpeg|jpg)$/i",$text)) {
            if (substr($text,0,11)=='attachment:') {
                $fname=substr($text,11);
                $ntext=$this->macro_repl('Attachment',$fname,1);
                if (!file_exists($ntext))
                    $text=$this->macro_repl('Attachment',$fname);
                else {
                    $text=qualifiedUrl($this->url_prefix.'/'.$ntext);
                    $text= "<img style='border:0' alt='$text' src='$text' />";
                }
            } else
                $text= "<img style='border:0' alt='$text' src='$text' />";
            $img='';
        }

        if (preg_match("/\.(png|gif|jpeg|jpg)$/i",$url))
            return "<a href='".$url."' $attr title='$wiki:$page'><img style='vertical-align:middle;border:0px' alt='$text' src='$url' /></a>$extra";

        if (!$text) return $img;
        return $img. "<a href='".$url."' $attr title='$wiki:$page'>$text</a>$extra$sep";
    }

    function get_pagelinks() {
        if (!is_object($this->cache))
            $this->cache= new Cache_text('pagelinks');

        if ($this->cache->exists($this->page->name)) {
            $links=$this->cache->fetch($this->page->name);
            if ($links !== false) return $links;
        }
        $links = get_pagelinks($this, $this->page->_get_raw_body());
        return $links;
    }

    function get_backlinks() {
        if (!is_object($this->bcache))
            $this->bcache= new Cache_text('backlinks');

        if ($this->bcache->exists($this->page->name)) {
            $links=$this->bcache->fetch($this->page->name);
            if ($links !== false) return $links;
        }
        // no backlinks found. XXX
        return array();
    }

    function word_repl($word,$text='',$attr='',$nogroup=0,$islink=1) {
        require_once(dirname(__FILE__).'/lib/xss.php');

        global $DBInfo;
        $nonexists='nonexists_'.$this->nonexists;

        $word = $page = trim($word, '[]'); // trim out [[Hello World]] => Hello World

        $extended = false;
        if (($word[0] == '"' or $word[0] == 'w') and preg_match('/^(?:wiki\:)?((")?[^"]+\2)((\s+|\|)?(.*))?$/', $word, $m)) {
            # ["extended wiki name"]
            # ["Hello World" Go to Hello]
            # [wiki:"Hello World" Go to Main]
            $word = substr($m[1], 1, -1);
            if (isset($m[5][0])) $text = $m[5]; // text arg ignored

            $extended=true;
            $page=$word;
        } else if (($p = strpos($word, '|')) !== false) {
            // or MediaWiki/WikiCreole like links
            $text = substr($word, $p + 1);
            $word = substr($word, 0, $p);
            $page = $word;
        } else {
            // check for [[Hello attachment:foo.png]] case
            $tmp = strtok($word, ' |');
            $last = strtok('');
            if (($p = strpos($last, ' ')) === false && substr($last, 0, 11) == 'attachment:') {
                $text = $last;
                $word = $tmp;
                $page = $word;
            }
        }

        if (!$extended and empty($DBInfo->mediawiki_style)) {
            #$page=preg_replace("/\s+/","",$word); # concat words
            $page=normalize($word); # concat words
        }

        if (empty($DBInfo->use_twikilink)) $islink=0;
        list($page,$page_text,$gpage)=
            normalize_word($page,$this->group,$this->page->name,$nogroup,$islink);
        if (isset($text[0])) {
            if (preg_match("/^(http|ftp|attachment).*\.(png|gif|jpeg|jpg)$/i",$text)) {
                if (substr($text,0,11)=='attachment:') {
                    $fname=substr($text,11);
                    $ntext=$this->macro_repl('attachment',$fname,1);
                    if (!file_exists($ntext)) {
                        $word=$this->macro_repl('attachment',$fname);
                    } else {
                        $text=qualifiedUrl($this->url_prefix.'/'.$ntext);
                        $word= "<img style='border:0' alt='$text' src='$text' /></a>";
                    }
                } else {
                    $text=str_replace('&','&amp;',$text);
                    // trash dummy query string
                    $text = preg_replace('@(\?|&)\.(png|gif|jpe?g)$@', '', $text);

                    if (!empty($this->fetch_images) and !preg_match('@^https?://'.$_SERVER['HTTP_HOST'].'@', $text))
                        $text = $this->fetch_action. str_replace(array('&', '?'), array('%26', '%3f'), $text);

                    $word="<img style='border:0' alt='$word' src='$text' /></a>";
                }
            } else {
                $word = preg_replace($this->baserule, $this->baserepl, $text);
                $word = str_replace('&lt;', '<', $word); // revert from baserule
                $word = _xss_filter($word);
            }
        } else {
            $word=$text=$page_text ? $page_text:$word;
            #echo $text;
            $word=_html_escape($word);
        }

        $url=_urlencode($page);
        $url_only=strtok($url,'#?'); # for [WikiName#tag] [wiki:WikiName#tag Tag]
        #$query= substr($url,strlen($url_only));
        if ($extended) $page=rawurldecode($url_only); # C++
        else $page=urldecode($url_only);
        $url=$this->link_url($url);

        #check current page
        if ($page == $this->page->name) $attr.=' class="current"';

        if (!empty($this->forcelink))
            return $this->nonexists_always($word, $url, $page);

        //$url=$this->link_url(_rawurlencode($page)); # XXX
        $idx = 0; // XXX
        if (isset($this->pagelinks[$page])) {
            $idx=$this->pagelinks[$page];
            switch($idx) {
                case 0:
                    #return "<a class='nonexistent' href='$url'>?</a>$word";
                    return call_user_func(array(&$this,$nonexists),$word,$url,$page);
                case -1:
                    $title='';
                    $tpage=urlencode($page);
                    if ($tpage != $word) $title = 'title="'._html_escape($page).'" ';
                    return "<a href='$url' $title$attr>$word</a>";
                case -2:
                    return "<a href='$url' $attr>$word</a>".
                        "<tt class='sister'><a href='$url'>&#x203a;</a></tt>";
                case -3:
                    #$url=$this->link_url(_rawurlencode($gpage));
                    return $this->link_tag(_rawurlencode($gpage),'',$this->icon['main'],'class="main"').
                        "<a href='$url' $attr>$word</a>";
                default:
                    return "<a href='$url' $attr>$word</a>".
                        "<tt class='sister'><a href='#sister$idx'>&#x203a;$idx</a></tt>";
            }
        } else if ($DBInfo->hasPage($page)) {
            $title='';
            $this->pagelinks[$page]=-1;
            $tpage=urlencode($page);
            if ($tpage != $word) $title = 'title="'._html_escape($page).'" ';
            return "<a href='$url' $title$attr>$word</a>";
        } else {
            if ($gpage and $DBInfo->hasPage($gpage)) {
                $this->pagelinks[$page]=-3;
                #$url=$this->link_url(_rawurlencode($gpage));
                return $this->link_tag(_rawurlencode($gpage),'',$this->icon['main'],'class="main"').
                    "<a href='$url' $attr>$word</a>";
            }
            if (!empty($this->aliases[$page])) return $this->aliases[$page];
            if (!empty($this->sister_on)) {
                if (empty($DBInfo->metadb)) $DBInfo->initMetaDB();
                $sisters=$DBInfo->metadb->getSisterSites($page, $DBInfo->use_sistersites);
                if ($sisters === true) {
                    $this->pagelinks[$page]=-2;
                    return "<a href='$url'>$word</a>".
                        "<tt class='sister'><a href='$url'>&#x203a;</a></tt>";
                }
                if (!empty($sisters)) {
                    if (!empty($this->use_easyalias) and !preg_match('/^\[wiki:[A-Z][A-Za-z0-9]+:.*$/', $sisters)) {
                        # this is a alias
                        $this->use_easyalias=0;
                        $tmp = explode("\n", $sisters);
                        $url=$this->link_repl(substr($tmp[0],0,-1).' '.$word.']');
                        $this->use_easyalias=1;
                        $this->aliases[$page]=$url;
                        return $url;
                    }
                    $this->sisters[]=
                        "<li><tt class='foot'><a id='sister$this->sister_idx'></a>".
                        "<a href='#rsister$this->sister_idx'>$this->sister_idx&#x203a;</a></tt> ".
                        "$sisters </li>";
                    $this->pagelinks[$page]=$this->sister_idx++;
                    $idx=$this->pagelinks[$page];
                }
                if ($idx > 0) {
                    return "<a href='$url'>$word</a>".
                        "<tt class='sister'>".
                        "<a id='rsister$idx'></a>".
                        "<a href='#sister$idx'>&#x203a;$idx</a></tt>";
                }
            }
            $this->pagelinks[$page]=0;
            #return "<a class='nonexistent' href='$url'>?</a>$word";
            return call_user_func(array(&$this,$nonexists),$word,$url,$page);
        }
    }

    function nonexists_simple($word, $url, $page) {
        $title = '';
        if ($page != $word) $title = 'title="'._html_escape($page).'" ';
        return "<a class='nonexistent nomarkup' {$title}href='$url' rel='nofollow'>?</a>$word";
    }

    function nonexists_nolink($word,$url) {
        return "$word";
    }

    function nonexists_always($word,$url,$page) {
        $title = '';
        if ($page != $word) $title = 'title="'._html_escape($page).'" ';
        return "<a href='$url' {$title}rel='nofollow'>$word</a>";
    }

    function nonexists_forcelink($word, $url, $page) {
        $title = '';
        if ($page != $word) $title = 'title="'._html_escape($page).'" ';
        return "<a class='nonexistent' rel='nofollow' {$title}href='$url'>$word</a>";
    }

    function nonexists_fancy($word, $url, $page) {
        global $DBInfo;
        $title = '';
        if ($page != $word) $title = 'title="'._html_escape($page).'" ';
        if ($word[0]=='<' and preg_match('/^<[^>]+>/',$word))
            return "<a class='nonexistent' rel='nofollow' {$title}href='$url'>$word</a>";
            #if (preg_match("/^[a-zA-Z0-9\/~]/",$word))
        if (ord($word[0]) < 125) {
            $link=$word[0];
            if ($word[0]=='&') {
                $link=strtok($word,';').';';$last=strtok('');
            } else
                $last=substr($word,1);
            return "<span><a class='nonexistent' rel='nofollow' {$title}href='$url'>$link</a>".$last.'</span>';
        }
        if (strtolower($DBInfo->charset) == 'utf-8')
            $utfword=$word;
        else if (function_exists('iconv')) {
            $utfword=iconv($DBInfo->charset,'utf-8',$word);
        }
        while ($utfword !== false and isset($utfword[0])) {
            preg_match('/^(.)(.*)$/u', $utfword, $m);
            if (!empty($m[1])) {
                $tag = $m[1];
                if (strtolower($DBInfo->charset) != 'utf-8' and function_exists('iconv')) {
                    $tag = iconv('utf-8', $DBInfo->charset, $tag);
                    if ($tag === false) break;
                    $last = substr($word, strlen($tag));
                } else {
                    $last = !empty($m[2]) ? $m[2] : '';
                }
                return "<span><a class='nonexistent' rel='nofollow' {$title}href='$url'>$tag</a>".$last.'</span>';
            }
            break;
        }
        return "<a class='nonexistent' rel='nofollow' {$title}href='$url'>$word</a>";
    }

    function head_repl($depth,$head,&$headinfo,$attr='') {
        $dep=$depth < 6 ? $depth : 5;
        $this->nobr=1;

        if ($headinfo == null)
            return "<h$dep$attr>$head</h$dep>";

        $head=str_replace('\"','"',$head); # revert \\" to \"

            if (!$headinfo['top']) {
                $headinfo['top']=$dep; $depth=1;
            } else {
                $depth=$dep - $headinfo['top'] + 1;
                if ($depth <= 0) $depth=1;
            }

        #    $depth=$dep;
        #    if ($dep==1) $depth++; # depth 1 is regarded same as depth 2
        #    $depth--;

        $num=''.$headinfo['num'];
        $odepth=$headinfo['dep'];

        if ($head[0] == '#') {
            # reset TOC numberings
            # default prefix is empty.
            if (!empty($this->toc_prefix)) $this->toc_prefix++;
            else $this->toc_prefix=1;
            $head[0]=' ';
            $dum=explode(".",$num);
            $i=sizeof($dum);
            for ($j=0;$j<$i;$j++) $dum[$j]=1;
            $dum[$i-1]=0;
            $num=implode('.', $dum);
        }
        $open="";
        $close="";

        if ($odepth && ($depth > $odepth)) {
            $num.=".1";
        } else if ($odepth) {
            $dum=explode(".",$num);
            $i=sizeof($dum)-1;
            while ($depth < $odepth && $i > 0) {
                unset($dum[$i]);
                $i--;
                $odepth--;
            }
            $dum[$i]++;
            $num=implode('.', $dum);
        }

        $headinfo['dep']=$depth; # save old
            $headinfo['num']=$num;

        $prefix=$this->toc_prefix;
        if ($this->toc)
            $head="<span class='tocnumber'><a href='#toc'>$num<span class='dot'>.</span></a> </span>$head";
        $perma='';
        if (!empty($this->perma_icon))
            $perma=" <a class='perma' href='#s$prefix-$num'>$this->perma_icon</a>";

        return "$close$open<h$dep$attr><a id='s$prefix-$num'></a>$head$perma</h$dep>";
    }

    function include_functions()
    {
        foreach (func_get_args() as $f) function_exists($f) or include_once 'plugin/function/'.$f.'.php';
    }

    function macro_repl($macro, $value = '', $params = array()) {
        // FIXME
        //return call_macro($this, $macro, $value, $params);
        return call_user_func_array('call_macro', array(&$this, $macro, $value, &$params));
    }

    function macro_cache_repl($name, $args)
    {
        $arg = '';
        if ($args === true) $arg = '()';
        else if (!empty($args)) $arg = '('.$args.')';
        $macro = $name.$arg;
        $md5sum = md5($macro);
        $this->_dynamic_macros[$macro] = array($md5sum, $this->mid);
        return '@@'.$md5sum.'@@';
    }

    function processor_repl($processor, $value, $params = array()) {
        return call_processor($this, $processor, $value, $params);
    }

    function filter_repl($filter, $value, $params = array()) {
        return call_filter($this, $filter, $value, $params);
    }

    function postfilter_repl($filter, $value, $params = array()) {
        return call_postfilter($this, $filter, $value, $params);
    }

    function ajax_repl($plugin, $params = array()) {
        return call_action($this, 'ajax', $plugin, $params);
    }

    function smiley_repl($smiley) {
        // check callback style
        if (is_array($smiley)) $smiley = $smiley[1];
        $img=$this->smileys[$smiley][3];

        $alt=str_replace("<","&lt;",$smiley);

        if (preg_match('/^(https?|ftp):/',$img))
            return "<img src='$img' style='border:0' class='smiley' alt='$alt' title='$alt' />";
        return "<img src='$this->imgs_dir/$img' style='border:0' class='smiley' alt='$alt' title='$alt' />";
    }

    function link_url($pageurl, $query_string='') {
        global $DBInfo;
        $sep=$DBInfo->query_prefix;

        if (empty($query_string)) {
            if (isset($this->query_string)) $query_string=$this->query_string;
        } else if ($query_string[0] == '#') {
            $query_string= $this->self_query.$query_string;
        }

        if ($sep == '?') {
            if (isset($pageurl[0]) && isset($query_string[0]) && $query_string[0]=='?')
                # add 'dummy=1' to work around the buggy php
                $query_string= '&amp;'.substr($query_string,1).'&amp;dummy=1';
            # Did you have a problem with &amp;dummy=1 ?
            # then, please replace above line with next line.
            #$query_string= '&amp;'.substr($query_string,1);
            $query_string= $pageurl.$query_string;
        } else
            $query_string= $pageurl.$query_string;
        return $this->prefix . $sep . $query_string;
    }

    function link_tag($pageurl,$query_string="", $text="",$attr="") {
        # Return a link with given query_string.
        $text = strval($text);
        if (!isset($text[0]))
            $text= $pageurl; # XXX
                if (!isset($pageurl[0]))
                    $pageurl=$this->page->urlname;
        if (isset($query_string[0]) and $query_string[0]=='?')
            $attr=empty($attr) ? 'rel="nofollow"' : $attr;
        $url=$this->link_url($pageurl,$query_string);
        return '<a href="'.$url.'" '. $attr .'><span>'.$text.'</span></a>';
    }

    function link_to($query_string="",$text="",$attr="") {
        if (empty($text))
            $text=_html_escape($this->page->name);

        return $this->link_tag($this->page->urlname,$query_string,$text,$attr);
    }

    function fancy_hr($rule) {
        $sz=($sz=strlen($rule)-4) < 6 ? ($sz ? $sz+2:0):8;
        $size=$sz ? " style='height:{$sz}px'":'';
        return "<div class='separator'><hr$size /></div>";
    }

    function simple_hr() {
        return "<div class='separator'><hr /></div>";
    }

    function _fixpath() {
        //$this->url_prefix= qualifiedUrl($this->url_prefix);
        $this->prefix= qualifiedUrl($this->prefix);
        $this->imgs_dir= qualifiedUrl($this->imgs_dir);
        $this->imgs_url_interwiki=qualifiedUrl($this->imgs_url_interwiki);
        $this->imgs_dir_url=qualifiedUrl($this->imgs_dir_url);
    }

    function postambles() {
        $save= $this->wikimarkup;
        $this->wikimarkup=0;
        if (!empty($this->postamble)) {
            $sz=sizeof($this->postamble);
            for ($i=0;$i<$sz;$i++) {
                $postamble=implode("\n",$this->postamble);
                if (!trim($postamble)) continue;
                list($type,$name,$val)=explode(':',$postamble,3);
                if (in_array($type,array('macro','processor'))) {
                    switch($type) {
                        case 'macro':
                            echo $this->macro_repl($name,$val,$options);
                            break;
                        case 'processor':
                            echo $this->processor_repl($name,$val,$options);
                            break;
                    }
                }
            }
        }
        $this->wikimarkup=$save;
    }

    function send_page($body="",$options=array()) {
        global $DBInfo;
        if (!empty($options['fixpath'])) $this->_fixpath();
        // reset macro ID
        $this->mid=0;

        if ($this->wikimarkup == 1) $this->nonexists='always';

        if (isset($body[0])) {
            unset($this->page->pi['#format']); // reset page->pi to get_instructions() again
            $this->text = $body;
            $pi=$this->page->get_instructions($body);

            if ($this->wikimarkup and $pi['raw']) {
                $pi_html=str_replace("\n","<br />\n",$pi['raw']);
                echo "<span class='wikiMarkup'><!-- wiki:\n$pi[raw]\n-->$pi_html</span>";
            }
            $this->set_wordrule($pi);
            $fts=array();
            if (isset($pi['#filter'])) $fts=preg_split('/(\||,)/',$pi['#filter']);
            if (!empty($this->filters)) $fts=array_merge($fts,$this->filters);
            if (!empty($fts)) {
                foreach ($fts as $ft) {
                    $body=$this->filter_repl($ft,$body,$options);
                }
            }
            if (isset($pi['#format']) and $pi['#format'] != 'wiki') {
                $pi_line='';
                if (!empty($pi['args'])) $pi_line="#!".$pi['#format']." $pi[args]\n";
                $savepi=$this->pi; // hack;;
                $this->pi=$pi;
                $opts = $options;
                $opts['nowrap'] = 1;
                if (isset($pi['start_line'])) {
                    $opts['.start_line'] = $pi['start_line'];
                    $pi_line = ''; // do not append $pi_line for this case.
                }
                $text= $this->processor_repl($pi['#format'],
                        $pi_line.$body,$opts);
                $this->pi=$savepi;
                if ($this->use_smartdiff)
                    $text= preg_replace_callback(array("/(\006|\010)(.*)\\1/sU"),
                            array(&$this,'_diff_repl'),$text);

                $fts=array();
                if (isset($pi['#postfilter'])) $fts=preg_split('/(\||,)/',$pi['#postfilter']);
                if (!empty($this->postfilters)) $fts=array_merge($fts,$this->postfilters);
                if (!empty($fts)) {
                    foreach ($fts as $ft)
                        $text=$this->postfilter_repl($ft,$text,$options);
                }
                $this->postambles();

                if (empty($options['nojavascript']))
                    echo $this->get_javascripts();
                echo $text;

                return;
            }
            // strtr old wiki markups
            if (!empty($this->trtags))
                $body = strtr($body, $this->trtags);
            $lines=explode("\n",$body);
            $el = end($lines);
            // delete last empty line
            if (!isset($el[0])) array_pop($lines);
        } else {
            # XXX need to redesign pagelink method ?
            if (empty($DBInfo->without_pagelinks_cache)) {
                if (empty($this->cache) or !is_object($this->cache))
                    $this->cache= new Cache_text('pagelinks');

                $dmt= $DBInfo->mtime();
                $this->update_pagelinks= $dmt > $this->cache->mtime($this->page->name);
                #like as..
                #if (!$this->update_pagelinks) $this->pagelinks=$this->get_pagelinks();
            }

            if (isset($options['rev'])) {
                $body=$this->page->get_raw_body($options);
                $pi=$this->page->get_instructions($body);
            } else {
                $pi=$this->page->get_instructions('', $options);
                $body=$this->page->get_raw_body($options);
            }
            $this->text = &$body; // XXX

            $this->set_wordrule($pi);
            if (!empty($this->wikimarkup) and !empty($pi['raw']))
                echo "<span class='wikiMarkup'><!-- wiki:\n$pi[raw]\n--></span>";

            if (!empty($this->use_rating) and empty($this->wikimarkup) and empty($pi['#norating'])) {
                $this->pi=$pi;
                $old=$this->mid;
                if (isset($pi['#rating'])) $rval=$pi['#rating'];
                else $rval='0';

                echo '<div class="wikiRating">'.$this->macro_repl('Rating',$rval,array('mid'=>'page'))."</div>\n";
                $this->mid=$old;
            }

            $fts=array();
            if (isset($pi['#filter'])) $fts=preg_split('/(\||,)/',$pi['#filter']);
            if (!empty($this->filters)) $fts=array_merge($fts,$this->filters);
            if ($fts) {
                foreach ($fts as $ft) {
                    $body=$this->filter_repl($ft,$body,$options);
                }
            }

            $this->pi=$pi;
            if (isset($pi['#format']) and $pi['#format'] != 'wiki') {
                $pi_line='';
                if (isset($pi['args'])) $pi_line="#!".$pi['#format']." $pi[args]\n";
                $opts = $options;
                $opts['nowrap'] = 1;
                if (!empty($pi['start_line'])) {
                    // trash PI instructions
                    $i = $pi['start_line'];
                    // set $start param
                    $opts['start'] = $i;
                    $pos = 0;
                    while (($p = strpos($body, "\n", $pos)) !== false and $i > 0) {
                        $pos = $p + 1;
                        $i --;
                    }
                    if ($pos > 0) {
                        $body = substr($body, $pos);
                    }
                    $opts['.start_offset'] = $pi['start_line'];
                }
                $text= $this->processor_repl($pi['#format'],$pi_line.$body,$opts);

                $fts=array();
                if (isset($pi['#postfilter'])) $fts=preg_split('/(\||,)/',$pi['#postfilter']);
                if (!empty($this->postfilters)) $fts=array_merge($fts,$this->postfilters);
                if ($fts) {
                    foreach ($fts as $ft)
                        $text=$this->postfilter_repl($ft,$text,$options);
                }
                $this->postambles();
                echo $this->get_javascripts();
                echo $text;

                if (!empty($DBInfo->use_tagging) and isset($pi['#keywords'])) {
                    $tmp="----\n";
                    if (is_string($DBInfo->use_tagging))
                        $tmp.=$DBInfo->use_tagging;
                    else
                        $tmp.=_("Tags:")." [[Keywords]]";
                    $this->send_page($tmp); // XXX
                }
                //$this->store_pagelinks(); // XXX
                return;
            }

            if (!empty($body)) {
                // strtr old wiki markups
                if (!empty($this->trtags))
                    $body = strtr($body, $this->trtags);

                $lines=explode("\n",$body);
                $el = end($lines);
                // delete last empty line
                if (!isset($el[0])) array_pop($lines);
            } else
                $lines=array();

            if (!empty($DBInfo->use_tagging) and isset($pi['#keywords'])) {
                $lines[]="----";
                if (is_string($DBInfo->use_tagging))
                    $lines[]=$DBInfo->use_tagging;
                else
                    $lines[]="Tags: [[Keywords]]";
            }

            $twin_mode=$DBInfo->use_twinpages;
            if (isset($pi['#twinpages'])) $twin_mode=$pi['#twinpages'];
            if (empty($DBInfo->metadb)) $DBInfo->initMetaDB();
            $twins=$DBInfo->metadb->getTwinPages($this->page->name,$twin_mode);

            if ($twins === true) {
                if (!empty($DBInfo->use_twinpages)) {
                    if (!empty($lines)) $lines[]="----";
                    $lines[]=sprintf(_("See %s"),"[wiki:TwinPages:".$this->page->name." "._("TwinPages")."]");
                }
            } else if (!empty($twins)) {
                if (!empty($lines)) $lines[]="----";
                if (sizeof($twins)>8) $twins[0]="\n".$twins[0]; // XXX
                $twins[0]=_("See TwinPages : ").$twins[0];
                $lines=array_merge($lines,$twins);
            }
        }

        # is it redirect page ?
        if (isset($pi['#redirect'][0]) and
                $this->wikimarkup != 1)
        {
            $url = $pi['#redirect'];
            $anchor = '';
            if (($p = strpos($url, '#')) > 0) {
                $anchor = substr($url, $p);
                $url = substr($url, 0, $p);
            }
            if (preg_match('@^https?://@', $url)) {
                $text = rawurldecode($url);
                $lnk = '<a href="'.$url.$anchor.'">'.$text.$anchor.'</a>';
            } else {
                $text = $url;
                $url = _urlencode($url);
                $lnk = $this->link_tag($url,
                        '?action=show'.$anchor,
                        $text).$anchor;
            }
            $msg = _("Redirect page");
            $this->write("<div class='wikiRedirect'><span>$msg</span><p>".$lnk."</p></div>");
        }

        # have no contents
        if (empty($lines)) return;

        $is_writable = 1;
        if (!$DBInfo->security->writable($options))
            $is_writable = 0;
        if ($this->source_site)
            $is_writable = 1;
        $options['.is_writable'] = $is_writable;
        if (isset($pi['start_line']))
            $options['.start_line'] = $pi['start_line'];

        $text = $this->processor_repl('monicompat', $lines, $options);

        # postamble
        $this->postambles();

        if (empty($options['nojavascript']))
            echo $this->get_javascripts();
        echo $text;
        if (!empty($this->sisters) and empty($options['nosisters'])) {
            $sister_save=$this->sister_on;
            $this->sister_on=0;
            $sisters=implode("\n",$this->sisters);
            $sisters = preg_replace_callback("/(".$wordrule.")/",
                    array(&$this, 'link_repl'), $sisters);
            $msg=_("Sister Sites Index");
            echo "<div id='wikiSister'>\n<div class='separator'><tt class='foot'>----</tt></div>\n$msg<br />\n<ul>$sisters</ul></div>\n";
            $this->sister_on=$sister_save;
        }

        // call BackLinks for Category pages
        if (!empty($this->categories) && $this->use_builtin_category &&
                preg_match('@'.$this->category_regex.'@', $this->page->name))
            echo $this->macro_repl('BackLinks', '', $options);

        if (!empty($this->foots))
            echo $this->macro_repl('FootNote','',$options);

        // mediawiki like built-in category support
        if (!empty($this->categories))
        echo $this->macro_repl('Category', '', $options);

        if (!empty($this->update_pagelinks) and !empty($options['pagelinks']))
            store_pagelinks($this->page->name, array_keys($this->pagelinks));
    }

    function register_javascripts($js) {
        if (is_array($js)) {
            foreach ($js as $j) $this->register_javascripts($j);
            return true;
        } else {
            if ($js{0} == '<') { $tag=md5($js); }
            else $tag=$js;
            if (!isset($this->java_scripts[$tag]))
                $this->java_scripts[$tag]=$js;
            else return false;
            return true;
        }
    }

    function get_javascripts() {
        global $Config;
        if (!empty($Config['use_jspacker']) and !empty($Config['cache_public_dir'])) {
            include_once('lib/fckpacker.php'); # good but not work with prototype.
                define ('JS_PACKER','FCK_Packer/MoniWiki');
            $constProc = new FCKConstantProcessor();
            #$constProc->RemoveDeclaration = false ;
            #include_once('lib/jspacker.php'); # bad!
            #$packer = new JavaScriptPacker('', 0);
            #$packer->pack(); // init compressor
            #include_once('lib/jsmin.php'); # not work.

            $out='';
            $packed='';
            $pjs = array();
            $keys = array();
            foreach ($this->java_scripts as $k=>$js)
                if (!empty($js)) $keys[]=$k;

            if (empty($keys)) return '';
            $uniq = md5(implode(';',$keys));
            $cache=new Cache_text('js', array('ext'=>'html'));

            if ($cache->exists($uniq)) {
                foreach ($keys as $k) $this->java_scripts[$k]='';
                return $cache->fetch($uniq);
            }

            foreach ($this->java_scripts as $k=>$js) {
                if ($js) {
                    if ($js{0} != '<') {
                        $async = '';
                        if (strpos($js, ',') !== false && substr($js, 0, 5) == 'async') {
                            $async = ' async';
                            $js = substr($js, 6);
                        }
                        if (preg_match('@^(http://|/)@',$js)) {
                            $out.="<script$async type='text/javascript' src='$js'></script>\n";
                    } else {
                        if (file_exists('local/'.$js)) {
                            $fp = fopen('local/'.$js,'r');
                            if (is_resource($fp)) {
                                $_js = fread($fp,filesize('local/'.$js));
                                fclose($fp);
                                $packed.='/* '.$js.' */'."\n";
                                #$packed.= JSMin::minify($_js);
                                $packed.= FCKJavaScriptCompressor::Compress($_js, $constProc)."\n";
                                #$packed.= $packer->_pack($_js)."\n";
                                $pjs[]=$k;
                            }
                        } else { // is it exist ?
                            $js=$this->url_prefix.'/local/'.$js;
                            $out.="<script$async type='text/javascript' src='$js'></script>\n";
                        }
                    }
                    } else { //
                        $out.=$js;
                        if ( 0 and preg_match('/<script[^>]+(src=("|\')([^\\2]+)\\2)?[^>]*>(.*)<\/script>\s*$/s',$js,$m)) {
                            if (!empty($m[3])) {
                                $out.=$js;
                                #$out.="<script type='text/javascript' src='$js'></script>\n";
                            } else if (!empty($m[4])) {
                                $packed.='/* embeded '.$k.'*/'."\n";
                                #$packed.= $packer->_pack($js)."\n";
                                $packed.= FCKJavaScriptCompressor::Compress($m[4], $constProc)."\n";
                                #$packed.= JSMin::minify($js);
                                $pjs[]=$k;
                            }
                    }
                    }
                    $this->java_scripts[$k]='';

                }
            }
            $suniq = md5(implode(';',$pjs));

            $fc = new Cache_text('js', array('ext'=>'js', 'dir'=>$Config['cache_public_dir']));
            $jsname = $fc->getKey($suniq,0);
            $out.='<script type="text/javascript" src="'.$Config['cache_public_url'].'/'.$jsname.'"></script>'."\n";
            $cache->update($uniq,$out);

            $ver = FCKJavaScriptCompressor::Revision();
            $header='/* '.JS_PACKER.' '.$ver.' '.md5($packed).' '.date('Y-m-d H:i:s').' */'."\n";
            # save real compressed js file.
            $fc->_save($jsname, $header.$packed);
            return $out;
        }
        $out='';
        foreach ($this->java_scripts as $k=>$js) {
            if ($js) {
                if ($js{0} != '<') {
                    $async = '';
                    if (strpos($js, ',') !== false && substr($js, 0, 5) == 'async') {
                        $async = ' async';
                        $js = substr($js, 6);
                    }
                    if (!preg_match('@^(http://|/)@',$js))
                        $js=$this->url_prefix.'/local/'.$js;
                    $out.="<script$async type='text/javascript' src='$js'></script>\n";
                } else {
                    $out.=$js;
                }
                $this->java_scripts[$k]='';
            }
        }
        return $out;
    }

    function get_diff($text, $rev = '') {
        global $DBInfo;

        if (!isset($text[0]))
            $text = "\n";
        if (!empty($DBInfo->use_external_diff)) {
            $tmpf2 = tempnam($DBInfo->vartmp_dir, 'DIFF_NEW');
            $fp = fopen($tmpf2, 'w');
            if (!is_resource($fp)) return ''; // ignore
            fwrite($fp, $text);
            fclose($fp);

            $fp = popen('diff -u '.$this->page->filename.' '.$tmpf2.$this->NULL, 'r');
            if (!is_resource($fp)) {
                unlink($tmpf2);
                return '';
            }
            $out = '';
            while (!feof($fp)) {
                $line = fgets($fp, 1024);
                $out.= $line;
            }
            pclose($fp);
            unlink($tmpf2);
        } else {
            require_once('lib/difflib.php');
            $orig = $this->page->_get_raw_body();
            $olines = explode("\n", $orig);
            $tmp = array_pop($olines);
            if ($tmp != '') $olines[] = $tmp;
            $nlines = explode("\n", $text);
            $tmp = array_pop($nlines);
            if ($tmp != '') $nlines[] = $tmp;
            $diff = new Diff($olines, $nlines);
            $unified = new UnifiedDiffFormatter;
            $unified->trailing_cr = "&nbsp;\n"; // hack to see inserted empty lines
            $out.= $unified->format($diff);
        }
        return $out;
    }

    function get_merge($text,$rev="") {
        global $DBInfo;

        if (!$text) return '';
        # recall old rev
        $opts['rev']=$this->page->get_rev();
        $orig=$this->page->get_raw_body($opts);

        if (!empty($DBInfo->use_external_merge)) {
            # save new
            $tmpf3=tempnam($DBInfo->vartmp_dir,'MERGE_NEW');
            $fp= fopen($tmpf3, 'w');
            fwrite($fp, $text);
            fclose($fp);

            $tmpf2=tempnam($DBInfo->vartmp_dir,'MERGE_ORG');
            $fp= fopen($tmpf2, 'w');
            fwrite($fp, $orig);
            fclose($fp);

            $fp=popen("merge -p ".$this->page->filename." $tmpf2 $tmpf3".$this->NULL,'r');

            if (!is_resource($fp)) {
                unlink($tmpf2);
                unlink($tmpf3);
                return '';
            }
            while (!feof($fp)) {
                $line=fgets($fp,1024);
                $out .= $line;
            }
            pclose($fp);
            unlink($tmpf2);
            unlink($tmpf3);
        } else {
            include_once('lib/diff3.php');
            # current
            $current=$this->page->_get_raw_body();

            $merge= new Diff3(explode("\n",$orig),
                    explode("\n",$current),explode("\n",$text));
            $out=implode("\n",$merge->merged_output());
        }

        $out=preg_replace("/(<{7}|>{7}).*\n/","\\1\n",$out);

        return $out;
    }

    function send_header($header = '', $params = array()) {
        send_header($this, $header, $params);
    }

    function get_actions($args='',$options) {
        $menu=array();
        if (!empty($this->pi['#action']) && !in_array($this->pi['#action'],$this->actions)){
            $tmp =explode(" ",$this->pi['#action'],2);
            $act = $txt = $tmp[0];
            if (!empty($tmp[1])) $txt = $tmp[1];
            $menu[]= $this->link_to("?action=$act",_($txt)," rel='nofollow' accesskey='x'");
            if (strtolower($act) == 'blog')
                $this->actions[]='BlogRss';

        } else if (!empty($args['editable'])) {
            if ($args['editable']==1)
                $menu[]= $this->link_to("?action=edit",_("EditText")," rel='nofollow' accesskey='x'");
            else {
                if ($this->source_site) {
                    $url = $this->link_url($this->page->urlname, '?action=edit');
                    $url = $this->source_site.$url;
                    $url = "<a href='$url' class='externalLink source'><span>"._("EditText").'</span></a>';
                } else {
                    $url = _("NotEditable");
                }
                $menu[] = $url;
            }
        } else
            $menu[]= $this->link_to('?action=show',_("ShowPage"));

        if (!empty($args['refresh']) and $args['refresh'] ==1)
            $menu[]= $this->link_to("?refresh=1",_("Refresh")," rel='nofollow' accesskey='n'");
        $menu[]=$this->link_tag("FindPage","",_("FindPage"));

        if (empty($args['noaction'])) {
            foreach ($this->actions as $action) {
                if (strpos($action,' ')) {
                    list($act,$text)=explode(' ',$action,2);
                    if ($options['page'] == $this->page->name) {
                        $menu[]= $this->link_to($act,_($text));
                    } else {
                        $menu[]= $this->link_tag($options['page'],$act,_($text));
                    }
                } else {
                    $menu[]= $this->link_to("?action=$action",_($action), " rel='nofollow'");
                }
            }
        }
        return $menu;
    }

    function send_footer($args = '', $params = array()) {
        send_footer($this, $args, $params);
    }

    function send_title($msgtitle = '', $link = '', $params = array()) {
        send_title($this, $msgtitle, $link, $params);
    }

    /**
     * @deprecated
     */
    function set_origin($pagename) {
        call_macro($this, $pagename);
    }

    /**
     * @deprecated
     */
    function set_trailer($trailer = '', $pagename, $size = 5) {
        $params = array();
        $params['trail'] = $trailer;
        $params['size'] = $size;
        call_macro($this, $pagename, $params);
    }

    function errlog($prefix="LoG",$tmpname='') {
        global $DBInfo;

        $this->mylog='';
        $this->LOG='';
        if ($DBInfo->use_errlog) {
            if(getenv("OS")!="Windows_NT") {
                $this->mylog=$tmpname ? $DBInfo->vartmp_dir.'/'.$tmpname:
                    tempnam($DBInfo->vartmp_dir,$prefix);
                $this->LOG=' 2>'.$this->mylog;
            }
        } else {
            if(getenv("OS")!="Windows_NT") $this->LOG=' 2>/dev/null';
        }
    }

    function get_errlog($all=0,$raw=0) {
        global $DBInfo;

        $log=&$this->mylog;
        if ($log and file_exists($log) and ($sz=filesize($log))) {
            $fd=fopen($log,'r');
            if (is_resource($fd)) {
                $maxl=!empty($DBInfo->errlog_maxline) ? min($DBInfo->errlog_maxline,200):20;
                if ($all or $sz <= $maxl*70) { # approx log size ~ line * 70
                    $out=fread($fd,$sz);
                } else {
                    for ($i=0;($i<$maxl) and ($s=fgets($fd,1024));$i++)
                        $out.=$s;
                    $out.= "...\n";
                }
                fclose($fd);
                unlink($log);
                $this->LOG='';
                $this->mylog='';

                if (empty($DBInfo->raw_errlog) and !$raw) {
                    $out=preg_replace('/(\/[a-z0-9.]+)+/','/XXX',$out);
                }
                return $out;
            }
        }
        return '';
    }

    function internal_errorhandler($errno, $errstr, $errfile, $errline) {
        $errfile=basename($errfile);
        switch ($errno) {
            case E_WARNING:
                echo "<div><b>WARNING</b> [$errno] $errstr<br />\n";
                echo " in cache $errfile($errline)<br /></div>\n";
                break;
            case E_NOTICE:
                #echo "<div><b>NOTICE</b> [$errno] $errstr<br />\n";
                #echo "  on line $errline in cache $errfile<br /></div>\n";
                break;
            case E_USER_ERROR:
                echo "<div><b>ERROR</b> [$errno] $errstr<br />\n";
                echo "  Fatal error in file $errfile($errline)";
                echo ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
                echo "Skip...<br /></div>\n";
                break;

            case E_USER_WARNING:
                echo "<b>MoniWiki WARNING</b> [$errno] $errstr<br />\n";
                break;

            case E_USER_NOTICE:
                echo "<b>MoniWiki NOTICE</b> [$errno] $errstr<br />\n";
                break;

            default:
                echo "Unknown error type: [$errno] $errstr<br />\n";
                break;
        }

        /* http://kr2.php.net/manual/en/function.set-error-handler.php */
        return true;
    }
} # end-of-Formatter

# setup the locale like as the phpwiki style
function get_locales($default = 'en') {
    $languages=array(
            'en'=>array('en_US','english',''),
            'fr'=>array('fr_FR','france',''),
            'ko'=>array('ko_KR','korean',''),
            );
    if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE']))
        return array($languages[$default][0]);
    $lang= strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    $lang= strtr($lang,'_','-');
    $langs=explode(',',preg_replace(array("/;[^;,]+/","/\-[a-z]+/"),'',$lang));
    if ($languages[$langs[0]]) return array($languages[$langs[0]][0]);
    return array($languages[$default][0]);
}

function set_locale($lang, $charset = '', $default = 'en') {
    $supported=array(
            'en_US'=>array('ISO-8859-1'),
            'fr_FR'=>array('ISO-8859-1'),
            'ko_KR'=>array('EUC-KR','UHC'),
            );
    $charset= strtoupper($charset);
    if ($lang == 'auto') {
        # get broswer's settings
        $langs = get_locales($default);
        $lang = $langs[0];
    }
    // check server charset
    $server_charset = '';
    if (function_exists('nl_langinfo'))
        $server_charset= nl_langinfo(CODESET);

    if ($charset == 'UTF-8') {
        $lang.= '.'.$charset;
    } else {
        if ($supported[$lang] && in_array($charset,$supported[$lang])) {
            return $lang.'.'.$charset;
        } else {
            return $default;
        }
    }
    return $lang;
}

# get the pagename
function get_pagename($charset = 'utf-8') {
    // get PATH_INFO or parse REQUEST_URI
    $path_info = get_pathinfo();

    if (isset($path_info[1]) && $path_info[0] == '/') {
        // e.g.) /FrontPage => FrontPage
        $pagename = substr($path_info, 1);
    } else if (!empty($_SERVER['QUERY_STRING'])) {
        $goto=isset($_POST['goto'][0]) ? $_POST['goto']:(isset($_GET['goto'][0]) ? $_GET['goto'] : '');
        if (isset($goto[0])) $pagename=$goto;
        else {
            parse_str($_SERVER['QUERY_STRING'], $arr);
            $keys = array_keys($arr);
            if (!empty($arr['action'])) {
                if ($arr['action'] == 'edit') {
                    if (!empty($arr['value'])) $pagename = $arr['value'];
                } else if ($arr['action'] == 'login') {
                    $pagename = 'UserPreferences';
                }
                unset($arr['action']);
            }
            foreach ($arr as $k=>$v)
                if (empty($v)) $pagename = $k;
        }
    }

    // check the validity of a given page name for UTF-8 case
    if (strtolower($charset) == 'utf-8') {
        if (!preg_match('//u', $options['pagename']))
            $options['pagename'] = ''; // invalid pagename
    }

    if (isset($pagename[0])) {
        $pagename=_stripslashes($pagename);

        if ($pagename[0]=='~' and ($p=strpos($pagename,"/")))
            $pagename=substr($pagename,1,$p-1)."~".substr($pagename,$p+1);
    }
    return $pagename;
}

function init_requests(&$options) {
    global $DBInfo;

    if (!empty($DBInfo->user_class)) {
        include_once('plugin/user/'.$DBInfo->user_class.'.php');
        $class = 'User_'.$DBInfo->user_class;
        $user = new $class();
    } else {
        $user = new WikiUser();
    }

    $udb=new UserDB($DBInfo);
    $DBInfo->udb=$udb;

    if (!empty($DBInfo->trail)) // read COOKIE trailer
        $options['trail']=trim($user->trail) ? $user->trail:'';

    if ($user->id != 'Anonymous') {
        $user->info = $udb->getInfo($user->id); // read user info
        $test = $udb->checkUser($user); # is it valid user ?
        if ($test == 1) {
            // fail to check ticket
            // check user group
            if ($DBInfo->login_strict > 0 ) {
                # auto logout
                $options['header'] = $user->unsetCookie();
            } else if ($DBInfo->login_strict < 0 ) {
                $options['msg'] = _("Someone logged in at another place !");
            }
        } else {
            // check group
            $user->checkGroup();
        }
    } else
        // read anonymous user IP info.
        $user->info = $udb->getInfo('Anonymous');

    $options['id']=$user->id;
    $DBInfo->user=$user;

    // check is_mobile_func
    $is_mobile_func = !empty($DBInfo->is_mobile_func) ? $DBInfo->is_mobile_func : 'is_mobile';
    if (!function_exists($is_mobile_func))
        $is_mobile_func = 'is_mobile';
    $options['is_mobile'] = $is_mobile = $is_mobile_func();

    # MoniWiki theme
    if ((empty($DBInfo->theme) or isset($_GET['action'])) and isset($_GET['theme'])) {
        // check theme
        if (preg_match('@^[a-zA-Z0-9_-]+$@', $_GET['theme']))
            $theme = $_GET['theme'];
    } else {
        if ($is_mobile) {
            if (isset($_GET['mobile'])) {
                if (empty($_GET['mobile'])) {
                    setcookie('desktop', 1, time()+60*60*24*30, get_scriptname());
                    $_COOKIE['desktop'] = 1;
                } else {
                    setcookie('desktop', 0, time()-60*60*24*30, get_scriptname());
                    unset($_COOKIE['desktop']);
                }
            }
        }
        if (isset($_COOKIE['desktop'])) {
            $DBInfo->metatags_extra = '';
            if (!empty($DBInfo->theme_css))
                $theme = $DBInfo->theme;
        } else if ($is_mobile or !empty($DBInfo->force_mobile)) {
            if (!empty($DBInfo->mobile_theme))
                $theme = $DBInfo->mobile_theme;
            if (!empty($DBInfo->mobile_menu))
                $DBInfo->menu = $DBInfo->mobile_menu;
            $DBInfo->use_wikiwyg = 0; # disable wikiwyg
        } else if ($DBInfo->theme_css) {
            $theme=$DBInfo->theme;
        }
    }
    if (!empty($theme)) $options['theme']=$theme;

    if ($options['id'] != 'Anonymous') {
        $options['css_url']=!empty($user->info['css_url']) ? $user->info['css_url'] : '';
        $options['quicklinks']=!empty($user->info['quicklinks']) ? $user->info['quicklinks'] : '';
        $options['tz_offset']=!empty($user->info['tz_offset']) ? $user->info['tz_offset'] : date('Z');
        if (empty($theme)) $options['theme'] = $theme = !empty($user->info['theme']) ? $user->info['theme'] : '';
    } else {
        $options['css_url']=$user->css;
        $options['tz_offset']=$user->tz_offset;
        if (empty($theme)) $options['theme']=$theme=$user->theme;
    }

    if (!$options['theme']) $options['theme']=$theme=$DBInfo->theme;

    if ($theme and ($DBInfo->theme_css or !$options['css_url'])) {
        $css = is_string($DBInfo->theme_css) ? $DBInfo->theme_css : 'default.css';
        $options['css_url']=(!empty($DBInfo->themeurl) ? $DBInfo->themeurl:$DBInfo->url_prefix)."/theme/$theme/css/$css";
    }

    if ($user->id != 'Anonymous' and !empty($DBInfo->use_scrap)) {
        $pages = explode("\t",$user->info['scrapped_pages']);
        $tmp = array_flip($pages);
        if (isset($tmp[$options['pagename']]))
            $options['scrapped']=1;
        else
            $options['scrapped']=0;
    }
}

function init_locale($lang, $domain = 'moniwiki', $init = false) {
    global $Config,$_locale;
    if (isset($_locale)) {
        if (!@include_once('locale/'.$lang.'/LC_MESSAGES/'.$domain.'.php'))
            @include_once('locale/'.substr($lang,0,2).'/LC_MESSAGES/'.$domain.'.php');
    } else if (substr($lang,0,2) == 'en') {
        $test=setlocale(LC_ALL, $lang);
    } else {
        if (!empty($Config['include_path'])) $dirs=explode(':',$Config['include_path']);
        else $dirs=array('.');

        while ($Config['use_local_translation']) {
            $langdir=$lang;
            if(getenv("OS")=="Windows_NT") $langdir=substr($lang,0,2);
            # gettext cache workaround
            # http://kr2.php.net/manual/en/function.gettext.php#58310
            $ldir=$Config['cache_dir']."/locale/$langdir/LC_MESSAGES/";

            $tmp = '';
            $fp = @fopen($ldir.'md5sum', 'r');
            if (is_resource($fp)) {
                $tmp = '-'.trim(fgets($fp,1024));
                fclose($fp);
            } else {
                $init = 1;
            }

            if ($init and !file_exists($ldir.$domain.$tmp.'mo')) {
                include_once(dirname(__FILE__).'/plugin/msgtrans.php');
                macro_msgtrans(null,$lang,array('init'=>1));
            } else {
                $domain=$domain.$tmp;
                array_unshift($dirs,$Config['cache_dir']);
            }
            break;
        }

        $test=setlocale(LC_ALL, $lang);
        foreach ($dirs as $dir) {
            $ldir=$dir.'/locale';
            if (is_dir($ldir)) {
                bindtextdomain($domain, $ldir);
                textdomain($domain);
                break;
            }
        }
        if (!empty($Config['set_lang'])) putenv("LANG=".$lang);
        if (function_exists('bind_textdomain_codeset'))
            bind_textdomain_codeset ($domain, $Config['charset']);
    }
}

function get_frontpage($lang) {
    global $Config;

    $lcid=substr(strtok($lang,'_'),0,2);
    return !empty($Config['frontpages'][$lcid]) ? $Config['frontpages'][$lcid]:$Config['frontpage'];
}

function wiki_main($options) {
    global $DBInfo,$Config;

    // get pagename
    $options['pagename'] = get_pagename($Config['charset']);

    // get default pagename
    if (!isset($options['pagename'][0]))
        $options['pagename'] = get_frontpage($options['lang']);

    $pagename = $options['pagename'];

    # get primary variables
    if (isset($_SERVER['REQUEST_METHOD']) and $_SERVER['REQUEST_METHOD']=='POST') {
        // reset some reserved variables
        if (isset($_POST['retstr'])) unset($_POST['retstr']);
        if (isset($_POST['header'])) unset($_POST['header']);

        # hack for TWiki plugin
        $action = '';
        if (!empty($_FILES['filepath']['name'])) $action='draw';
        if (isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
            # hack for Oekaki: PageName----action----filename
            list($pagename,$action,$value)=explode('----',$pagename,3);
            $options['value']=$value;
        } else {
            $value=!empty($_POST['value']) ? $_POST['value'] : '';
            $action=!empty($_POST['action']) ? $_POST['action'] : $action;

            if (empty($action)) {
                $dum=explode('----',$pagename,3);
                if (isset($dum[0][0]) && isset($dum[1][0])) {
                    $pagename=trim($dum[0]);
                    $action=trim($dum[1]);
                    $value=isset($dum[2][0]) ? $dum[2] : '';
                }
            }
        }
        $goto=!empty($_POST['goto']) ? $_POST['goto'] : '';
        $popup=!empty($_POST['popup']) ? 1 : 0;

        // ignore invalid POST actions
        if (empty($goto) and empty($action)) {
            header('Status: 405 Not allowed');
            return;
        }
    } else {
        // reset some reserved variables
        if (isset($_GET['retstr'])) unset($_GET['retstr']);
        if (isset($_GET['header'])) unset($_GET['header']);

        $action=!empty($_GET['action']) ? $_GET['action'] : '';
        $value=isset($_GET['value'][0]) ? $_GET['value'] : '';
        $goto=isset($_GET['goto'][0]) ? $_GET['goto'] : '';
        $rev=!empty($_GET['rev']) ? $_GET['rev'] : '';
        if ($options['id'] == 'Anonymous')
            $refresh = 0;
        else
            $refresh = !empty($_GET['refresh']) ? $_GET['refresh'] : '';
        $popup=!empty($_GET['popup']) ? 1 : 0;
    }
    // parse action
    // action=foobar, action=foobar/macro, action=foobar/json etc.
    $options['action_mode'] = $action_mode = '';
    $options['full_action'] = $full_action = $action;
    if (($p=strpos($action, '/'))!==false) {
        $full_action = strtr($action, '/', '-');
        $action_mode = substr($action, $p + 1);
        $action=substr($action,0,$p);
    }

    $options['page']=$pagename;
    $options['action'] = $action;
    $reserved = array('call', 'prefix');
    foreach ($reserved as $k)
        unset($options[$k]); // unset all reserved

    // call local_pre_check
    if (function_exists('local_pre_check'))
        local_pre_check($action, $options);

    // check pagename length
    $key = $DBInfo->pageToKeyname($pagename);
    if (!empty($action) && strlen($key) > 255) {
        $i = 252; // 252 + reserved 3 (.??) = 255

        $newname = $DBInfo->keyToPagename(substr($key, 0, 252));
        $j = mb_strlen($newname, $Config['charset']);
        $j--;
        do {
            $newname = mb_substr($pagename, 0, $j, $Config['charset']);
            $key = $DBInfo->pageToKeyname($newname);
        } while (strlen($key) > 248 && --$j > 0);

        $options['page'] = $newname;
        $options['orig_pagename'] = $pagename; // original page name
        $pagename = $newname;
    } else {
        $options['orig_pagename'] = '';
    }

    // check action
    if (isset($action[0]) && $action !== 'show') {
        // save the action name
        $action_name = $action;
        // is it valid action ?
        $plugin = getPlugin($action);
        // $act == 'false'; // disabled action
        // $act == null; // not found

        if (empty($plugin)) {
            $options['action'] = $action;
            if ($plugin === false)
                $title = sprintf(_("%s action is disabled."), _html_escape($action));
            else
                $title = sprintf(_("%s action is not found."), _html_escape($action));
            $options['title'] = $title;
            return do_invalid(null, $options);
        }
    }

    // create WikiPage object
    $page = $DBInfo->getPage($pagename);

    // setup some default variable $options depend on the current user.
    // WikiUser initialized in it.
    init_requests($options);

    // set the real IP address for proxy
    $remote = $_SERVER['REMOTE_ADDR'];
    $real = realIP();
    if ($remote != $real) {
        $_SERVER['OLD_REMOTE_ADDR'] = $remote;
        $_SERVER['REMOTE_ADDR'] = $real;
    }

    // start session
    if (empty($Config['nosession']) and is_writable(ini_get('session.save_path')) ) {
        require_once('lib/session.php');
        $session_name = session_name();
        if (!empty($_COOKIE[$session_name])) {
            $session_id = $_COOKIE[$session_name];
        } else {
            $session_id = session_id();
        }
        if (!empty($session_id)) {
            _session_start($session_id, $options['id']);
        } elseif (!empty($options['id']) !== 'Anonymous') {
            _session_start('dummy', $options['id']);
        }
    }

    // setup cache headers.
    // it depends on user id
    http_default_cache_control($options);

    // load ruleset
    if (!empty($Config['config_ruleset'])) {
        $ruleset_file = 'config/ruleset.'.$Config['config_ruleset'].'.php';
        if (file_exists($ruleset_file)) {
            $ruleset = load_ruleset($ruleset_file);

            $Config['ruleset'] = $ruleset;
        }

        // is it robot ?
        if (!empty($ruleset['allowedrobot'])) {
            if (empty($_SERVER['HTTP_USER_AGENT'])) {
                $options['is_robot'] = 1;
            } else {
                $options['is_robot'] = is_allowed_robot($ruleset['allowedrobot'], $_SERVER['HTTP_USER_AGENT']);
            }
        }

        // setup staff members
        if (!empty($ruleset['staff'])) {
            $DBInfo->members = array_merge($DBInfo->members, $ruleset['staff']);
        }
    }

    $page->is_static = false;

    // setup Page instructions.
    $pis = array();

    // get PI cache
    if ($page->exists()) {
        $page->pi = $pis = $page->get_instructions('', array('refresh'=>$refresh));

        // set some PIs for robot
        if (!empty($options['is_robot'])) {
            $DBInfo->use_sectionedit = 0; # disable section edit
                $page->is_static = true;
        } else if ($_SERVER['REQUEST_METHOD'] == 'GET' or $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            if (empty($action) and empty($refresh))
                $page->is_static = empty($pis['#nocache']) && empty($pis['#dynamic']);
        }
    }

    // HEAD support for robots
    if (!empty($_SERVER['REQUEST_METHOD']) and $_SERVER['REQUEST_METHOD'] == 'HEAD') {
        if (!$page->exists()) {
            header("HTTP/1.1 404 Not found");
            header("Status: 404 Not found");
        } else {
            if ($page->is_static or is_static_action($options)) {
                $mtime = $page->mtime();
                $etag = $page->etag($options);
                $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $mtime);
                header('Last-Modified: '.$lastmod);
                if (!empty($action)) {
                    $etag = '"'.$etag.'"';
                    header('ETag: '.$etag);
                }

                // checksum request
                if (isset($_SERVER['HTTP_X_GET_CHECKSUM']))
                    header('X-Checksum: md5-'. md5($page->get_raw_body()));
            }
        }
        return;
    }

    // conditional get support.
    if (is_static_action($options) or
            (!empty($DBInfo->use_conditional_get) and $page->is_static)) {
        $mtime = $page->mtime();
        $etag = $page->etag($options);
        $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $mtime);
        $need = http_need_cond_request($mtime, $lastmod, $etag);
        if (!$need) {
            @ob_end_clean();
            $headers = array();
            $headers[] = 'HTTP/1.0 304 Not Modified';
            $headers[] = 'Last-Modified: '.$lastmod;

            foreach ($headers as $header) header($header);
            return;
        }
    }

    // create formatter.
    $formatter = new Formatter($page,$options);

    $formatter->refresh=!empty($refresh) ? $refresh : '';
    $formatter->popup=!empty($popup) ? $popup : '';
    $formatter->tz_offset=$options['tz_offset'];

    // check blocklist/whitelist for block_actions
    $act = strtolower($action);
    while (!empty($DBInfo->block_actions) && !empty($ruleset) && in_array($act, $DBInfo->block_actions)) {
        require_once 'lib/checkip.php';

        // check whitelist
        if (isset($ruleset['whitelist']) && check_ip($ruleset['whitelist'], $_SERVER['REMOTE_ADDR'])) {
            break;
        }

        $res = null;
        // check blacklist
        if ((isset($ruleset['blacklist']) &&
                    check_ip($ruleset['blacklist'], $_SERVER['REMOTE_ADDR'])) ||
                (isset($ruleset['blacklist.ranges']) &&
                 search_network($ruleset['blacklist.ranges'], $_SERVER['REMOTE_ADDR'])))
        {
            $res = true;
        } else if (!empty($DBInfo->use_dynamic_blacklist)) {
            require_once('plugin/ipinfo.php');
            $blacklist = get_cached_temporary_blacklist();
            $retval = array();
            $ret = array('retval'=>&$retval);
            $res = search_network($blacklist, $_SERVER['REMOTE_ADDR'], $ret);

            if ($res !== false) {
                // retrieve found
                $ac = new Cache_Text('ipblock');
                $info = $ac->fetch($retval, 0, $ret);
                if ($info !== false) {
                    if (!$info['suspended']) // whitelist IP
                        break;
                    $res = true;
                } else {
                    $ac->remove($retval); // expired IP entry.
                    $res = false;
                }
            }
        }

        // show warning message
        if ($res) {
            $options['notice']=_("Your IP is in the blacklist");
            $options['msg']=_("Please contact WikiMasters");
            $options['msgtype'] = 'warn';

            if (!empty($DBInfo->edit_actions) and in_array($act, $DBInfo->edit_actions))
                $options['action'] = $action = 'edit';
            else if ($act != 'edit')
                $options['action'] = $action = 'show';
            break;
        }

        // check kiwirian
        if (isset($ruleset['kiwirian']) && in_array($options['id'], $ruleset['kiwirian'])) {
            $options['title']=_("You are blocked in this wiki");
            $options['msg']=_("Please contact WikiMasters");
            do_invalid($formatter,$options);
            return false;
        }

        break;
    }

    // set robot class
    if (!empty($options['is_robot'])) {
        if (!empty($DBInfo->security_class_robot)) {
            $class='Security_'.$DBInfo->security_class_robot;
            include_once('plugin/security/'.$DBInfo->security_class_robot.'.php');
        } else {
            $class='Security_robot';
            include_once('plugin/security/robot.php');
        }
        $DBInfo->security = new $class ($DBInfo);
        // is it allowed to robot ?
        if (!$DBInfo->security->is_allowed($action,$options)) {
            $action='show';
            if (!empty($action_mode)) {
                echo '[]'; // FIXME
                return;
            }
        }
        $DBInfo->extra_macros='';
    }

    while (empty($action) or $action=='show') {
        if (isset($value[0])) { # ?value=Hello
            $options['value']=$value;
            do_goto($formatter,$options);
            return true;
        } else if (isset($goto[0])) { # ?goto=Hello
            $options['value']=$goto;
            do_goto($formatter,$options);
            return true;
        }
        if (!$page->exists()) {
            if (isset($options['retstr']))
                return false;
            if (!empty($DBInfo->auto_search) && $action!='show' && $p=getPlugin($DBInfo->auto_search)) {
                $action=$DBInfo->auto_search;
                break;
            }

            // call notfound action
            $action = 'notfound';
            break;
        }
        # render this page

        if (isset($_GET['redirect']) and !empty($DBInfo->use_redirect_msg) and $action=='show'){
            $redirect = $_GET['redirect'];
            $options['msg']=
                '<h3>'.sprintf(_("Redirected from page \"%s\""),
                        $formatter->link_tag(_rawurlencode($redirect), '?action=show', $redirect))."</h3>";
        }

        if (empty($action)) $options['pi']=1; # protect a recursivly called #redirect

        if (!empty($DBInfo->control_read) and !$DBInfo->security->is_allowed('read',$options)) {
            $options['action'] = 'read';
            do_invalid($formatter,$options);
            return;
        }

        $formatter->pi=$formatter->page->get_instructions();

        if (!empty($DBInfo->body_attr))
            $options['attr'] = $DBInfo->body_attr;

        $ret = $formatter->send_header('', $options);

        if (empty($options['is_robot'])) {
            if (!empty($DBInfo->use_referer) and isset($_SERVER['HTTP_REFERER']))
                log_referer($_SERVER['HTTP_REFERER'], $pagename);
        }
        $formatter->send_title('', '', $options);

        $formatter->write("<div id='wikiContent'>\n");
        if (isset($options['timer']) and is_object($options['timer'])) {
            $options['timer']->Check("init");
        }

        // force #nocache for #redirect pages
        if (isset($formatter->pi['#redirect'][0]))
            $formatter->pi['#nocache'] = 1;

        $extra_out='';
        $options['pagelinks']=1;
        if (!empty($Config['cachetime']) and $Config['cachetime'] > 0 and empty($formatter->pi['#nocache'])) {
            $cache= new Cache_text('pages', array('ext'=>'html'));
            $mcache= new Cache_text('dynamic_macros');
            $mtime=$cache->mtime($pagename);
            $now=time();
            $check=$now-$mtime;
            $_macros=null;
            if ($cache->mtime($pagename) < $formatter->page->mtime()) $formatter->refresh = 1; // force update

            $valid = false;
            $delay = !empty($DBInfo->default_delaytime) ? $DBInfo->default_delaytime : 0;

            if (empty($formatter->refresh) and $DBInfo->checkUpdated($mtime, $delay) and ($check < $Config['cachetime'])) {
                if ($mcache->exists($pagename))
                    $_macros= $mcache->fetch($pagename);

                // FIXME TODO: check postfilters
                if (0 && empty($_macros)) {
                    #$out = $cache->fetch($pagename);
                    $valid = $cache->fetch($pagename, '', array('print'=>1));
                } else {
                    $out = $cache->fetch($pagename);
                    $valid = $out !== false;
                }
                $mytime=gmdate("Y-m-d H:i:s",$mtime+$options['tz_offset']);
                $extra_out= "<!-- Cached at $mytime -->";
            }
            if (!$valid) {
                $formatter->_macrocache=1;
                ob_start();
                $formatter->send_page('',$options);
                flush();
                $out=ob_get_contents();
                ob_end_clean();
                $formatter->_macrocache=0;
                $_macros = $formatter->_dynamic_macros;
                if (!empty($_macros))
                    $mcache->update($pagename,$_macros);
                if (isset($out[0]))
                    $cache->update($pagename, $out);
            }
            if (!empty($_macros)) {
                $mrule=array();
                $mrepl=array();
                foreach ($_macros as $m=>$v) {
                    if (!is_array($v)) continue;
                    $mrule[]='@@'.$v[0].'@@';
                    $options['mid']=$v[1];
                    $mrepl[]=$formatter->macro_repl($m,'',$options); // XXX
                }
                echo $formatter->get_javascripts();
                $out=str_replace($mrule,$mrepl,$out);

                // no more dynamic macros found
                if (empty($formatter->_dynamic_macros)) {
                    // update contents
                    $cache->update($pagename, $out);
                    // remove dynamic macros cache
                    $mcache->remove($pagename);
                }
            }
            if ($options['id'] != 'Anonymous')
                $args['refresh']=1; // add refresh menu
        } else {
            ob_start();
            $formatter->send_page('', $options);
            flush();
            $out = ob_get_contents();
            ob_end_clean();
        }

        // fixup to use site specific thumbwidth
        if (!empty($Config['site_thumb_width']) and
                $Config['site_thumb_width'] != $DBInfo->thumb_width) {
            $opts = array('thumb_width'=>$Config['site_thumb_width']);
            $out = $formatter->postfilter_repl('imgs_for_mobile', $out, $opts);
        }
        echo $out,$extra_out;

        // automatically set #dynamic PI
        if (empty($formatter->pi['#dynamic']) and !empty($formatter->_dynamic_macros)) {
            $pis = $formatter->pi;
            if (empty($pis['raw'])) {
                // empty PIs
                $pis = array();
            } else if (isset($pis['#format']) and !preg_match('/#format\s/', $pis['raw'])) {
                // #format not found in PIs
                unset($pis['#format']);
            }
            $pis['#dynamic'] = 1; // internal instruction

            $pi_cache = new Cache_text('PI');
            $pi_cache->update($formatter->page->name, $pis);
        } else if (empty($formatter->_dynamic_macros) and !empty($formatter->pi['#dynamic'])) {
            $pi_cache = new Cache_text('PI');
            $pi_cache->remove($formatter->page->name); // reset PI
            $mcache->remove($pagename); // remove macro cache
            if (isset($out[0]))
                $cache->update($pagename, $out); // update cache content
        }

        if (isset($options['timer']) and is_object($options['timer'])) {
            $options['timer']->Check("send_page");
        }
        $formatter->write("<!-- wikiContent --></div>\n");

        if (!empty($DBInfo->extra_macros) and
                $formatter->pi['#format'] == $DBInfo->default_markup) {
            if (!empty($formatter->pi['#nocomment'])) {
                $options['nocomment']=1;
                $options['notoolbar']=1;
            }
            $options['mid']='dummy';
            echo '<div id="wikiExtra">'."\n";
            $mout = '';
            $extra = array();
            if (is_array($DBInfo->extra_macros))
                $extra = $DBInfo->extra_macros;
            else
                $extra[] = $DBInfo->extra_macros; // XXX
            if (!empty($formatter->pi['#comment'])) array_unshift($extra,'Comment');

            foreach ($extra as $macro)
                $mout.= $formatter->macro_repl($macro,'',$options);
            echo $formatter->get_javascripts();
            echo $mout;
            echo '</div>'."\n";
        }

        $args['editable']=1;
        $formatter->send_footer($args,$options);
        return;
    }

    $options['value'] = $value;
    $options['action_mode'] = $action_mode;
    $options['full_action'] = $full_action;
    call_action($formatter, $action, $options);
}

// load site specific config variables.
function load_site_config($topdir, $site, &$conf, &$deps) {
    // dependency
    $deps = array($topdir.'/config.php', dirname(__FILE__).'/lib/wikiconfig.php');

    // override some $conf vars to control site specific options
    if (!empty($site)) {
        $configfile = $topdir.'/config/config.'.$site.'.php';
        if (file_exists($configfile)) {
            $deps[] = $configfile;
            $local = _load_php_vars($configfile);
            // update $conf
            foreach ($local as $k=>$v) {
                $conf[$k] = $v;
            }
        }
    }

    require_once(dirname(__FILE__).'/lib/wikiconfig.php');
    $conf = wikiConfig($conf);
}

// load cached site specific config variables.
function load_cached_site_config($topdir, $site, &$conf, $params = array()) {
    //$cache = new Cache_text('config', array('depth'=>0, 'ext'=>'php'));
    $cache = new Cache_text('settings', array('depth'=>0));

    // cached config key
    $key = 'config';
    if (!empty($site))
        $key .= '.'.$site;

    if (!($cached_config = $cache->fetch($key, 0, $params))) {
        // update site specific config
        $deps = array();
        load_site_config($topdir, $site, $conf, $deps);

        // update config cache
        $cache->update($key, $conf, 0, array('deps'=>$deps));
    } else {
        $conf = $cached_config;
    }
}

// include classes
require_once('lib/WikiDB.php');
require_once('lib/UserDB.php');
require_once('lib/WikiPage.php');
require_once('lib/WikiUser.php');

// include base classes
require_once('lib/metadb.base.php');
require_once('lib/security.base.php');

// common
require_once('lib/pluginlib.php');

if (!defined('INC_MONIWIKI')):
# Start Main
$Config = getConfig('config.php', array('init'=>1));
require_once('wikilib.php');
require_once('lib/win32fix.php');
require_once('lib/cache.text.php');
require_once('lib/timer.php');
require_once('lib/output.php');

$options = array();
if (class_exists('Timer')) {
  $timing = new Timer();
  $options['timer'] = &$timing;
  $options['timer']->Check("load");
}

$topdir = dirname(__FILE__);
// always load the global local config
if (file_exists($topdir.'/config/site.local.php'))
    require_once($topdir.'/config/site.local.php');
else if (isset($Config['site_local_php']) and file_exists($Config['site_local_php']))
    require_once($Config['site_local_php']);

// load site specific config with default config variables.
//$deps = array();
//load_site_config(dirname(__FILE__), $_SERVER['HTTP_HOST'], $Config, $deps);
load_cached_site_config(dirname(__FILE__), $_SERVER['HTTP_HOST'], $Config);

$DBInfo= new WikiDB($Config);

if (isset($options['timer']) and is_object($options['timer'])) {
  $options['timer']->Check("load");
}

$lang = set_locale($Config['lang'], $Config['charset'], $Config['default_lang']);
init_locale($lang);
$DBInfo->lang = $lang;
$options['lang'] = $lang;

wiki_main($options);
endif;
// vim:et:sts=4:sw=4
?>
