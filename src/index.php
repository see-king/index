<?php
namespace SeeKing;

/**
 * 
 * A really simple class for rendering index html with predefined and rooted media tags.
 * Can be useful to host a one-page front-end app.
 * Declares response code 200 for a found route and 404 for not found ones.
 * 
 * required constants: 
 * const SITE - the complete site domain, e.g. https://www.sitexample.com  * 
 * 
 * Passed html file must already have all of the media tags to be replaced.
 * 
 * Sitemap is an assoc array of "route"=>[] , starting with home root  ("" route).
 * Each item may have 'title', 'description' (strings) and 'items' (nested assoc array of "route" => [] )
 * 
 * usage:
 * 
 * $index = new SESEd\Index( 
 *      "index.tpl.html", // this is the path to html
 *      [ 
 *          // roots configuration          
 *          "domain" => "http://sitexample.com",
 *          "image_root" => "http://images.sitexample.com/", 
 *          "base_root" => "", // the base root. change it to any base root, e.r. /some/base/root (no trailing slash)
 *          
 *          // title separator - the element that will separate site title from page title in document title.
 *          "title_separaror" => " | ",
 * 
 *          // tags configuration
 *          "tags" => [
 *              "image" => "http://images.sitexample.com/logo.jpg",
 *              "url" => "http://sitexample.com",
 *              "type" => "website",
 *              "title" => "Site Title",         
 *              "description" => array( 
 *                   "en" => "This site might be the best site in whole WWW"
 *               ), 
 *               "locale" => "en_US",
 *               "site_name" => "Example Site",
 *               "fb:app_id" => "1234567890"
 *          ],
 * 
 *          // sitemap
 *          "sitemap" => [
 *              "" => [
 *                  "title" => "Site title",
 *                  "description": [  "en" : "This site might be the best site in the whole WWW", "es" : "Este sitio podria ser el mejor sitio en todo el WWW" ]
 *                  "items" => [
 *                       "about" => {
 *                          "title" : "About"
 *                          "description": [  "en" : "The page about the site.", "es" : "La pagina sobre el sitio" ]
 *                       },
 *                       "contacts" => {
 *                          "title" : "Shop",
 *                          "description": [  "en" : "Contact us using the form", "es" : "Contactenos a travez de la forma" ]
 *                       },
 *                       "shop" => {
 *                          "title" : "Shop"
 *                       },  
 * 
 *                  ]
 *              ]
 *          ]
 *      ]
 * )
 * 
 */


 class Index {
    
    protected $html = "";    
    protected $url = "/";
    protected $language = "en";
    protected $sitemap = array();
    protected $defaultTags = array();
    protected $domain;
    protected $image_root;
    protected $base_root;
    protected $title_separator = " | ";
    protected $response_code = 200;


    function __construct( $tplPath, $params = array() )
    {    
        $this->html = \file_get_contents($tplPath);

        $this->url          = $_SERVER["REQUEST_URI"];
        
        $this->sitemap      = @$params["sitemap"] ?: array( "/" => array( "title" => "Home") );
        $this->defaultTags  = @$params["defaultTags"] ?: array();
        $this->domain       = @$params["domain"] ?: "http://sitexample.com";
        $this->image_root   = @$params["image_root"] ?: "/images/";
        $this->base_root    = @$params["base_root"] ?: "";

        $this->title_separator = @$params["title_separator"] ?: " | ";

        $this->parseHead();
    }
    
    /**
     * Recursively browses sitemap to find the current node.
     * If node not found, returns false
     */
    function browseSitemap( $root, $map = null,  $sep = "/" ){

        $map = is_null($map) ? $this->sitemap : $map;
        
        if( $this->base_root ){

            if (substr($root, 0, strlen($this->base_root)) == $this->base_root) {
                $root = substr($root, strlen($this->base_root));
            } 

            
            // var_dump($root);
        }

        $chunks = explode( $sep, $root );        

        // remove last empty node
        if( $chunks[ sizeof($chunks) - 1] == "" ){
            array_pop($chunks);
        }

        $node = array_shift($chunks);
        if( sizeof($chunks) > 0 ){
            if( isset( $map[ $node ]['items'] ) ){                
                // recursively treat map[items]
                return $this->browseSitemap( implode($sep, $chunks), $map[$node]["items"], $sep );
            } else {
                return false;
            }
        } else {
            if( isset( $map[ $node ] ) ){                
                return $map[ $node ];
            } else {
                return false;
            }
        }
    }



    function replaceTag(  $str, $tag, $value ){
        $regx = "/(property=\"og:$tag\" *content=\")[^\"]*(\")/m";
        // var_dump( "replacing tag $tag for '$value'");
        // return $str.replace( $regx, "$1"+ $value + "$2" );
        return preg_replace( $regx, "$1$value$2", $str );
    }

    
    function parseHead(){
        
        // fetch the item from sitemap based on current URL
        $item = $this->browseSitemap($this->url);        

        $tags = array();

        if( is_array($item) ){
            foreach( $this->defaultTags as $key => $value ){                
                
                // treat each key specifically
                switch( $key ){
                    case "url":
                        $tags[$key] = $this->domain . $this->url;
                        break;
                    case "description":
                        $tags[$key] = isset( $item[$key][$this->language] ) ? $item[$key][$this->language] : $this->defaultTags[$key][$this->language];
                        break;
                    case "title":
                        $tags[$key] = isset( $item[$key] ) ? 
                            $this->defaultTags[$key] . $this->title_separator . $item[$key] . 
                            ( isset( $item['subtitle']) ? $this->title_separator.$item['subtitle']  : "" ): $this->defaultTags[$key];

                        // adjust <title>
                        // $regx = '/(<title>)[^\"<>]*(<\\title>)/';
                        $regx = '/(<title>)[^<>\/]*(<\/title>)/m';
                        $value = $tags[$key];
                        $this->html = preg_replace( $regx, "$1$value$2", $this->html );

                        break;
                    case "image":
                        // if image is not set or empty, use default image
                        $image = @$item[$key] ?: $this->defaultTags[$key];

                        // if image path contains http it's an absolute path and doesn't need prefixes
                        $imagePrefix = (strpos( $image, 'http') === false) ? $this->image_root : "" ; 
                        
                        $tags[$key] = $imagePrefix . $image;
                        break;                        
                    default:
                        $tags[$key] = isset( $item[$key] ) ? $item[$key] : $this->defaultTags[$key];
                        break;
                }

                $this->html = $this->replaceTag( $this->html, $key, $tags[$key]);
            }
        } else {
            // page not found
            $this->response_code = 404;
        }
    }

    function render(){ 
        // deal with 5.3
        if( PHP_VERSION < "5.4.0" ){
            header('X-PHP-Response-Code: '. $this->response_code, true, $this->response_code);
        } else { 
            http_response_code ( $this->response_code );
        }
        return $this->html; 
    }
}