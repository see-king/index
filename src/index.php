<?php
namespace SESEd;

/**
 * 
 * A really simple class for rendering index html with predefined and rooted media tags
 * required constants:
 * IMAGE_ROOT - http root to images
 * 
 * usage:
 * 
 * $index = new SESEd\Index( 
 *      "index.tpl.html", 
 *      array(
 *          defaultTags = array(
 *              
 *          )
 *      )
 * )
 * 
 */


 class Index {
    
    protected $html = "";
    protected $sitemap = array();
    protected $url = "/";
    protected $language = "en";
    
    protected $defaultTags = array();

    protected $response_code = 200;


    function __construct( $html, $params = array() )
    {
        $this->html = $html;

        
        $this->sitemap = @$params["sitemap"] ?: array( "/" => array( "title" => "Home") );

        $this->url = $_SERVER["REQUEST_URI"];

        $this->defaultTags = @$params["defaultTags"] ?: array();

        $this->parseHead();
    }
    
    /**
     * Recursively browses sitemap to find the current node.
     * If node not found, returns false
     */
    function browseSitemap( $root, $map = null,  $sep = "/" ){

        $map = is_null($map) ? $this->sitemap : $map;
        
        $chunks = explode( $sep, $root );        
        // var_dump( $chunks );

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
        $item = $this->browseSitemap($this->url);
        // var_dump( $item, $this->url );
        $tags = array();

        if( $item ){
            foreach( $this->defaultTags as $key => $value ){                
                switch( $key ){
                    case "url":
                        $tags[$key] = SITE . $this->url;
                        break;
                    case "description":
                        $tags[$key] = isset( $item[$key][$this->language] ) ? $item[$key][$this->language] : $this->defaultTags[$key][$this->language];
                        break;
                    case "title":
                        $tags[$key] = isset( $item[$key] ) ? 
                        $this->defaultTags[$key] . " | " . $item[$key] . ( isset( $item['subtitle']) ? " | ".$item['subtitle']  : "" ): 
                            $this->defaultTags[$key];
                        break;
                    case "image":
                        // if image is not set or empty, use default image
                        $image = @$item[$key] ?: $this->defaultTags[$key];

                        // if image path contains http it's an absolute path and doesn't need prefixes
                        $imagePrefix = (strpos( $image, 'http') === false) ? IMAGE_ROOT : "" ; 

                        //$tags[$key] = isset( $image ) ?  SITE . IMAGE_ROOT . $image : SITE . IMAGE_ROOT . ;
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